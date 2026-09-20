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

namespace Uhifadhi\Bundle\ShellBundle\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Uhifadhi\Bundle\ShellBundle\Model\AreaTab;
use Uhifadhi\Bundle\ShellBundle\Model\SettingsScreen;
use Uhifadhi\Contracts\Settings\SettingsTab;

/**
 * THE SETTINGS SECTION'S FRAME: which screens it has, which one is being read,
 * and where each of them lives.
 *
 * THE SECTION WEARS THE AREA IDIOM (ruled 2026-09-20): a head that is the same
 * on every tab, a strip of the screens it owns, and the tab that EDITS the
 * organisation last. So its strip is built exactly the way an area's is, from
 * one declaration — {@see SettingsTab} — which is also what the sidebar's
 * subtree is built from. Two readings of one list; a strip and a sidebar row
 * cannot disagree about which screens the section has.
 *
 * ONE ADDRESS, FOUR SCREENS. The first screen is the bare address and every
 * other hangs one segment below it, which is the shape a configure page's
 * sections already wear. A screen nobody named answers 404 here rather than
 * silently falling back to the first: a mistyped address that quietly drew
 * something is worse than one that says it is not a page.
 *
 * IT NAMES NO SCREEN. Every method below reads the declaration; not one of
 * them mentions a tab by name, which is what keeps adding a fifth screen a
 * change to the enum and to nothing else.
 */
final class SettingsSection
{
    /** The one route the whole section is drawn at — the application mounts it. */
    public const string ROUTE = 'settings';

    /** The route parameter that names the screen; absent for the first one. */
    public const string PARAMETER = 'tab';

    /** What the head says, on every screen of the section. */
    public const string TITLE = 'Settings';

    /** The one thing in the action row: this section reads the whole installation. */
    public const string SCOPE = 'organisation scope';

    public function __construct(
        private readonly UrlGeneratorInterface $urls,
        private readonly SettingsReading $reading,
    ) {
    }

    /**
     * THE SCREEN AT THIS ADDRESS, or null where the segment names none.
     *
     * Returning null rather than throwing keeps the decision about what a
     * miss MEANS with the controller, which is the layer that can answer with
     * a 404.
     */
    public function screen(?string $segment): ?SettingsScreen
    {
        $tab = null === $segment ? SettingsTab::first() : SettingsTab::tryFrom($segment);
        if (null === $tab) {
            return null;
        }

        /*
         * THE TEMPLATE IS DERIVED, NOT DECLARED. One screen is one template
         * named after it, so a fifth screen needs no entry in a map here —
         * and the declaration stays free of this bundle's own namespace,
         * which is what lets it live in the contracts package at all.
         */
        return new SettingsScreen('@Shell/settings/'.$tab->value.'.html.twig', [
            'title' => self::TITLE,
            'subtitle' => $tab->subtitle(),
            'scope' => self::SCOPE,
            'tabs' => $this->tabs($tab),
            // THE READING ITSELF, NOT ITS ANSWERS. Each screen draws a
            // different few of them, and a controller that resolved all of
            // them would run the health checks to draw the identity card.
            'settings' => $this->reading,
        ]);
    }

    /**
     * THE STRIP — every screen of the section, in the declared order, with the
     * one being read marked.
     *
     * @return list<AreaTab>
     */
    public function tabs(?SettingsTab $current = null): array
    {
        $tabs = [];
        foreach (SettingsTab::cases() as $tab) {
            $tabs[] = new AreaTab($tab->label(), $this->addressOf($tab), $tab === $current);
        }

        return $tabs;
    }

    /** Where one screen lives. */
    public function addressOf(SettingsTab $tab): string
    {
        $segment = $tab->segment();

        return $this->urls->generate(self::ROUTE, null === $segment ? [] : [self::PARAMETER => $segment]);
    }
}
