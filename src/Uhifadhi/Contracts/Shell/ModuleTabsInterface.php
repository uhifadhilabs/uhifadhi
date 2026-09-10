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

namespace Uhifadhi\Contracts\Shell;

/**
 * HOW A MODULE DECLARES ITS DATA PLACES — and the whole of what it declares
 * about its own navigation.
 *
 * A TAB IS A PLACE WHERE DATA LIVES. Patrols has an overview and a list of
 * patrols, so patrols declares two tabs; incidents declares an overview and a
 * list of incidents. Nothing that CONFIGURES a module is a tab — no settings,
 * no kinds, no widget library — because a strip that mixes places to look at
 * with screens that change how the module behaves stops meaning anything. Those
 * are sections of the one configure page ({@see ConfigurationSectionsInterface}).
 *
 * ONE LIST, TWO RENDERINGS, AND THE MODULE WRITES NEITHER. The shell draws the
 * declared tabs as the strip under the module's head AND as the module's
 * children in the sidebar's location tree, so the two cannot disagree the way
 * two hand-kept copies eventually would. A module that ships a `_tabs.html.twig`
 * of its own is a module that will drift from the frame.
 *
 * IT TAKES NOTHING, exactly like every other plug-point in this package. The
 * shell passes no area, no request and no viewer: it resolves the area from the
 * request it is already serving and merges it into every tab's route. A module
 * that needed to be handed the area would be a module the shell had to know the
 * shape of.
 *
 * A MODULE IS NOT AUTOCONFIGURED. A reusable bundle's services are wired
 * explicitly, so the tag goes on by hand:
 *
 *     $services->set('patrol.module_tabs', PatrolModuleTabs::class)
 *         ->tag(ModuleTabsInterface::TAG);
 *
 * A declaration that forgot the tag gets the module's pages with no strip and
 * no children in the tree, and nothing anywhere says why. Tag it.
 */
interface ModuleTabsInterface
{
    /**
     * The tag that puts a declaration in the frame.
     *
     * A CONSTANT, so a module spells it once and a rename is a compile error
     * rather than a strip that quietly stops being drawn.
     */
    public const string TAG = 'uhifadhi.module_tabs';

    /**
     * WHICH MODULE THESE TABS BELONG TO — the same slug the module's
     * {@see \Uhifadhi\Contracts\ModuleProviderInterface::slug()} returns.
     *
     * It is what the shell matches against the module the request is inside, so
     * two modules never see each other's strips, and a module switched off for
     * an area contributes nothing to a page it does not appear on.
     */
    public function slug(): string;

    /**
     * The module's data places, in the order they should render. Exactly one of
     * them will be lit on any of the module's own pages, and the module decides
     * which by naming the routes each tab is the place for
     * ({@see ModuleTab::lightsFor()}).
     *
     * WITHHOLD A PLACE THE VIEWER MAY NOT HAVE — do not disable it. A greyed
     * tab tells a ranger a screen exists and they are not trusted with it,
     * which is a worse product than not mentioning it; the value object has no
     * url-less form, so there is nothing here to grey out even by accident.
     *
     * @return list<ModuleTab>
     */
    public function tabs(): array;
}
