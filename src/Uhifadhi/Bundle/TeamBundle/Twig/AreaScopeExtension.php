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

namespace Uhifadhi\Bundle\TeamBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Uhifadhi\Bundle\TeamBundle\Security\AreaAuthority;

/**
 * THE ONE FACT THE "SCOPED TO <AREA>" BANNER NEEDS — the name of the area a
 * bounded administrator is confined to, or nothing at all when they are
 * unbounded (DECISIONS §5.6, docs/area-scoped-authority.md §7.6).
 *
 * The area-admin design draws a banner ("You are scoped to Southern Reserve") on the
 * team / department / position management chrome, shown to a bounded (area-X)
 * `team.manage` holder and to nobody else. Every one of those screens is a
 * separate controller, so the banner reads its one input through a Twig function
 * rather than each controller threading the same value into its render context —
 * the fence is stated in one partial, fed from one place.
 *
 * IT NAMES THE AREA THROUGH THE CONTRACT, NEVER AN AREA PACKAGE. The name comes
 * off {@see \Uhifadhi\Contracts\Entity\AreaInterface::getName()} on the
 * authority-area {@see AreaAuthority} already resolves for the voter, so this
 * module points at an area exactly as it points at a person, and requires
 * neither package to do it. A `null` answer is the whole signal: no area means
 * unbounded, which means no banner.
 */
final class AreaScopeExtension extends AbstractExtension
{
    public function __construct(
        private readonly AreaAuthority $authority,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('team_scope_area', $this->scopeArea(...)),
        ];
    }

    /**
     * The name of the area this administrator is confined to, or null when they
     * are unbounded (a tier or org-level holder) or not signed in — the null the
     * banner reads as "show nothing".
     */
    public function scopeArea(): ?string
    {
        return $this->authority->authorityArea()?->getName();
    }
}
