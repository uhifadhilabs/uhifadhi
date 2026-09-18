<?php

declare(strict_types=1);

/*
 * This file is part of the Uhifadhi core.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\Bundle\AreaBundle\Controller;

use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Exception\ZoneImportException;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneRepository;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneEventService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneImportDraftService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneImportService;

/**
 * A FILE BECOMES ZONES, IN TWO STEPS AND ONE DECISION.
 *
 * THE PREVIEW WRITES NOTHING. It reads the file, states a verdict for every
 * feature, and puts the plan where the confirm can find it; the document itself
 * is let go there and then. The person is then looking at exactly what they are
 * about to approve — the same plate, the same rings, the same colours.
 *
 * THE CONFIRM WRITES THE SUBSET IT IS NAMED, and it is additive: features that
 * no longer fit are reported, never forced, and nothing is overwritten to make
 * room for them.
 *
 * BOTH ANSWER WITH A REDIRECT. A confirm that rendered its own result would
 * import the file a second time the moment somebody refreshed.
 */
final readonly class ZoneImportController
{
    public const string TOKEN = 'area_zones_import';

    /** The file field, on the shell's uploader and in the request alike. */
    public const string FILE_FIELD = 'zones';

    /** The checkbox each arriving feature carries, so the subset is the person's. */
    public const string SUBSET_FIELD = 'arriving';

    public function __construct(
        private ZoneImportService $imports,
        private ZoneImportDraftService $draft,
        private ZoneEventService $events,
        private ZoneRepository $zones,
        private CsrfTokenManagerInterface $csrf,
        private UrlGeneratorInterface $urls,
        private ?TokenStorageInterface $tokens = null,
    ) {
    }

    #[Route('/areas/{uuid}/zones/import/preview', name: 'area_zones_import_preview', requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted('area.edit')]
    public function preview(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
    ): Response {
        $this->denyUnlessTokenValid($request);
        $this->draft->dropPlan($area);

        $file = $request->files->get(self::FILE_FIELD);
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $offered = $file instanceof UploadedFile ? $file->getClientOriginalName() : 'the file';
            /*
             * A FILE THAT NEVER ARRIVED IS NOT A FILE THAT WAS REFUSED, and the
             * difference is the one thing the person needs: PHP drops an upload
             * over the server's limit before any of this code runs, so the size
             * is named rather than the contents blamed.
             */
            $this->draft->holdRefusal($area, $offered, \sprintf(
                'the file did not reach the server — it was larger than this server\'s upload limit (%s), or no file was chosen',
                (string) (\ini_get('post_max_size') ?: 'unknown'),
            ));

            return $this->backToTheSection($area);
        }

        $name = $file->getClientOriginalName();
        $preferred = $request->request->get('nameProperty');

        try {
            $this->draft->holdPlan($area, $this->imports->plan(
                $area,
                $file,
                $name,
                \is_string($preferred) && '' !== $preferred ? $preferred : null,
            ));
        } catch (ZoneImportException $e) {
            $this->draft->holdRefusal($area, $name, $e->getMessage());
            $this->events->refused($area, $e->getMessage(), $this->actor());
        }

        return $this->backToTheSection($area);
    }

    #[Route('/areas/{uuid}/zones/import/confirm', name: 'area_zones_import_confirm', requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted('area.edit')]
    public function confirm(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
    ): Response {
        $this->denyUnlessTokenValid($request);

        $plan = $this->draft->plan($area);
        if (null === $plan) {
            // The plan expired with the session, or the confirm was replayed
            // after the import already ran. Neither is an error worth a page.
            return $this->backToTheSection($area);
        }

        $chosen = $request->request->all(self::SUBSET_FIELD);
        $names = array_values(array_filter($chosen, \is_string(...)));

        $wasEmpty = 0 === $this->zones->countFor($area);
        $result = $this->imports->apply($area, $plan, $names, $this->actor());
        $this->draft->dropPlan($area);

        $skipped = $plan->flagged();
        $this->events->imported($area, $result, $this->actor(), $wasEmpty);

        $this->draft->holdOutcome($area, \sprintf(
            '%d zone%s added',
            $result->count(),
            1 === $result->count() ? '' : 's',
        ), array_values(array_filter([
            0 === \count($skipped) + $result->skippedCount() ? '' : \sprintf('%d skipped', \count($skipped) + $result->skippedCount()),
            \sprintf('from %s', $plan->fileName),
            'the history gained its line',
        ])));

        return $this->backToTheSection($area);
    }

    private function backToTheSection(AreaOfInterest $area): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate(
            ZoneConfigureController::ROUTE,
            ['uuid' => $area->getUuidString()],
        ));
    }

    private function denyUnlessTokenValid(Request $request): void
    {
        $submitted = $request->request->get('_token');
        if (!\is_string($submitted) || !$this->csrf->isTokenValid(new CsrfToken(self::TOKEN, $submitted))) {
            throw new AccessDeniedException('The zone import form was submitted without a valid CSRF token.');
        }
    }

    /**
     * WHO DID IT, WHERE THAT IS KNOWN. Recorded as provenance rather than used
     * for anything, so a console importer with no session is not a problem and
     * the identifier survives the account being removed.
     */
    private function actor(): ?string
    {
        $user = $this->tokens?->getToken()?->getUser();

        return null === $user ? null : $user->getUserIdentifier();
    }
}
