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

namespace Uhifadhi\Bundle\TeamBundle\Shell;

use Uhifadhi\Bundle\TeamBundle\Controller\TeamConfigureController;
use Uhifadhi\Contracts\Shell\ConfigurationSection;
use Uhifadhi\Contracts\Shell\ConfigurationSectionsInterface;

/**
 * WHAT `Configure` OPENS IN THE TEAM SECTION.
 *
 * THREE SCREENS, NOT THREE TEMPLATES. An area's configure sections are rendered
 * into the shell's own area-shaped page; an org-level section has no area in
 * its address, so its sections are screens of their own and the strip is built
 * from their routes. That is a difference of address, not of idiom.
 *
 * THE ORDER IS THE HOUSE'S, NOT THIS CLASS'S. The shell ranks a surface's
 * sections — Widget library, then everything else in the order it was
 * declared, then Settings last — so the strip reads the same on every surface
 * in the product. That rank also decides what the one `Configure` action
 * opens: the first entry.
 *
 * THERE IS NO `Roles` ENTRY, AND THE DRAWN STRIP HAS ONE. It pointed at the
 * Positions matrix, "because that is where a permission set is actually
 * edited" — but a configure entry naming a route makes that route a configure
 * SECTION, and Positions is a data tab: standing on it, the frame drew the
 * configure strip instead of the section's own. Two strips cannot both be
 * right on one page. The matrix is reached from the Positions tab and from the
 * Roles tab, both of which are in the tab strip, so nothing is unreachable —
 * only the third door is gone.
 */
final readonly class TeamSectionConfiguration implements ConfigurationSectionsInterface
{
    public function slug(): string
    {
        return TeamSectionTabs::SURFACE;
    }

    public function heading(): string
    {
        return 'Team';
    }

    public function summary(): string
    {
        return 'How people get in, what happens to an account, and who may change either.';
    }

    public function sections(): array
    {
        return [
            // ONE SCREEN (owner 2026-09-22): no widget library — a register is app
            // mechanics — and no positions vocabulary — a list nothing read once
            // position names became unique across the organization.
            ConfigurationSection::screen(
                ConfigurationSection::SETTINGS,
                'Team settings',
                TeamConfigureController::SETTINGS,
            ),
        ];
    }
}
