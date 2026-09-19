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

namespace Uhifadhi\Bundle\TeamBundle\Service;

use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Model\Permission;
use Uhifadhi\Bundle\TeamBundle\Model\PermissionGroup;
use Uhifadhi\Bundle\TeamBundle\Model\PermissionRow;
use Uhifadhi\Bundle\TeamBundle\Model\SectionFact;
use Uhifadhi\Bundle\TeamBundle\Model\TierRow;
use Uhifadhi\Bundle\TeamBundle\Repository\PositionRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;

/**
 * WHAT AUTHORITY EXISTS ON THIS INSTALLATION, AND WHO HOLDS IT.
 *
 * TWO THINGS DECIDE IT AND THERE IS NO THIRD. The TIER — three cases, two of
 * which stand above the matrix — and the PERMISSION a position carries. There
 * is no Role entity, none is proposed, and this service asks for none: it
 * reads the two that already decide and aggregates them, which is the whole of
 * what the tab needed that did not exist.
 *
 * IT WRITES NOTHING. The matrix is edited on Positions; a page that could
 * change a grant from the report of it would be a second write path for the
 * fact one screen already owns.
 *
 * TWO QUERIES, NOT ONE PER PERMISSION. Every figure below is counted in one
 * pass over the positions and one over the people, because "how many hold
 * `area.view`" asked per row is a query per row on the one page that lists
 * every row there is.
 */
final readonly class RolesBoard
{
    public function __construct(
        private PermissionCatalogue $catalogue,
        private PositionRepository $positions,
        private UserRepository $users,
    ) {
    }

    /**
     * @return array{
     *     facts: list<SectionFact>,
     *     tiers: list<TierRow>,
     *     groups: list<PermissionGroup>,
     *     permissions: int,
     *     core: int,
     *     fromModules: int,
     * }
     */
    public function read(): array
    {
        $permissions = $this->catalogue->all();
        $positions = $this->positions->findAllOrdered();
        $people = $this->users->findAllByName();

        $core = \count(array_filter($permissions, static fn (Permission $p): bool => $p->isCore()));

        return [
            'facts' => $this->facts($permissions, $positions, $people, $core),
            'tiers' => self::tiers($people),
            'groups' => $this->groups($permissions, $positions, $people),
            'permissions' => \count($permissions),
            'core' => $core,
            'fromModules' => \count($permissions) - $core,
        ];
    }

    /**
     * @param list<Permission> $permissions
     * @param list<Position>   $positions
     * @param list<User>       $people
     *
     * @return list<SectionFact>
     */
    private function facts(array $permissions, array $positions, array $people, int $core): array
    {
        $modules = [];
        foreach ($permissions as $permission) {
            if (null !== $permission->source) {
                $modules[$permission->source] = true;
            }
        }
        $installed = \count($this->catalogue->moduleNames());

        $byTier = $byGrant = 0;
        foreach ($people as $person) {
            if (!$person->isActive()) {
                continue;
            }
            if ($person->getTeamRole()->canManageContent()) {
                ++$byTier;
                continue;
            }
            if (true === $person->getPosition()?->hasPermission(PermissionEnum::TeamManage)) {
                ++$byGrant;
            }
        }

        return [
            new SectionFact('Tiers', (string) \count(TeamRoleEnum::cases()), '2 are escape hatches'),
            new SectionFact('Core permissions', (string) $core, 'the host’s own'),
            new SectionFact(
                'Module permissions',
                (string) (\count($permissions) - $core),
                \sprintf('from %d of %d modules', \count($modules), $installed),
            ),
            new SectionFact('Positions', (string) \count($positions), 'each a permission set'),
            new SectionFact(
                'May administer',
                (string) ($byTier + $byGrant),
                \sprintf('of %d · %d by tier, %d by grant', \count($people), $byTier, $byGrant),
            ),
        ];
    }

    /**
     * THE THREE TIERS, WITH THE PEOPLE IN THEM NAMED WHERE NAMING IS USEFUL.
     *
     * A TIER SMALL ENOUGH TO NAME IS NAMED, because "there is one Super Admin"
     * is a fact somebody must be able to act on — and if that account is lost
     * there is no second owner and no break-glass. A tier holding the rest of
     * the installation is not a list; Staff says so in the way a reader
     * already thinks of it, and any other crowded tier says how many.
     *
     * @param list<User> $people
     *
     * @return list<TierRow>
     */
    private static function tiers(array $people): array
    {
        /** How many names a tier may state before a list stops being a reading. */
        $nameable = 3;

        $rows = [];
        foreach (TeamRoleEnum::cases() as $tier) {
            $names = [];
            foreach ($people as $person) {
                if ($tier === $person->getTeamRole()) {
                    $names[] = $person->getFullName();
                }
            }

            $rows[] = new TierRow(
                tier: $tier,
                people: \count($names),
                who: match (true) {
                    [] === $names => 'nobody',
                    \count($names) <= $nameable => implode(', ', $names),
                    TeamRoleEnum::Staff === $tier => 'everybody else',
                    default => \sprintf('%d people', \count($names)),
                },
                meaning: $tier->description(),
            );
        }

        return $rows;
    }

    /**
     * EVERY PERMISSION THERE IS, under the band that says where it came from:
     * the host's own umbrellas first, then each module that declared one, then
     * the installed modules that declare nothing.
     *
     * @param list<Permission> $permissions
     * @param list<Position>   $positions
     * @param list<User>       $people
     *
     * @return list<PermissionGroup>
     */
    private function groups(array $permissions, array $positions, array $people): array
    {
        $holders = [];
        foreach ($people as $person) {
            if (!$person->isActive()) {
                continue;
            }
            foreach ($person->getPosition()?->getPermissionValues() ?? [] as $value) {
                $holders[$value] = ($holders[$value] ?? 0) + 1;
            }
        }

        $carriers = [];
        foreach ($positions as $position) {
            foreach ($position->getPermissionValues() as $value) {
                $carriers[$value] = ($carriers[$value] ?? 0) + 1;
            }
        }

        $moduleNames = $this->catalogue->moduleNames();
        $banded = [];
        foreach ($permissions as $permission) {
            // THE BAND IS THE DECLARER'S, not the word the permission happens
            // to start with: two modules may both call an umbrella "Records",
            // and a band that merged them would say the host declared both.
            $key = $permission->isCore() ? 'host:'.$permission->umbrella : 'module:'.$permission->source;
            $banded[$key] ??= new PermissionGroup(
                heading: $permission->isCore()
                    ? $permission->umbrella
                    : ($moduleNames[(string) $permission->source] ?? (string) $permission->source),
                source: $permission->isCore() ? 'the host' : (string) $permission->source,
            );

            $banded[$key] = new PermissionGroup(
                heading: $banded[$key]->heading,
                source: $banded[$key]->source,
                rows: [...$banded[$key]->rows, new PermissionRow(
                    label: $permission->label(),
                    value: $permission->value,
                    description: $permission->description,
                    positions: $carriers[$permission->value] ?? 0,
                    people: $holders[$permission->value] ?? 0,
                )],
            );
        }

        $groups = array_values($banded);

        // AN INSTALLED MODULE THAT DECLARES NOTHING IS DRAWN, so its absence
        // cannot be misread as "not installed".
        foreach ($this->catalogue->silentModules() as $slug) {
            $groups[] = new PermissionGroup($moduleNames[$slug] ?? $slug, $slug);
        }

        return $groups;
    }
}
