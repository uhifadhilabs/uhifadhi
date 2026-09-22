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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Uhifadhi\Bundle\TeamBundle\Access\TeamConcerns;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;
use Uhifadhi\Contracts\Access\Verb;

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
 * ONE SCREEN, TEAM SETTINGS (ruled 2026-09-22): it states the installation's
 * policies and carries the one write this surface has — adding a position.
 */
final readonly class TeamConfigureController
{
    /** The section's settings — the last entry in the strip, and the house's rank. */
    public const string SETTINGS = 'team_configure';

    /** The words a position is written with. */
    public function __construct(
        private Environment $twig,
        private UserRepository $users,
        private DepartmentRepository $departments,
        private CsrfTokenManagerInterface $csrf,
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
    #[IsGranted(PositionController::CONFIGURE)]
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
            // WHO ADMINISTERS THE TEAM, in the terms the model now uses:
            // somebody whose position confers the positions register. It read
            // the flat `team.manage` until administering the team stopped
            // being a seventh verb and became these concerns with configure
            // on them.
            if (true === $person->getPosition()?->grantsVerbOn(TeamConcerns::POSITIONS, Verb::Configure)) {
                ++$mayAdminister;
            }
        }

        return new Response($this->twig->render('@Team/team/configure.html.twig', [
            'people' => \count($people),
            'mayAdminister' => $mayAdminister,
            'byTier' => $byTier,
            'tiers' => TeamRoleEnum::cases(),
            'departments' => \count($this->departments->findAllActiveOrdered()),
            // THE ADD-A-POSITION CARD posts to the positions register's own
            // write, so it carries that write's token, not the vocabulary's.
            'csrfToken' => $this->csrf->getToken(PositionController::CSRF_ID)->getValue(),
        ]));
    }

    /*
     * WHAT A POSITION MAY BE CALLED — titles, and the departments already
     * using each word.
     *
     * THE SAME WORD TWICE IS LEGAL and is the case this vocabulary exists to
     * allow: a position's name is unique inside its department and nowhere
     * else, so `Analyst` in Ecology and `Analyst` in Protection Service are
     * two different jobs. Nothing on this screen may merge them.
     */
}
