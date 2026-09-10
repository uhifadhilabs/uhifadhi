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
 * ONE DATA PLACE A MODULE HAS — a tab in the strip under the module's head, and
 * a row under the module in the sidebar's tree. The same value object serves
 * both, because they are two renderings of one list and a module that had to
 * declare them separately would eventually declare them differently.
 *
 * A TAB IS A PLACE WHERE DATA LIVES, and that is the whole rule. A patrol
 * module has an overview and a list of patrols, so it has two tabs. Nothing
 * that CONFIGURES the module is a tab: no settings, no kinds, no widget
 * library — those are sections of the configure page and reached from the one
 * Configure action ({@see ConfigurationSection}).
 *
 * IT CARRIES A ROUTE, NOT A URL. A module cannot build its own urls without a
 * router, and a module that had to build them would have to know the area's
 * uuid to build them WITH. So it names the route and the shell generates it,
 * merging the area of the request the viewer is actually in — a tab's own
 * parameters win, so a module that scopes a screen differently still can.
 *
 * @see ModuleTabsInterface for how a module declares the list
 */
final readonly class ModuleTab
{
    /**
     * The route names this tab lights for. Never empty: the constructor
     * defaults it to the route the tab points at.
     *
     * @var list<string>
     */
    public array $lightsFor;

    /**
     * @param string                $label      what the tab says, e.g. "Patrols"
     * @param string                $routeName  the route that serves it
     * @param array<string, scalar> $parameters route parameters beyond the area's own
     * @param list<string>          $lightsFor  the routes that are this place — the tab's
     *                                          own route when empty; an entry ending in
     *                                          `*` lights every route with that prefix
     */
    public function __construct(
        public string $label,
        public string $routeName,
        public array $parameters = [],
        array $lightsFor = [],
    ) {
        if ('' === trim($label)) {
            throw new \InvalidArgumentException('A tab says something: it cannot have an empty label.');
        }

        if ('' === trim($routeName)) {
            throw new \InvalidArgumentException(\sprintf('The "%s" tab names no route. A tab the viewer may not have is withheld by its module, never rendered without a destination.', $label));
        }

        $this->lightsFor = [] === $lightsFor ? [$routeName] : $lightsFor;
    }

    /**
     * IS THIS TAB THE PLACE THE VIEWER IS ON?
     *
     * A LIST AND ITS DETAIL SCREEN ARE ONE PLACE — opening a record does not
     * leave the place the record lives in — so a module names the whole family
     * rather than one route. Only the module knows which of its routes are the
     * same place, which is why the answer is declared and not guessed from a
     * url shape.
     */
    public function lightsFor(string $route): bool
    {
        foreach ($this->lightsFor as $candidate) {
            if ($candidate === $route) {
                return true;
            }

            // A TRAILING STAR IS A PREFIX, and only a trailing one. A star in
            // the middle is part of the name: a half-understood pattern lights
            // tabs nobody meant to light, and a module gets no warning of it.
            if (str_ends_with($candidate, '*') && str_starts_with($route, substr($candidate, 0, -1))) {
                return true;
            }
        }

        return false;
    }
}
