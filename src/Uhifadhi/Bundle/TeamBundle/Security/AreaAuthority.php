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
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Contracts\Entity\AreaInterface;

/**
 * WHAT THE SIGNED-IN ADMINISTRATOR'S REACH IS — the read side of area-scoped
 * `team.manage`.
 *
 * The voter answers "may this person do X here?" for a single permission. This
 * answers the coarser structural question the department writes need: is the
 * administrator UNBOUNDED (a tier, or somebody placed across the whole
 * organization — able to mint org departments, change scope, touch any area),
 * or confined to the areas they were placed at?
 *
 * The ruling it enforces: an area-X administrator MAY create, rename and
 * deactivate area-level departments in X; may NOT create org-level departments,
 * change any department's scope, or touch org-level or other areas' departments.
 * Any of the forbidden acts widens power past the admin's own boundary — minting
 * an org department, promoting to org, reaching another area — which is
 * escalation. This service is where "past their boundary" is computed; the
 * controller is where it is refused.
 *
 * IT READS THE PLACEMENT, WHICH IS WHERE REACH NOW LIVES. It used to derive
 * the boundary from `actor.position.department.area`, a chain that made
 * somebody's reach a property of their job title; the ruled model records it
 * against the person. Unplaced is not unbounded — it reaches nothing, because
 * the model fails closed.
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
     * area-scoping) or somebody placed across the whole organization. These are
     * the only administrators who may mint an org-level department, change a
     * scope, or manage a department outside a single area.
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

        return $actor->getPlacement()?->isWholeOrganization() ?? false;
    }

    /**
     * THE AREAS A BOUNDED ADMINISTRATOR IS CONFINED TO, or null when they are
     * unbounded. An empty list is somebody placed nowhere, and reaches nothing.
     *
     * @return list<AreaInterface>|null
     */
    public function authorityAreas(): ?array
    {
        if ($this->isUnbounded()) {
            return null;
        }

        return $this->actor()?->getPlacement()?->getAreas() ?? [];
    }

    /**
     * Whether the administrator's authority reaches the given area. Unbounded
     * reaches everywhere; a bounded one reaches only the areas it was placed at,
     * and never the org-level bucket (a null area), because managing an
     * org-level department is an unbounded act.
     */
    public function covers(?AreaInterface $area): bool
    {
        if ($this->isUnbounded()) {
            return true;
        }

        if (null === $area) {
            return false;
        }

        $placement = $this->actor()?->getPlacement();

        return null !== $placement && !$placement->reachesNothing() && $placement->coversArea($area);
    }

    /**
     * Whether the administrator's authority reaches a DEPARTMENT — the unit a
     * screen files work under. Unbounded reaches every department; a bounded
     * (area-X) administrator reaches only an area-level department confined to
     * an area they were placed at, and only one their own placement names.
     *
     * An org-level department, another area's, or none at all is beyond a
     * bounded administrator — creating work under it would file that work past
     * their boundary.
     */
    public function reachesDepartment(?Department $department): bool
    {
        if ($this->isUnbounded()) {
            return true;
        }

        if (null === $department || !$department->isAreaLevel()) {
            return false;
        }

        return $this->covers($department->getArea())
            && ($this->actor()?->getPlacement()?->coversDepartment($department) ?? false);
    }

    /**
     * Whether the administrator's authority reaches a PERSON — the question
     * assignment asks, now that a position carries no ground of its own.
     *
     * A POSITION IS NO LONGER THE UNIT OF REACH. It used to be: a position sat
     * in a department, the department sat in an area, and "may I assign
     * somebody to this position" was answerable from the position alone. A
     * position belongs to nobody now, so the question is about the PERSON being
     * moved: a bounded administrator may work on somebody whose ground lies
     * inside their own, and nobody else.
     */
    public function reachesPerson(User $person): bool
    {
        if ($this->isUnbounded()) {
            return true;
        }

        $theirs = $person->getPlacement();
        if (null === $theirs || $theirs->isWholeOrganization()) {
            // Nobody bounded may work on somebody placed everywhere, and
            // somebody placed nowhere lies in no area to be reached in.
            return false;
        }

        foreach ($theirs->getAreas() ?? [] as $area) {
            if (!$this->covers($area)) {
                return false;
            }
        }

        return [] !== ($theirs->getAreas() ?? []);
    }

    /**
     * THE PERMISSIONS THIS ADMINISTRATOR MAY CONFER on a position (the
     * no-escalation half). `null` means UNBOUNDED — a tier or an
     * organization-wide holder, who may grant anything the catalogue offers.
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
}
