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

namespace Uhifadhi\Bundle\TeamBundle\Security;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Contracts\Entity\AreaInterface;

/**
 * WHAT THE SIGNED-IN ADMINISTRATOR'S REACH IS — the read side of area-scoped
 * `team.manage` (DECISIONS §5.6, docs/area-scoped-authority.md §7.6).
 *
 * The voter answers "may this person do X here?" for a single permission. This
 * answers the coarser structural question the department writes need: is the
 * administrator UNBOUNDED (a tier, or an org-level team.manage holder — able to
 * mint org departments, change scope, touch any area), or confined to ONE area
 * (an area-X admin, who may manage only area-level departments in X)?
 *
 * The ruling it enforces: an area-X `team.manage` holder MAY create, rename and
 * deactivate area-level departments in X; may NOT create org-level departments,
 * change any department's scope, or touch org-level or other areas' departments.
 * Any of the forbidden acts widens power past the admin's own boundary — minting
 * an org department, promoting to org, reaching another area — which is
 * escalation. This service is where "past their boundary" is computed; the
 * controller is where it is refused.
 *
 * IT READS THE SAME DERIVED AUTHORITY-AREA THE VOTER DOES —
 * `actor.position.department.scope`, org-level (null) meaning every area — so the
 * two can never disagree about where an administrator's authority reaches.
 */
final readonly class AreaAuthority
{
    public function __construct(
        private TokenStorageInterface $tokens,
    ) {
    }

    public function actor(): ?User
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof User ? $user : null;
    }

    /**
     * UNBOUNDED — reaches every area. A tier (Super Admin / Admin, which bypass
     * area-scoping) or an org-level team.manage holder (department scope null).
     * These are the only administrators who may mint an org-level department,
     * change a scope, or manage a department outside a single area.
     */
    public function isUnbounded(): bool
    {
        $actor = $this->actor();
        if (null === $actor) {
            return false;
        }

        if ($actor->getTeamRole()->canManageContent()) {
            return true;
        }

        // An org-level position (department scope null) is org-wide authority.
        return null === $actor->getDepartment()?->getArea();
    }

    /**
     * The one area a bounded administrator is confined to, or null when they are
     * unbounded (or not signed in).
     */
    public function authorityArea(): ?AreaInterface
    {
        return $this->isUnbounded() ? null : $this->actor()?->getDepartment()?->getArea();
    }

    /**
     * Whether the administrator's authority reaches the given area. Unbounded
     * reaches everywhere; a bounded one reaches only its own area, and never the
     * org-level bucket (a null area), because managing an org-level department is
     * an unbounded act.
     */
    public function covers(?AreaInterface $area): bool
    {
        if ($this->isUnbounded()) {
            return true;
        }

        $authority = $this->authorityArea();
        if (null === $authority || null === $area) {
            return false;
        }

        $authorityUuid = $authority->getUuidString();
        $areaUuid = $area->getUuidString();
        if (null !== $authorityUuid && null !== $areaUuid) {
            return $authorityUuid === $areaUuid;
        }

        $authorityId = $authority->getId();

        return null !== $authorityId && $authorityId === $area->getId();
    }

    /**
     * Whether the administrator's authority reaches a POSITION — the unit a
     * person is assigned to (§5.6(a), the person-assignment half). Unbounded
     * reaches every position; a bounded (area-X) administrator reaches only a
     * position filed under an area-level department confined to their own area.
     *
     * A position under an org-level department, under another area's, or filed
     * under NO department at all (a loose position, which has no scope to speak
     * of) is beyond a bounded administrator — assigning a person into it, or
     * moving one out of it, would reach past their boundary.
     */
    public function reaches(Position $position): bool
    {
        return $this->reachesDepartment($position->getDepartment());
    }

    /**
     * Whether the administrator's authority reaches a DEPARTMENT — the unit a
     * position is filed under (§5.6(b), the position-create/rename half).
     * Unbounded reaches every department; a bounded (area-X) administrator
     * reaches only an area-level department confined to their own area.
     *
     * An org-level department, another area's, or NO department at all (a loose
     * position, which has no scope to speak of) is beyond a bounded
     * administrator — creating a position under it, or renaming one filed under
     * it, would file work past their boundary.
     */
    public function reachesDepartment(?Department $department): bool
    {
        if ($this->isUnbounded()) {
            return true;
        }

        return null !== $department
            && $department->isAreaLevel()
            && $this->covers($department->getArea());
    }

    /**
     * THE PERMISSIONS THIS ADMINISTRATOR MAY CONFER on a position (§5.6(c), the
     * no-escalation half). `null` means UNBOUNDED — a tier or org-level holder,
     * who may grant anything the catalogue offers.
     *
     * A bounded (area-X) administrator may grant only what their OWN position
     * holds — granting a permission they do not themselves have widens power past
     * their boundary — and NEVER `team.manage`, even though they hold it:
     * conferring team administration mints another administrator, an org-wide act
     * reserved to the unbounded. So the fence the area-admin design draws ("you
     * can grant only what your own position holds") is this list, and everything
     * outside it is drawn disabled and refused server-side.
     *
     * @return list<string>|null the grantable values, or null when unbounded
     */
    public function grantablePermissions(): ?array
    {
        if ($this->isUnbounded()) {
            return null;
        }

        $held = $this->actor()?->getPosition()?->getPermissionValues() ?? [];

        return array_values(array_filter(
            $held,
            static fn (string $value): bool => PermissionEnum::TeamManage->value !== $value,
        ));
    }

    /**
     * Whether this administrator may confer a single permission — the per-row
     * question the matrix asks to decide whether a box is enabled, and the
     * controller asks to refuse a crafted grant past the boundary.
     */
    public function mayGrant(string $permission): bool
    {
        $grantable = $this->grantablePermissions();

        return null === $grantable || \in_array($permission, $grantable, true);
    }

    /**
     * Narrow a grouped-position picker to what this administrator may ASSIGN a
     * person to (§5.6(a)) — every position for an unbounded administrator, only
     * their own area's for a bounded one. A group left with no reachable
     * position drops out entirely, so the picker offers only real targets — the
     * confined reassignment control the area-admin design draws, whose list holds
     * only the administrator's own area's positions.
     *
     * @param array<string, list<Position>> $grouped department name => positions
     *
     * @return array<string, list<Position>>
     */
    public function assignable(array $grouped): array
    {
        if ($this->isUnbounded()) {
            return $grouped;
        }

        $filtered = [];
        foreach ($grouped as $department => $positions) {
            $kept = array_values(array_filter($positions, fn (Position $position): bool => $this->reaches($position)));
            if ([] !== $kept) {
                $filtered[$department] = $kept;
            }
        }

        return $filtered;
    }
}
