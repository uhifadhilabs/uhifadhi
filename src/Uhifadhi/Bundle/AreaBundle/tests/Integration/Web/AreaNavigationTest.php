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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Uhifadhi\Bundle\AreaBundle\Shell\AreaNavigation;
use Uhifadhi\Bundle\ShellBundle\Model\NavItem;
use Uhifadhi\Bundle\ShellBundle\Model\NavSection;

/**
 * THE AREAS SECTION OF THE SIDEBAR — the register, and under it every area with
 * its own screens unfolded.
 *
 * GATING IS THIS MODULE'S JOB, not the shell's: the shell holds no
 * authorization service and asks nothing about the viewer. A row somebody may
 * not have is ABSENT from what this returns, never hidden — a hidden row is a
 * row that leaks its existence to whoever reads the HTML.
 */
final class AreaNavigationTest extends WebTestCase
{
    /** @param list<string> $grants */
    private function navAt(string $path, array $grants = self::ALL_AREA_PERMISSIONS): AreaNavigation
    {
        if (!isset($this->em)) {
            $this->boot($grants);
        }
        $this->signIn();

        /** @var RequestStack $stack */
        $stack = static::getContainer()->get('request_stack');
        $stack->push(Request::create($path));

        /** @var AreaNavigation $nav */
        $nav = static::getContainer()->get('test_public.area.navigation');

        return $nav;
    }

    /** @return list<NavSection> */
    private function sections(AreaNavigation $nav): array
    {
        return array_values([...$nav->sections()]);
    }

    public function testItContributesAnAreasRowUnderItsOwnHeading(): void
    {
        $this->boot();
        $this->anArea();

        $sections = $this->sections($this->navAt('/areas'));

        self::assertCount(1, $sections);
        self::assertSame(AreaNavigation::SECTION, $sections[0]->label);
        self::assertSame('Areas', $sections[0]->items[0]->label);
    }

    /** A declared position, not a hope about container compilation order. */
    public function testItDeclaresWhereItSitsAmongTheOtherSections(): void
    {
        $this->boot();
        $this->anArea();

        self::assertSame(AreaNavigation::POSITION, $this->sections($this->navAt('/areas'))[0]->position);
    }

    /**
     * EVERY AREA UNFOLDS TO ITS OWN SCREENS, and they are the SAME list the tab
     * strip is drawn from — so a screen cannot appear in one and not the other.
     */
    public function testEachAreaUnfoldsToTheScreensTheTabStripWouldShow(): void
    {
        $this->boot();
        $area = $this->anArea('Northern Conservation Reserve');

        $areas = $this->sections($this->navAt('/areas/'.$area->getUuidString()))[0]->items[0]->children;

        self::assertCount(1, $areas);
        self::assertSame('Northern Conservation Reserve', $areas[0]->label);
        self::assertSame(
            ['Overview', 'Zones', 'Settings'],
            array_map(static fn (NavItem $i): string => $i->label, $areas[0]->children),
        );
    }

    /** Areas are listed by name — insertion order is invisible to anybody reading. */
    public function testTheAreasAreListedByName(): void
    {
        $this->boot();
        $this->anArea('Western Reserve');
        $this->anArea('Eastern Reserve');

        $areas = $this->sections($this->navAt('/areas'))[0]->items[0]->children;

        self::assertSame(
            ['Eastern Reserve', 'Western Reserve'],
            array_map(static fn (NavItem $i): string => $i->label, $areas),
        );
    }

    /**
     * ONLY THE AREA BEING VIEWED IS UNFOLDED. An installation with eight areas
     * would otherwise open with forty rows.
     */
    public function testOnlyTheAreaBeingViewedIsOpen(): void
    {
        $this->boot();
        $here = $this->anArea('Aardvark Area');
        $this->anArea('Zebra Area');

        $areas = $this->sections($this->navAt('/areas/'.$here->getUuidString()))[0]->items[0]->children;

        self::assertTrue($areas[0]->open, 'the area being viewed is unfolded');
        self::assertFalse($areas[1]->open, 'the others are not');
    }

    /** The register row lights on the register itself, never on an area beneath it. */
    public function testTheRegisterRowLightsOnlyOnTheRegister(): void
    {
        $this->boot();
        $area = $this->anArea();

        self::assertTrue($this->sections($this->navAt('/areas'))[0]->items[0]->current);

        self::assertFalse(
            $this->sections($this->navAt('/areas/'.$area->getUuidString()))[0]->items[0]->current,
        );
    }

    /**
     * ABSENT, NEVER HIDDEN. Somebody without `area.view` gets no section at all,
     * rather than a section whose rows all close in their face.
     */
    public function testSomebodyWhoMayNotSeeAnAreaGetsNoSectionAtAll(): void
    {
        $this->boot([]);
        $this->anArea();

        self::assertSame([], $this->sections($this->navAt('/areas', [])));
    }

    /**
     * NO TOKEN, NO QUESTION. A page can render outside any firewall — an error
     * page, a console-rendered template — and asking the authorization checker
     * there THROWS rather than answering false. A viewer nobody can identify
     * holds nothing, which is the same answer without the 500.
     */
    public function testThereIsNoSectionWhereThereIsNoViewerToAskAbout(): void
    {
        $this->boot();
        $this->anArea();

        /** @var RequestStack $stack */
        $stack = static::getContainer()->get('request_stack');
        $stack->push(Request::create('/areas'));

        /** @var AreaNavigation $nav */
        $nav = static::getContainer()->get('test_public.area.navigation');

        // Deliberately NOT signed in.
        self::assertSame([], $this->sections($nav));
    }

    /** An installation with no areas yet still offers the register itself. */
    public function testTheRegisterRowIsThereBeforeAnyAreaIs(): void
    {
        $this->boot();

        $sections = $this->sections($this->navAt('/areas'));

        self::assertCount(1, $sections);
        self::assertSame([], $sections[0]->items[0]->children);
    }
}
