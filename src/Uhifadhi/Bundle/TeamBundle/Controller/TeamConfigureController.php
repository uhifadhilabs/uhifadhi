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

namespace Uhifadhi\Bundle\TeamBundle\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Exception\DuplicatePositionTitleException;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\PositionTitleRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;
use Uhifadhi\Bundle\TeamBundle\Service\PositionTitleService;
use Uhifadhi\Bundle\TeamBundle\Service\PositionVocabulary;

/**
 * HOW THE TEAM SECTION IS SET UP — its two configure screens of its own.
 *
 * AN ORG-LEVEL SECTION'S CONFIGURE SCREENS ARE ITS OWN ROUTES. The shell's
 * configure page renders a section into an AREA's frame, and this section has
 * no area in its address; so the screens are addresses here, declared to the
 * shell through the same sections contract, and the shell builds the strip
 * from them.
 *
 * SETTINGS IS READ-ONLY, AND THAT IS THE DESIGN. Every line on it is a fact
 * about the model or about who may do what. A control that let one of them be
 * changed would be a second place the rule lived; the rule is in the code, and
 * this page is where you read it without opening the code.
 *
 * A SCREEN DOES NOT NAME AN ACTION THAT DOES NOT EXIST. There is no
 * "deleting an account" row and no recycle bin: accounts are deactivated, kept
 * and listed, and naming the absent action would only teach a reader to look
 * for it.
 *
 * POSITIONS VOCABULARY IS THE ONE THAT WRITES, and it writes exactly one
 * thing: the title a position may be called. What a position may DO is the
 * matrix, on Positions, and nothing here grants anything.
 */
final readonly class TeamConfigureController
{
    public const string CSRF_ID = 'team_position_title';

    /** The section's settings — the last entry in the strip, and the house's rank. */
    public const string SETTINGS = 'team_configure';

    /** The words a position is written with. */
    public const string VOCABULARY = 'team_configure_positions';

    public const string TITLE_CREATE = 'team_position_title_create';

    public const string TITLE_RENAME = 'team_position_title_rename';

    public function __construct(
        private Environment $twig,
        private UserRepository $users,
        private DepartmentRepository $departments,
        private PositionTitleRepository $titles,
        private PositionTitleService $titleWrites,
        private PositionVocabulary $vocabulary,
        private CsrfTokenManagerInterface $csrf,
        private UrlGeneratorInterface $router,
    ) {
    }

    /**
     * HOW PEOPLE GET IN, WHAT HAPPENS TO AN ACCOUNT, AND WHO MAY CHANGE
     * EITHER.
     *
     * THE TWO FIGURES ON IT ARE ANSWERS, not readings of a period: "who may
     * administer" is an access question, and a page that stated the rule
     * without saying how many people it currently names would be a page you
     * still had to leave to act on.
     */
    #[Route('/team/configure', name: self::SETTINGS, defaults: TeamController::SURFACE, methods: ['GET'])]
    #[IsGranted(PermissionEnum::TeamManage->value)]
    public function settings(): Response
    {
        $people = $this->users->findAllByName();
        $byTier = 0;
        $mayAdminister = 0;
        foreach ($people as $person) {
            if (!$person->isActive()) {
                continue;
            }
            if ($person->getTeamRole()->canManageContent()) {
                ++$byTier;
                ++$mayAdminister;
                continue;
            }
            if (true === $person->getPosition()?->hasPermission(PermissionEnum::TeamManage)) {
                ++$mayAdminister;
            }
        }

        return new Response($this->twig->render('@Team/team/configure.html.twig', [
            'people' => \count($people),
            'mayAdminister' => $mayAdminister,
            'byTier' => $byTier,
            'tiers' => TeamRoleEnum::cases(),
            'titles' => \count($this->titles->findAllOrdered()),
            'departments' => \count($this->departments->findAllActiveOrdered()),
        ]));
    }

    /**
     * WHAT A POSITION MAY BE CALLED — titles, and the departments already
     * using each word.
     *
     * THE SAME WORD TWICE IS LEGAL and is the case this vocabulary exists to
     * allow: a position's name is unique inside its department and nowhere
     * else, so `Analyst` in Ecology and `Analyst` in Protection Service are
     * two different jobs. Nothing on this screen may merge them.
     */
    #[Route('/team/configure/positions', name: self::VOCABULARY, defaults: TeamController::SURFACE, methods: ['GET'])]
    #[IsGranted(PermissionEnum::TeamManage->value)]
    public function vocabulary(): Response
    {
        return new Response($this->twig->render('@Team/team/configure_positions.html.twig', [
            'titles' => $this->titles->findAllOrdered(),
            // A WORD IN TWO DEPARTMENTS IS TWO JOBS, and the screen names
            // which words those are rather than leaving a reader to spot them.
            ...$this->vocabulary->read(),
            'csrfToken' => $this->csrf->getToken(self::CSRF_ID)->getValue(),
        ]));
    }

    #[Route('/team/configure/positions/titles', name: self::TITLE_CREATE, methods: ['POST'])]
    #[IsGranted(PermissionEnum::TeamManage->value)]
    public function createTitle(Request $request): RedirectResponse
    {
        $this->guard($request);

        $name = trim((string) $request->request->get('name'));
        if ('' === $name) {
            return $this->back($request, 'A title needs a name.');
        }

        try {
            $this->titleWrites->create($name, $request->request->getBoolean('leads'));
        } catch (DuplicatePositionTitleException $clash) {
            return $this->back($request, $clash->getMessage());
        }

        return $this->back($request, \sprintf('“%s” is a position title.', $name), 'success');
    }

    #[Route('/team/configure/positions/titles/{uuid}/rename', name: self::TITLE_RENAME, requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(PermissionEnum::TeamManage->value)]
    public function renameTitle(Request $request, string $uuid): RedirectResponse
    {
        $this->guard($request);

        $title = $this->titles->findOneByUuid(Uuid::fromString($uuid));
        if (null === $title) {
            throw new NotFoundHttpException('No title by that identifier.');
        }

        $name = trim((string) $request->request->get('name'));
        if ('' === $name) {
            return $this->back($request, 'A title needs a name.');
        }

        try {
            $this->titleWrites->rename($title, $name, $request->request->getBoolean('leads'));
        } catch (DuplicatePositionTitleException $clash) {
            return $this->back($request, $clash->getMessage());
        }

        return $this->back($request, \sprintf('The title is called “%s”.', $name), 'success');
    }

    private function guard(Request $request): void
    {
        if (!$this->csrf->isTokenValid(new CsrfToken(self::CSRF_ID, (string) $request->request->get('_token')))) {
            throw new NotFoundHttpException('That form is stale.');
        }
    }

    private function back(Request $request, string $message, string $tone = 'error'): RedirectResponse
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($tone, $message);
        }

        return new RedirectResponse($this->router->generate(self::VOCABULARY));
    }
}
