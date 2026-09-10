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

namespace Uhifadhi\Contracts\Tests\Shell;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Contracts\Shell\ModuleTab;

/**
 * ONE DATA PLACE A MODULE HAS, as a value object — a label, the route that
 * serves it, and the answer to "am I on it".
 *
 * The load-bearing thing to prove is the LIGHTING RULE, because a strip whose
 * job is to say which of these places you are on is worth nothing if it says
 * the wrong one. A tab lights for the route it points at, for any other route
 * it was told to light for, and for a whole family of routes when the entry is
 * written as a prefix.
 */
final class ModuleTabTest extends TestCase
{
    public function testATabCarriesItsLabelItsRouteAndTheRoutesParameters(): void
    {
        $tab = new ModuleTab('Patrols', 'patrol_list', ['status' => 'open']);

        self::assertSame('Patrols', $tab->label);
        self::assertSame('patrol_list', $tab->routeName);
        self::assertSame(['status' => 'open'], $tab->parameters);
    }

    public function testATabLightsForItsOwnRouteByDefault(): void
    {
        $tab = new ModuleTab('Overview', 'patrol_dashboard');

        self::assertTrue($tab->lightsFor('patrol_dashboard'));
        self::assertFalse($tab->lightsFor('patrol_list'));
    }

    /**
     * A LIST SCREEN AND ITS DETAIL SCREEN ARE ONE PLACE. Opening a record does
     * not leave the place the record lives in, so the tab that led there stays
     * lit — and the module says so, because only the module knows which of its
     * routes are the same place.
     */
    public function testATabLightsForEveryRouteItWasToldToLightFor(): void
    {
        $tab = new ModuleTab('Patrols', 'patrol_list', lightsFor: ['patrol_list', 'patrol_detail']);

        self::assertTrue($tab->lightsFor('patrol_list'));
        self::assertTrue($tab->lightsFor('patrol_detail'));
        self::assertFalse($tab->lightsFor('patrol_dashboard'));
    }

    /**
     * A MODULE WITH A FAMILY OF ROUTES NAMES THE FAMILY, not every member: a
     * trailing `*` is the whole prefix. Without it a module grows a screen and
     * the strip goes dark on it until somebody remembers this list.
     */
    public function testATrailingStarLightsAWholeFamilyOfRoutes(): void
    {
        $tab = new ModuleTab('Patrols', 'patrol_list', lightsFor: ['patrol_list*']);

        self::assertTrue($tab->lightsFor('patrol_list'));
        self::assertTrue($tab->lightsFor('patrol_list_detail'));
        self::assertTrue($tab->lightsFor('patrol_list_export'));
        self::assertFalse($tab->lightsFor('patrol_dashboard'));
    }

    /**
     * THE STAR IS A PREFIX, NOT A WILDCARD ANYWHERE. `patrol_*_detail` is not a
     * pattern this understands, and pretending it is would light tabs nobody
     * meant to light.
     */
    public function testAStarAnywhereButTheEndIsPartOfTheName(): void
    {
        $tab = new ModuleTab('Patrols', 'patrol_list', lightsFor: ['patrol_*_detail']);

        self::assertFalse($tab->lightsFor('patrol_foot_detail'));
        self::assertTrue($tab->lightsFor('patrol_*_detail'));
    }

    public function testATabWithNoLabelIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ModuleTab('', 'patrol_list');
    }

    public function testATabWithNoRouteIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ModuleTab('Patrols', '');
    }
}
