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

namespace Uhifadhi\Bundle\TeamBundle\Devkit;

use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Service\DepartmentService;
use Uhifadhi\Bundle\TeamBundle\Service\PositionService;
use Uhifadhi\Bundle\TeamBundle\Service\UserService;
use Uhifadhi\Contracts\Devkit\ContentProviderInterface;

/**
 * A SMALL ORGANISATION TO LOOK AT — three departments, four positions and six
 * people, so a developer's first screen is a populated one.
 *
 * IT GOES THROUGH THE SAME SERVICES THE SCREENS DO, and that is the whole
 * discipline of it. Demo content written straight to the tables is demo content
 * that can be shaped in ways the product cannot produce — a position holding a
 * permission no module declares, a department in a state no form can reach —
 * and every such row is a bug report about a screen that is working correctly.
 * Everything below is reachable by somebody clicking.
 *
 * IT IS COLLECTED, NOT RUN. devkit installs through `require-dev`; in a
 * production build nothing collects this and it is an ordinary service nobody
 * ever asks anything of.
 *
 * NOBODY HERE HAS AN AREA. This bundle knows about people and never about
 * areas, so the departments it seeds are organisation-wide and it depends on no
 * other content. A demo installation that also has areas confines them from the
 * screen, which is the same act an operator would perform.
 *
 * THE PASSWORDS ARE GENERATED AND NEVER PRINTED. Demo accounts are still
 * accounts: one shipped with a known password is a door left open on whatever
 * machine the demo was run on. Somebody who needs to sign in as one of these
 * resets it, exactly as they would for a colleague who has forgotten theirs.
 *
 * @see ContentProviderInterface
 */
final readonly class TeamContentProvider implements ContentProviderInterface
{
    public function __construct(
        private UserService $accounts,
        private PositionService $positions,
        private DepartmentService $departments,
    ) {
    }

    public function key(): string
    {
        return 'team';
    }

    public function label(): string
    {
        return 'Team';
    }

    public function description(): string
    {
        return 'A small organisation: departments, the positions filed under them, and the people who hold them.';
    }

    public function dependsOn(): array
    {
        return [];
    }

    public function load(): void
    {
        $protection = $this->departments->create('Protection Service', null);
        $ecology = $this->departments->create('Ecology', null);
        $operations = $this->departments->create('Operations', null);

        $coordinator = $this->positions->create('Coordinator', $operations);
        $this->positions->setPermissions($coordinator, [PermissionEnum::TeamManage->value]);

        $headRanger = $this->positions->create('Head Ranger', $protection);
        $ranger = $this->positions->create('Ranger', $protection);
        $analyst = $this->positions->create('Analyst', $ecology);

        $this->person('amara.okonkwo@example.test', 'Amara', 'Okonkwo', TeamRoleEnum::SuperAdmin, $coordinator);
        $this->person('desta.haile@example.test', 'Desta', 'Haile', TeamRoleEnum::Admin, $headRanger);
        $this->person('kofi.mensah@example.test', 'Kofi', 'Mensah', TeamRoleEnum::Staff, $ranger);
        $this->person('nadia.sow@example.test', 'Nadia', 'Sow', TeamRoleEnum::Staff, $ranger);
        $this->person('thabo.ndlovu@example.test', 'Thabo', 'Ndlovu', TeamRoleEnum::Staff, $analyst);

        // SOMEBODY WITH NO POSITION, because that is a real state the roster has
        // to draw: verified, able to sign in, and able to do nothing at all.
        $this->person('yara.benali@example.test', 'Yara', 'Benali', TeamRoleEnum::Staff, null);
    }

    private function person(string $email, string $firstName, string $lastName, TeamRoleEnum $tier, ?Position $position): void
    {
        $this->accounts->create($email, $firstName, $lastName, bin2hex(random_bytes(24)), $tier, $position);
    }
}
