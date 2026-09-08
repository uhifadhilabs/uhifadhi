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

namespace Uhifadhi\Bundle\TeamBundle\Enum;

/**
 * The fixed catalogue of granular permissions a {@see \Uhifadhi\Bundle\TeamBundle\Entity\Position} can grant.
 * Each belongs to an umbrella (Areas, Modules, Team) with a specific action (View, Create, …).
 * An umbrella is the heading the matrix groups under and nothing more: holding a permission is
 * decided against the person by {@see \Uhifadhi\Bundle\TeamBundle\Security\PermissionVoter},
 * never by a coarse standing a path pattern could name.
 *
 * SEVEN, AND THE SEVENTH IS `team.manage`. Administering the team is a row an administrator can
 * see, count across the roster and take away, granted the one way every other capability is —
 * through a position. A standing beside the matrix rather than in it would be a second authority
 * system running alongside the first, and nobody can audit that.
 *
 * The Team umbrella carries exactly one row, and that is not an oversight: an umbrella is a
 * heading, and one row under one heading is a catalogue that reads.
 *
 * THERE IS NO INGESTION. This platform has no ingestion capability, and a permission that guards
 * nothing is a power an admin can assign over code that does not exist.
 *
 * EVERY PERMISSION CARRIES A SENTENCE ({@see description()}), the core seven exactly as a
 * module-declared one does ({@see \Uhifadhi\Contracts\ModulePermission}). The matrix prints
 * it under the name, because "Areas · Delete" says which words were chosen and not what ticking
 * the box hands over. A core row without one would be the single row on that page an
 * administrator cannot read, and the rule is that there are no such rows.
 *
 * Single-org: there is no party axis. An installation is one authority.
 */
enum PermissionEnum: string
{
    // Areas
    case AreaView = 'area.view';
    case AreaCreate = 'area.create';
    case AreaEdit = 'area.edit';
    case AreaDelete = 'area.delete';
    // Modules
    case ModuleView = 'module.view';
    case ModuleCreate = 'module.create';   // configure a module: settings + visualizations (composition is Admin-tier)
    // Team
    case TeamManage = 'team.manage';

    public function umbrella(): string
    {
        return $this->meta()[0];
    }

    public function action(): string
    {
        return $this->meta()[1];
    }

    /** One sentence saying what holding this lets a person do — printed under the name in the matrix. */
    public function description(): string
    {
        return $this->meta()[2];
    }

    public function label(): string
    {
        return $this->umbrella().' · '.$this->action();
    }

    /**
     * WHETHER THIS PERMISSION CARRIES AN AREA — the scope axis the area-aware
     * voter reads ({@see \Uhifadhi\Bundle\TeamBundle\Security\PermissionVoter}).
     *
     * An AREA-SCOPED permission answers "may this person do X *here*?" — the
     * voter compares the target area against the actor's authority-area (their
     * department's scope). An INHERENTLY GLOBAL one has no area to compare
     * against and is granted on the permission alone.
     *
     * Only `area.create` is global among the core seven, and structurally so: a
     * new area has no area yet, so there is nothing to scope the check against —
     * creating areas is a fleet act. Everything else operational is area-scoped;
     * `area.delete` is area-scoped by ruling, for consistency
     * with the other area permissions. The asymmetry is deliberate: operational
     * power is local by default, only fleet-shaping acts are global.
     */
    public function isAreaScoped(): bool
    {
        return self::AreaCreate !== $this;
    }

    /**
     * The whole catalogue in declaration order, for the /team/positions permission matrix.
     *
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * @return array{0: string, 1: string, 2: string} [umbrella, action, description]
     */
    private function meta(): array
    {
        return match ($this) {
            self::AreaView => ['Areas', 'View', 'See the areas this installation manages and everything recorded inside them.'],
            self::AreaCreate => ['Areas', 'Create', 'Draw a new area and add it to the installation.'],
            self::AreaEdit => ['Areas', 'Edit', 'Change an area’s name, its boundary and its settings.'],
            self::AreaDelete => ['Areas', 'Delete', 'Remove an area, and with it everything filed under that area.'],
            self::ModuleView => ['Modules', 'View', 'Open the modules switched on for an area and read what they show.'],
            self::ModuleCreate => ['Modules', 'Add', 'Switch a module on for an area and configure its settings and visualizations.'],
            self::TeamManage => ['Team', 'Manage', 'Administer this team: add people, deactivate them, change tiers, and compose the positions everybody else’s permissions come from.'],
        };
    }
}
