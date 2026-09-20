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

namespace Uhifadhi\Bundle\TeamBundle\Performance;

use Uhifadhi\Contracts\Kpi\CurrentPeriodInterface;

/**
 * THE PERIOD A PERFORMANCE SCREEN CANNOT DRAW WITHOUT — and the one sentence
 * that says so when nobody published one.
 *
 * THIS BUNDLE IS TWO THINGS AT ONCE: a model an installation persists — the
 * people, the positions, the departments — and a set of SCREENS. The model
 * needs no calendar; the performance screens caption a period on every
 * figure they draw. A kernel that registers this bundle for its entities and
 * not its pages — a module's test kernel, a console-only importer — is an
 * ordinary thing, and it must boot.
 *
 * SO THE DEPENDENCY IS OPTIONAL IN THE CONTAINER AND REQUIRED AT THE SCREEN.
 * The wiring asks for {@see CurrentPeriodInterface} and tolerates its
 * absence; a page that actually needs one says what is missing and how to
 * fix it, rather than failing as "non-existent service" at container compile
 * in somebody else's suite — which is exactly how this was found.
 *
 * ONE MESSAGE, ONE PLACE. Three screens need the same thing, and three
 * copies of the sentence is three chances for one of them to name the wrong
 * package.
 */
final class RequiredPeriod
{
    /** A guard, not an object: there is nothing to construct. */
    private function __construct()
    {
    }

    /**
     * @throws \LogicException when nothing in this kernel publishes the current period
     */
    public static function of(?CurrentPeriodInterface $periods): CurrentPeriodInterface
    {
        return $periods ?? throw new \LogicException('A performance screen captions the period it reads, and nothing in this kernel publishes one. Register a bundle that provides Uhifadhi\Contracts\Kpi\CurrentPeriodInterface — the core\'s AtlasBundle does, and team-bundle already requires it in composer.json; add Uhifadhi\Bundle\AtlasBundle\AtlasBundle::class to config/bundles.php. A kernel that wants this bundle\'s people and not its performance pages needs no such bundle and boots without one.');
    }
}
