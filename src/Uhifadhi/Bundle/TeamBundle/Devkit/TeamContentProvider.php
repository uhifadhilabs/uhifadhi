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
use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;
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
 * IT SEEDS ONCE. An installation that already answers to any of the addresses
 * below is left exactly as it is: re-running a demo seeder is a developer
 * repeating a command, not an instruction to enter this organisation twice —
 * and a department name is unique org-wide, so the second attempt is refused
 * rather than duplicated, taking every slice seeded after this one down with it.
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
    /**
     * THE SIX ADDRESSES THIS SEEDS, written once and read twice: the roster
     * below is built from them, and whether any of them is already answered for
     * is how a second run recognises its own first one.
     *
     * The accounts are the detector rather than the departments, because an
     * account is what the whole slice ends in: an installation holding one of
     * these got here by running this, and the departments and positions in
     * front of it are already in place.
     *
     * @var array<string, string>
     */
    private const array ACCOUNTS = [
        'coordinator' => 'amara.okonkwo@example.test',
        'head_ranger' => 'desta.haile@example.test',
        'ranger' => 'kofi.mensah@example.test',
        'second_ranger' => 'nadia.sow@example.test',
        'analyst' => 'thabo.ndlovu@example.test',
        'unseated' => 'yara.benali@example.test',
    ];

    public function __construct(
        private UserService $accounts,
        private PositionService $positions,
        private DepartmentService $departments,
        private UserRepository $roster,
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
        if ($this->alreadySeeded()) {
            return;
        }

        $protection = $this->departments->create('Protection Service', null);
        $ecology = $this->departments->create('Ecology', null);
        $operations = $this->departments->create('Operations', null);

        $coordinator = $this->positions->create('Coordinator', $operations);
        $this->positions->setPermissions($coordinator, [PermissionEnum::TeamManage->value]);

        $headRanger = $this->positions->create('Head Ranger', $protection);
        $ranger = $this->positions->create('Ranger', $protection);
        $analyst = $this->positions->create('Analyst', $ecology);

        $this->person(self::ACCOUNTS['coordinator'], 'Amara', 'Okonkwo', TeamRoleEnum::SuperAdmin, $coordinator);
        $this->person(self::ACCOUNTS['head_ranger'], 'Desta', 'Haile', TeamRoleEnum::Admin, $headRanger);
        $this->person(self::ACCOUNTS['ranger'], 'Kofi', 'Mensah', TeamRoleEnum::Staff, $ranger);
        $this->person(self::ACCOUNTS['second_ranger'], 'Nadia', 'Sow', TeamRoleEnum::Staff, $ranger);
        $this->person(self::ACCOUNTS['analyst'], 'Thabo', 'Ndlovu', TeamRoleEnum::Staff, $analyst);

        // SOMEBODY WITH NO POSITION, because that is a real state the roster has
        // to draw: verified, able to sign in, and able to do nothing at all.
        $this->person(self::ACCOUNTS['unseated'], 'Yara', 'Benali', TeamRoleEnum::Staff, null);
    }

    /**
     * ANY ONE OF THE ADDRESSES BEING ANSWERED IS ENOUGH. A run that stopped
     * part-way through left some of them and not others, and the honest reading
     * of that is still "this has been here" — the departments it would start
     * with are the ones that refuse a second write.
     */
    private function alreadySeeded(): bool
    {
        foreach (self::ACCOUNTS as $email) {
            if (null !== $this->roster->findOneByEmail($email)) {
                return true;
            }
        }

        return false;
    }

    private function person(string $email, string $firstName, string $lastName, TeamRoleEnum $tier, ?Position $position): void
    {
        $this->accounts->create($email, $firstName, $lastName, bin2hex(random_bytes(24)), $tier, $position);
    }
}
