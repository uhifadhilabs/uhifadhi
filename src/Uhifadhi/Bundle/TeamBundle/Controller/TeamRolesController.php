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
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Service\RolesBoard;

/**
 * THE ROLES TAB — what authority exists on this installation, and who holds
 * it.
 *
 * THERE IS NO ROLE ENTITY AND THE TAB ASSUMES NONE. Two things decide
 * authority here: the TIER, which grants nothing except for the two cases that
 * grant everything, and the PERMISSION a position carries. The tab reads both
 * and invents no third.
 *
 * IT WRITES NOTHING. The matrix is edited on Positions.
 */
final readonly class TeamRolesController
{
    /** The section's fifth tab. */
    public const string ROLES = 'team_roles';

    public function __construct(
        private Environment $twig,
        private RolesBoard $board,
    ) {
    }

    #[Route('/team/roles', name: self::ROLES, defaults: TeamController::SURFACE, methods: ['GET'])]
    #[IsGranted(PermissionEnum::TeamManage->value)]
    public function index(): Response
    {
        return new Response($this->twig->render('@Team/team/roles.html.twig', $this->board->read()));
    }
}
