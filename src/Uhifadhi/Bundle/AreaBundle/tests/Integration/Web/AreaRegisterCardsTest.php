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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web;

use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\RegistryBundle\Entity\Module;
use Uhifadhi\Bundle\RegistryBundle\Enum\ModuleCategory;
use Uhifadhi\Bundle\RegistryBundle\Enum\ModuleStatus;

/**
 * THE REGISTER CARD'S OPERATIONAL PULSE, GATHERED THROUGH THE OVERVIEW CONTRIBUTIONS —
 * and through nothing this bundle wrote.
 *
 * The area page owns a card's frame, its identity, its "modules live" count and its
 * face; every operational figure on it arrives from a module switched on in the
 * area — the stat cells and "out right now" chip from the now-tile contribution, the
 * alert flag from the attention contribution, "last check-in" from the pulse contribution. What
 * is proved here is that the area page LAYS OUT a contributed figure where its module
 * is on and shows the honest boundary-only card where it is off: the same
 * open/closed discipline the overview keeps, at register density. The suite ships
 * its own contributors ({@see FakeNowTiles}, {@see FakeAttention},
 * {@see FakePulse}) because this bundle must depend on no real module.
 */
final class AreaRegisterCardsTest extends WebTestCase
{
    private function body(string $path): string
    {
        $this->browser()->request('GET', $path);

        return (string) $this->browser()->getResponse()->getContent();
    }

    private function install(AreaOfInterest $area, string $slug): void
    {
        /** @var \Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleService $modules */
        $modules = static::getContainer()->get('test_public.registry.area_modules');
        $modules->install($area, $slug);
    }

    private function aCatalogue(): void
    {
        $this->em->persist(new Module()
            ->setSlug('patrols')
            ->setName('Patrols')
            ->setCategory(ModuleCategory::Pressure)
            ->setStatus(ModuleStatus::Live)
            ->setDataSource('GPS field tracks')
            ->setPosition(0));
        $this->em->flush();
    }

    /**
     * A CARD'S STAT CELLS ARE THE MODULE'S NOW-TILES, laid out beside the area page's
     * own "modules live". The area page prints "23" and "patrols this wk" without
     * knowing either is a patrol figure — it lays out what the registry handed back.
     */
    public function testTheStatCellsFlowFromTheNowTileContribution(): void
    {
        $this->boot();
        $area = $this->anArea('Northern Conservation Reserve');
        $this->aCatalogue();
        $this->install($area, 'patrols');

        $body = $this->body('/areas');

        // The area page's own cell...
        self::assertStringContainsString('modules live', $body);
        // ...and the module's contributed figures beside it.
        self::assertStringContainsString('23', $body);
        self::assertStringContainsString('patrols this wk', $body);
        self::assertStringContainsString('14/22', $body);
        self::assertStringContainsString('team on duty', $body);
    }

    /**
     * THE "OUT RIGHT NOW" CHIP IS THE MODULE'S ONE LIVE NOW-TILE. A live tile
     * foots the card rather than filling a standing stat cell, so "3 patrols out
     * now" reads as the pulse of the moment, not a figure for the week.
     */
    public function testTheLiveChipFlowsFromTheLiveNowTile(): void
    {
        $this->boot();
        $area = $this->anArea('Northern Conservation Reserve');
        $this->aCatalogue();
        $this->install($area, 'patrols');

        $body = $this->body('/areas');

        self::assertStringContainsString('3 patrols out now', $body);
    }

    /**
     * THE ALERT FLAG COUNTS THE ATTENTION CONTRIBUTION. Two contributed items make the
     * card's flag read a plural count, and the "With alerts" pill counts this
     * area among those asking for attention.
     */
    public function testTheAlertFlagCountsTheAttentionContribution(): void
    {
        $this->boot();
        $area = $this->anArea('Northern Conservation Reserve');
        $this->aCatalogue();
        $this->install($area, 'patrols');

        $body = $this->body('/areas');

        self::assertStringContainsString('2 alerts', $body);
        // The pill counts one area with alerts.
        self::assertStringContainsString('With alerts &middot; 1', $body);
    }

    /**
     * "LAST CHECK-IN" IS THE PULSE CONTRIBUTION'S MOST RECENT MOVE, with the exact
     * instant on a machine-readable `<time>` and a plain relative label for a
     * person.
     */
    public function testTheLastCheckInFlowsFromThePulseContribution(): void
    {
        $this->boot();
        $area = $this->anArea('Northern Conservation Reserve');
        $this->aCatalogue();
        $this->install($area, 'patrols');

        $body = $this->body('/areas');

        self::assertStringContainsString('last check-in', $body);
        self::assertStringContainsString('min ago', $body);
        self::assertStringContainsString('<time datetime=', $body);
    }

    /**
     * A LIVE AREA WEARS THE LIVE BADGE AND ITS FILTER FLAG. Its card carries the
     * live badge on the face and `data-live="1"`, so the Live pill finds it.
     */
    public function testALiveAreaWearsTheLiveBadge(): void
    {
        $this->boot();
        $area = $this->anArea('Northern Conservation Reserve');
        $this->aCatalogue();
        $this->install($area, 'patrols');

        $body = $this->body('/areas');

        self::assertStringContainsString('data-live="1"', $body);
        self::assertMatchesRegularExpression('/class="ax-live"/', $body);
    }

    /**
     * THE COUNTS MOVE WITH THE INSTALLATION. One area, one module on: Live counts
     * one, Awaiting setup counts none.
     */
    public function testThePillCountsReadTheLedger(): void
    {
        $this->boot();
        $area = $this->anArea('Northern Conservation Reserve');
        $this->aCatalogue();
        $this->install($area, 'patrols');

        $body = $this->body('/areas');

        self::assertStringContainsString('Live &middot; 1', $body);
        self::assertStringContainsString('Awaiting setup &middot; 0', $body);
    }
}
