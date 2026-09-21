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
use Uhifadhi\Bundle\AreaBundle\Entity\Zone;
use Uhifadhi\Bundle\AreaBundle\Exception\ZoneImportException;
use Uhifadhi\Bundle\AreaBundle\Exception\ZoneNameException;
use Uhifadhi\Bundle\AreaBundle\Exception\ZoneOverlapException;
use Uhifadhi\Bundle\AreaBundle\Model\Actor;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneImportDraftStore;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneImportService;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneService;

/**
 * THE WRITES THAT ARE NOT AN IMPORT: the three that change ONE zone — rename
 * it, redraw its ring, remove it — and the one that removes the whole set.
 *
 * A RENAME IS PER ZONE AND TOUCHES NO GEOMETRY. It is the only edit a zone
 * needs no file and no map for, which is why it sits on the card itself and
 * posts on its own.
 *
 * REMOVING THE SET IS ONE EXPLICIT ACT WITH THE COUNT IN IT, never a loop of
 * deletions, and the confirmation offers the export first — nothing keeps
 * superseded geometry, so a set nobody downloaded is a set nobody can put back.
 * Deletion orphans nothing: no record, no person and no figure is deleted with
 * it, and ground that stops being zoned is simply unzoned, which is legal.
 */
final readonly class ZoneEditController
{
    public const string RENAME_TOKEN = 'area_zone_rename';
    public const string RING_TOKEN = 'area_zone_ring';
    public const string REMOVE_TOKEN = 'area_zone_remove';
    public const string CLEAR_TOKEN = 'area_zones_clear';

    public function __construct(
        private ZoneService $zones,
        private ZoneImportService $imports,
        private ZoneImportDraftStore $draft,
        private CsrfTokenManagerInterface $csrf,
        private UrlGeneratorInterface $urls,
        private ?TokenStorageInterface $tokens = null,
    ) {
    }

    #[Route('/areas/{uuid}/zones/{zone}/rename', name: 'area_zone_rename', requirements: ['uuid' => Requirement::UUID, 'zone' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted('zones.configure', subject: 'area')]
    public function rename(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
        #[MapEntity(mapping: ['zone' => 'uuid'])] Zone $zone,
    ): Response {
        $this->denyUnlessTokenValid($request, self::RENAME_TOKEN);
        $this->denyUnlessTheZoneIsThisAreas($area, $zone);

        $was = (string) $zone->getName();
        $name = $request->request->get('name');

        try {
            $this->zones->rename($zone, \is_string($name) ? $name : '', $this->actor());
        } catch (ZoneNameException $e) {
            $this->draft->holdRefusal($area, $was, $e->getMessage());

            return $this->backToTheSection($area);
        }

        return $this->backToTheSection($area);
    }

    /**
     * THE RING, REPLACED FROM A SINGLE-FEATURE FILE — the same validation path
     * an import runs, so a ring that arrives one at a time is held to the
     * invariant a ring that arrives eleven at a time is held to.
     *
     * THE EARLIER RING IS NOT KEPT. A zone set has one live state, and a second
     * copy of a ring that is no longer the truth is a second truth; the export
     * is how a state is kept.
     */
    #[Route('/areas/{uuid}/zones/{zone}/ring', name: 'area_zone_ring', requirements: ['uuid' => Requirement::UUID, 'zone' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted('zones.configure', subject: 'area')]
    public function ring(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
        #[MapEntity(mapping: ['zone' => 'uuid'])] Zone $zone,
    ): Response {
        $this->denyUnlessTokenValid($request, self::RING_TOKEN);
        $this->denyUnlessTheZoneIsThisAreas($area, $zone);

        $name = (string) $zone->getName();
        $file = $request->files->get(ZoneImportController::FILE_FIELD);
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $this->draft->holdRefusal($area, $name, 'the file did not reach the server, or no file was chosen');

            return $this->backToTheSection($area);
        }

        try {
            $this->zones->replaceGeometry($zone, $this->imports->ringFor($zone, $file, $file->getClientOriginalName()), $this->actor());
        } catch (ZoneImportException|ZoneOverlapException $e) {
            $this->draft->holdRefusal($area, $file->getClientOriginalName(), $e->getMessage());

            return $this->backToTheSection($area);
        }

        return $this->backToTheSection($area);
    }

    /**
     * ONE ZONE, GONE. Its ground becomes unzoned, which is legal: an area is
     * often only partly zoned. No station, person or record is deleted with it.
     */
    #[Route('/areas/{uuid}/zones/{zone}/remove', name: 'area_zone_remove', requirements: ['uuid' => Requirement::UUID, 'zone' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted('zones.delete', subject: 'area')]
    public function remove(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
        #[MapEntity(mapping: ['zone' => 'uuid'])] Zone $zone,
    ): Response {
        $this->denyUnlessTokenValid($request, self::REMOVE_TOKEN);
        $this->denyUnlessTheZoneIsThisAreas($area, $zone);

        $this->zones->remove($zone, $this->actor());

        return $this->backToTheSection($area);
    }

    #[Route('/areas/{uuid}/zones/clear', name: 'area_zones_clear', requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted('zones.delete', subject: 'area')]
    public function clear(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
    ): Response {
        $this->denyUnlessTokenValid($request, self::CLEAR_TOKEN);

        $removed = $this->zones->removeAll($area, $this->actor());

        $this->draft->dropPlan($area);
        $this->draft->holdOutcome($area, \sprintf(
            '%d zone%s removed',
            $removed,
            1 === $removed ? '' : 's',
        ), ['no station, person or record was deleted', 'the history gained its line']);

        return $this->backToTheSection($area);
    }

    private function backToTheSection(AreaOfInterest $area): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate(
            ZoneConfigureController::ROUTE,
            ['uuid' => $area->getUuidString()],
        ));
    }

    /**
     * A ZONE IS ADDRESSED INSIDE ITS AREA, so a uuid from one area on another
     * area's address is not a zone that happens to be elsewhere — it is a
     * request for something this page is not about.
     */
    private function denyUnlessTheZoneIsThisAreas(AreaOfInterest $area, Zone $zone): void
    {
        if ($zone->getArea()?->getId() !== $area->getId()) {
            throw new AccessDeniedException('That zone does not belong to this area.');
        }
    }

    private function denyUnlessTokenValid(Request $request, string $id): void
    {
        $submitted = $request->request->get('_token');
        if (!\is_string($submitted) || !$this->csrf->isTokenValid(new CsrfToken($id, $submitted))) {
            throw new AccessDeniedException('The form was submitted without a valid CSRF token.');
        }
    }

    private function actor(): ?string
    {
        return Actor::of($this->tokens?->getToken()?->getUser());
    }
}
