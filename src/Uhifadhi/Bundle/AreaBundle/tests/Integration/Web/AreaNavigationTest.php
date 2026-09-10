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
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Shell\AreaNavigation;
use Uhifadhi\Bundle\RegistryBundle\Entity\Module;
use Uhifadhi\Bundle\RegistryBundle\Enum\ModuleCategory;
use Uhifadhi\Bundle\RegistryBundle\Enum\ModuleStatus;
use Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleService;
use Uhifadhi\Bundle\ShellBundle\Model\NavItem;
use Uhifadhi\Bundle\ShellBundle\Model\NavSection;

/**
 * THE AREAS SECTION OF THE SIDEBAR — the register, and under it every area with
 * its own screens unfolded.
 *
 * GATING IS THIS BUNDLE'S JOB, not the shell's: the shell holds no
 * authorization service and asks nothing about the viewer. A row somebody may
 * not have is ABSENT from what this returns, never hidden — a hidden row is a
 * row that leaks its existence to whoever reads the HTML.
 */
final class AreaNavigationTest extends WebTestCase
{
    /**
     * A viewer who may also see the area's modules. The Modules screen is the
     * only one of the area's own that carries a gate, and it is the branch the
     * fourth rung hangs from.
     *
     * @var list<string>
     */
    private const array WITH_MODULES = [...self::ALL_AREA_PERMISSIONS, 'module.view'];

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
            ['Overview', 'Zones'],
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

    /**
     * THE SIDEBAR'S FOURTH RUNG — a module unfolds to its OWN data places, and
     * they are the same list the strip under the module's head is drawn from,
     * because there is only one declaration for both to read.
     */
    public function testTheModuleBeingViewedUnfoldsToItsOwnDataPlaces(): void
    {
        $this->boot(self::WITH_MODULES);
        $area = $this->anArea('Northern Conservation Reserve');
        $this->aCatalogue();
        $this->install($area, 'patrols');
        $uuid = (string) $area->getUuidString();

        $modules = $this->modulesBranch($this->navAtRoute('/areas/'.$uuid.'/modules/patrols', 'test_module_entry', self::WITH_MODULES));

        self::assertSame('Patrols', $modules[0]->label);
        self::assertSame(
            ['Overview', 'Patrols'],
            array_map(static fn (NavItem $i): string => $i->label, $modules[0]->children),
        );
    }

    /**
     * THE MODULE IS THE OPEN ANCESTOR AND THE PLACE UNDER IT CARRIES THE LIGHT.
     * Exactly one row in the branch is lit, and on a module's list screen it is
     * the leaf rather than the module.
     */
    public function testTheLeafCarriesTheLightAndTheModuleIsOnlyTheBranchAboveIt(): void
    {
        $this->boot(self::WITH_MODULES);
        $area = $this->anArea('Northern Conservation Reserve');
        $this->aCatalogue();
        $this->install($area, 'patrols');
        $uuid = (string) $area->getUuidString();

        $modules = $this->modulesBranch($this->navAtRoute('/areas/'.$uuid.'/modules/patrols/patrols', 'test_module_list', self::WITH_MODULES));

        self::assertFalse($modules[0]->current, 'the module is the ancestor, not the lit row');
        self::assertTrue($modules[0]->open);
        self::assertSame(
            ['Patrols'],
            array_map(
                static fn (NavItem $i): string => $i->label,
                array_values(array_filter($modules[0]->children, static fn (NavItem $i): bool => $i->current)),
            ),
        );
    }

    /**
     * A MODULE THE VIEWER IS NOT IN STAYS FOLDED AND EMPTY. Drilling every
     * module of every area would build rows nobody can see, and the point of the
     * rung is to show where you are.
     */
    public function testAModuleTheViewerIsNotInsideDoesNotUnfold(): void
    {
        $this->boot(self::WITH_MODULES);
        $area = $this->anArea('Northern Conservation Reserve');
        $this->aCatalogue();
        $this->install($area, 'patrols');

        $modules = $this->modulesBranch($this->navAtRoute('/areas/'.$area->getUuidString().'/modules', 'area_modules', self::WITH_MODULES));

        self::assertNotSame([], $modules, 'the area has a module to fold');

        foreach ($modules as $module) {
            self::assertSame([], $module->children, $module->label.' unfolded from outside');
        }
    }

    /**
     * The catalogue this suite's modules are filed in. Written here rather than
     * read from a module bundle, because this bundle depends on none.
     */
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

    /** Switch a module on for an area, through the registry's own ledger. */
    private function install(AreaOfInterest $area, string $slug): void
    {
        /** @var AreaModuleService $modules */
        $modules = static::getContainer()->get('test_public.registry.area_modules');
        $modules->install($area, $slug);
    }

    /**
     * The rows hanging off the area's Modules screen — the third rung's branch.
     *
     * @return list<NavItem>
     */
    private function modulesBranch(AreaNavigation $nav): array
    {
        $screens = $this->sections($nav)[0]->items[0]->children[0]->children;

        foreach ($screens as $screen) {
            if ('Modules' === $screen->label) {
                return $screen->children;
            }
        }

        self::fail('the area has no Modules screen to hang modules from');
    }

    /**
     * The nav as it renders on a named ROUTE, not merely a path: the fourth rung
     * lights by route name, which is how a module says which of its screens are
     * the same place.
     *
     * @param list<string> $grants
     */
    private function navAtRoute(string $path, string $route, array $grants = self::ALL_AREA_PERMISSIONS): AreaNavigation
    {
        if (!isset($this->em)) {
            $this->boot($grants);
        }
        $this->signIn();

        $request = Request::create($path);
        $request->attributes->set('_route', $route);

        /** @var RequestStack $stack */
        $stack = static::getContainer()->get('request_stack');
        $stack->push($request);

        /** @var AreaNavigation $nav */
        $nav = static::getContainer()->get('test_public.area.navigation');

        return $nav;
    }
}
