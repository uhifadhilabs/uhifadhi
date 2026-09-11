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

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Uhifadhi\Bundle\AreaBundle\Controller\AreaModulesController;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Service\AreaComposition;
use Uhifadhi\Bundle\AreaBundle\Shell\AreaNavigation;
use Uhifadhi\Bundle\RegistryBundle\Entity\Module;
use Uhifadhi\Bundle\RegistryBundle\Enum\ModuleCategory;
use Uhifadhi\Bundle\RegistryBundle\Enum\ModuleStatus;
use Uhifadhi\Bundle\ShellBundle\Model\NavItem;

/**
 * THE PER-AREA MODULES SCREEN — what this area has switched on, and the shop it
 * is composed in.
 *
 * THE ROUTE NAME IS THE CONTRACT, not the path. `area_modules` is the name
 * three other packages already generate blind: this bundle's own tab strip lists
 * it, and the patrol module's breadcrumb and dashboard back-button ask for it and
 * print plain text when it does not answer. Mounting it here is what lights all
 * three, which is why {@see testTheTabStripLightsTheModulesTab} exists at all.
 *
 * THE LEDGER IS THE REGISTRY'S. Nothing here writes an `area_module` row by hand —
 * every switch goes through the registry's published service, because the invariant
 * that a pinned module cannot be parked lives there and a second writer would
 * eventually disagree with it.
 */
#[CoversClass(AreaModulesController::class)]
#[CoversClass(AreaComposition::class)]
#[CoversClass(AreaNavigation::class)]
final class AreaModulesTest extends WebTestCase
{
    /** Everything an ordinary admin holds, plus the module strings this screen asks for. */
    private const array ALL = ['area.view', 'area.create', 'area.edit', 'area.delete', 'module.view', 'module.create'];

    public function testTheGridShowsWhatTheAreaHasSwitchedOn(): void
    {
        $this->boot(self::ALL);
        $this->signIn();
        $area = $this->anArea();
        $this->aCatalogue();
        $this->install($area, 'patrols');

        $body = $this->body($this->modulesPath($area));

        self::assertStringContainsString('Patrols', $body);
        // Parked modules are the shop's business, not the grid's: a tile for
        // something that is switched off would open onto nothing.
        self::assertStringNotContainsString('Incidents', $body);
    }

    /** The category heading the registry files the module under, not one invented here. */
    public function testTheGridGroupsTilesByCatalogueCategory(): void
    {
        $this->boot(self::ALL);
        $this->signIn();
        $area = $this->anArea();
        $this->aCatalogue();
        $this->install($area, 'patrols');
        $this->install($area, 'forest-loss');

        $body = $this->body($this->modulesPath($area));

        self::assertStringContainsString('Pressure', $body);
        self::assertStringContainsString('Flux', $body);
    }

    /**
     * A TILE WITH NOWHERE TO GO IS NOT A LINK. A catalogue row whose bundle
     * declares no entry route has no pages yet; the tile stays inert rather than
     * 404ing on a click.
     */
    public function testATileWithoutAnEntryRouteIsNotALink(): void
    {
        $this->boot(self::ALL);
        $this->signIn();
        $area = $this->anArea();
        $this->aCatalogue();
        $this->install($area, 'forest-loss');

        self::assertStringContainsString('mtile-inert', $this->body($this->modulesPath($area)));
    }

    /** An area with nothing switched on is new, not broken. */
    public function testAnAreaWithNothingSwitchedOnSaysSo(): void
    {
        $this->boot(self::ALL);
        $this->signIn();
        $area = $this->anArea();
        $this->aCatalogue();

        self::assertStringContainsString('No modules', $this->body($this->modulesPath($area)));
    }

    /**
     * THE NAME OTHER PACKAGES GENERATE. A module's breadcrumb and its dashboard
     * back-button ask the router for `area_modules`; the path is this bundle's
     * convention and may move, the name may not.
     */
    public function testTheGridAnswersToItsContractRouteName(): void
    {
        $this->boot(self::ALL);
        $area = $this->anArea();

        /** @var UrlGeneratorInterface $urls */
        $urls = static::getContainer()->get('router');

        self::assertSame(
            $this->modulesPath($area),
            $urls->generate('area_modules', ['uuid' => $area->getUuidString()]),
        );
    }

    /**
     * THE TAB IS THE ROUTE'S, NOT THE STRIP'S. AreaShellSource lists an
     * `area_modules` tab and drops it silently where no route answers, so
     * an installation that unmounts the screen gets a shorter strip rather than
     * a broken page — and mounting it lights the tab with no edit anywhere.
     */
    public function testTheTabStripLightsTheModulesTab(): void
    {
        $this->boot(self::ALL);
        $this->signIn();
        $area = $this->anArea();
        $this->aCatalogue();

        $body = $this->body($this->modulesPath($area));

        self::assertStringContainsString($this->modulesPath($area), $body);
        self::assertStringContainsString('Modules', $body);
    }

    /** The tab is absent, not greyed, for somebody who may not see modules. */
    public function testTheModulesTabIsAbsentWithoutModuleView(): void
    {
        $this->boot(['area.view', 'area.edit']);
        $this->signIn();
        $area = $this->anArea();
        $this->aCatalogue();

        self::assertStringNotContainsString($this->modulesPath($area), $this->body('/areas/'.$area->getUuidString()));
    }

    public function testTheGridIsClosedWithoutModuleView(): void
    {
        $this->boot(['area.view']);
        $this->signIn();
        $area = $this->anArea();
        $this->aCatalogue();

        self::assertSame(403, $this->get($this->modulesPath($area))->getStatusCode());
    }

    /**
     * A ROW WHOSE BUNDLE IS GONE IS NOT A MODULE ANY MORE.
     *
     * The registry's catalogue is an INTERSECTION — rows in the table AND providers
     * registered — precisely so uninstalling a bundle removes its capability
     * without deleting anybody's data. The `area_module` row survives that, and
     * a screen reading rows straight would keep drawing a tile for a module the
     * machine no longer has: a card that can never open, forever, with no way to
     * clear it because the shop cannot offer to park something it cannot see.
     *
     * So both sides of this screen are read through the catalogue. The row stays
     * where it is, ready for the day the bundle comes back.
     */
    public function testAModuleWhoseBundleIsGoneLeavesTheScreenEntirely(): void
    {
        $this->boot(self::ALL);
        $this->signIn();
        $area = $this->anArea();
        $this->aCatalogue();
        $this->install($area, 'patrols');
        $this->aGhostRow($area);

        self::assertStringNotContainsString('Ghost', $this->body($this->modulesPath($area)));
        self::assertStringNotContainsString('Ghost', $this->body($this->modulesPath($area).'/customize'));
        // And the one whose bundle IS installed is untouched by the exclusion.
        self::assertStringContainsString('Patrols', $this->body($this->modulesPath($area)));
    }

    // ── The shop ──────────────────────────────────────────────────────────

    public function testTheShopListsActiveAndParkedSeparately(): void
    {
        $this->boot(self::ALL);
        $this->signIn();
        $area = $this->anArea();
        $this->aCatalogue();
        $this->install($area, 'patrols');

        $body = $this->body($this->modulesPath($area).'/customize');

        self::assertStringContainsString('Active modules', $body);
        self::assertStringContainsString('Inactive modules', $body);
        // Parked: in the catalogue, no row on this area.
        self::assertStringContainsString('Incidents', $body);
    }

    /** The grid's Customize button is hidden from somebody who may only look. */
    public function testTheCustomizeAffordanceCarriesItsPermission(): void
    {
        $this->boot(['area.view', 'module.view']);
        $this->signIn();
        $area = $this->anArea();
        $this->aCatalogue();

        self::assertStringNotContainsString('/customize', $this->body($this->modulesPath($area)));
    }

    public function testTheShopIsClosedWithoutModuleCreate(): void
    {
        $this->boot(['area.view', 'module.view']);
        $this->signIn();
        $area = $this->anArea();
        $this->aCatalogue();

        self::assertSame(403, $this->get($this->modulesPath($area).'/customize')->getStatusCode());
    }

    public function testAddingAModuleSwitchesItOnForTheArea(): void
    {
        $this->boot(self::ALL);
        $this->signIn();
        $area = $this->anArea();
        $this->aCatalogue();

        $response = $this->post($this->modulesPath($area).'/customize/install', ['module' => 'patrols']);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(['patrols'], $this->activeSlugs($area));
    }

    public function testSwitchingAModuleOffParksIt(): void
    {
        $this->boot(self::ALL);
        $this->signIn();
        $area = $this->anArea();
        $this->aCatalogue();
        $this->install($area, 'patrols');

        $response = $this->post($this->modulesPath($area).'/customize/uninstall', ['module' => 'patrols']);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame([], $this->activeSlugs($area));
    }

    /**
     * THE DATA STAYS. Parking a module removes it from the area's composition
     * and nothing else — the row survives switched off, which is what lets
     * switching it back on be a re-activation rather than a fresh install.
     */
    public function testParkingAModuleKeepsItsRow(): void
    {
        $this->boot(self::ALL);
        $this->signIn();
        $area = $this->anArea();
        $this->aCatalogue();
        $this->install($area, 'patrols');

        $this->post($this->modulesPath($area).'/customize/uninstall', ['module' => 'patrols']);
        $this->post($this->modulesPath($area).'/customize/install', ['module' => 'patrols']);

        self::assertSame(['patrols'], $this->activeSlugs($area));
        self::assertCount(1, $this->em->getRepository(\Uhifadhi\Bundle\RegistryBundle\Entity\AreaModule::class)->findAll());
    }

    public function testTheOrderThePillsAreDraggedIntoIsPersisted(): void
    {
        $this->boot(self::ALL);
        $this->signIn();
        $area = $this->anArea();
        $this->aCatalogue();
        $this->install($area, 'patrols');
        $this->install($area, 'incidents');

        $this->post($this->modulesPath($area).'/customize/reorder', ['order' => ['incidents', 'patrols']]);

        self::assertSame(['incidents', 'patrols'], $this->activeSlugs($area));
    }

    /**
     * AND THE ROWS ARE THE OTHER END OF THE SAME DRAG. The shop draws the active
     * set twice and says both are draggable, so the order a person expresses in
     * the detailed rows reaches the route exactly as a pill order does — and the
     * page they come back to is drawn in the order they just set, rows included.
     */
    public function testTheOrderTheRowsAreDraggedIntoIsPersistedAndRedrawn(): void
    {
        $this->boot(self::ALL);
        $this->signIn();
        $area = $this->anArea();
        $this->aCatalogue();
        $this->install($area, 'patrols');
        $this->install($area, 'incidents');
        $this->install($area, 'forest-loss');

        $this->post($this->modulesPath($area).'/customize/reorder', ['order' => ['forest-loss', 'incidents', 'patrols']]);

        self::assertSame(['forest-loss', 'incidents', 'patrols'], $this->activeSlugs($area));

        $rows = $this->body($this->modulesPath($area).'/customize');
        $positions = array_map(static fn (string $slug): int|false => strpos($rows, 'data-slug="'.$slug.'"'), ['forest-loss', 'incidents', 'patrols']);
        $sorted = $positions;
        sort($sorted);
        self::assertSame($sorted, $positions, 'The shop is not redrawn in the order it was just given.');
    }

    /** A write without the permission is refused, and nothing moves. */
    public function testTogglingIsClosedWithoutModuleCreate(): void
    {
        $this->boot(['area.view', 'module.view']);
        $this->signIn();
        $area = $this->anArea();
        $this->aCatalogue();

        self::assertSame(403, $this->post($this->modulesPath($area).'/customize/install', ['module' => 'patrols'])->getStatusCode());
        self::assertSame([], $this->activeSlugs($area));
    }

    /** A forged POST from another page is refused before it writes. */
    public function testAWriteWithoutAValidTokenIsRefused(): void
    {
        $this->boot(self::ALL);
        $this->signIn();
        $area = $this->anArea();
        $this->aCatalogue();

        $this->browser()->request('POST', $this->modulesPath($area).'/customize/install', ['module' => 'patrols', '_token' => 'forged']);

        self::assertSame(403, $this->browser()->getResponse()->getStatusCode());
        self::assertSame([], $this->activeSlugs($area));
    }

    /** A slug that is in no catalogue writes nothing and does not explode. */
    public function testAnUnknownModuleIsIgnored(): void
    {
        $this->boot(self::ALL);
        $this->signIn();
        $area = $this->anArea();
        $this->aCatalogue();

        self::assertSame(302, $this->post($this->modulesPath($area).'/customize/install', ['module' => 'nonesuch'])->getStatusCode());
        self::assertSame([], $this->activeSlugs($area));
    }

    /**
     * THE SIDEBAR'S MODULES BRANCH UNFOLDS TO THE AREA'S OWN MODULES.
     *
     * The reported miss: the tree drew "Modules" but nothing under it, so a
     * ranger on a module page could not see where they were. The branch is the
     * SAME set the grid draws (one reader, {@see AreaComposition::moduleLinksFor}),
     * so a module cannot be in the grid and absent from the tree. On a module's
     * page the leaf is the lit row and "Modules" is only the open branch above it.
     */
    public function testTheSidebarModulesBranchUnfoldsToTheAreasOwnModules(): void
    {
        $this->boot(self::ALL);
        $this->signIn();
        $area = $this->anArea('Northern Reserve');
        $this->aCatalogue();
        $this->install($area, 'patrols');

        // Stand inside the modules space — on the patrols module's own page.
        /** @var RequestStack $stack */
        $stack = static::getContainer()->get('request_stack');
        $stack->push(Request::create($this->modulesPath($area).'/patrols'));

        /** @var AreaNavigation $nav */
        $nav = static::getContainer()->get('test_public.area.navigation');

        $sections = array_values([...$nav->sections()]);
        $areaRow = $sections[0]->items[0]->children[0]; // Observatory › Areas › Northern Reserve
        $modules = $this->childNamed($areaRow, 'Modules');

        self::assertNotNull($modules, 'the Modules screen carries a branch of the area\'s modules');
        self::assertContains(
            'Patrols',
            array_map(static fn (NavItem $i): string => $i->label, $modules->children),
            'the area\'s active modules hang under Modules',
        );

        $patrols = $this->childNamed($modules, 'Patrols');
        self::assertNotNull($patrols);
        self::assertTrue($patrols->current, 'the module you are on is the lit leaf');
        self::assertFalse($modules->current, 'the parent yields the light to the leaf it is showing');
        self::assertTrue($modules->open, 'the branch is unfolded while you are inside it');
    }

    private function childNamed(NavItem $item, string $label): ?NavItem
    {
        foreach ($item->children as $child) {
            if ($child->label === $label) {
                return $child;
            }
        }

        return null;
    }

    // ── Fixtures and helpers ──────────────────────────────────────────────

    /**
     * A CATALOGUE OF ROWS ONLY. The registry's ModuleCatalogue intersects rows with
     * registered providers, so this kernel registers the matching providers —
     * see {@see WebKernel}. What is asserted is this bundle's reading of the
     * ledger, not the registry's own catalogue rules.
     */
    private function aCatalogue(): void
    {
        foreach ([
            ['patrols', 'Patrols', ModuleCategory::Pressure, ModuleStatus::Live, 'GPS field tracks'],
            ['incidents', 'Incidents', ModuleCategory::Pressure, ModuleStatus::Live, 'field reports'],
            ['forest-loss', 'Forest loss', ModuleCategory::Flux, ModuleStatus::Template, 'Hansen GFC'],
        ] as $i => [$slug, $name, $category, $status, $source]) {
            $this->em->persist(new Module()
                ->setSlug($slug)
                ->setName($name)
                ->setCategory($category)
                ->setStatus($status)
                ->setDataSource($source)
                ->setPosition($i));
        }
        $this->em->flush();
    }

    /**
     * A `module` row with no provider behind it, switched on for this area — the
     * state an installation is in the moment it removes a module bundle. Written
     * directly because the registry's own install() refuses a slug the catalogue
     * cannot see, which is the rule being relied on here.
     */
    private function aGhostRow(AreaOfInterest $area): void
    {
        $ghost = new Module()
            ->setSlug('ghost')
            ->setName('Ghost')
            ->setCategory(ModuleCategory::Operations)
            ->setStatus(ModuleStatus::Live)
            ->setDataSource('a bundle that was uninstalled')
            ->setPosition(9);
        $this->em->persist($ghost);
        $this->em->persist(new \Uhifadhi\Bundle\RegistryBundle\Entity\AreaModule()->setArea($area)->setModule($ghost)->setActive(true)->setPosition(9));
        $this->em->flush();
    }

    private function install(AreaOfInterest $area, string $slug): void
    {
        /** @var \Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleService $modules */
        $modules = static::getContainer()->get('test_public.registry.area_modules');
        $modules->install($area, $slug);
    }

    /** @return list<string> */
    private function activeSlugs(AreaOfInterest $area): array
    {
        $this->em->clear();
        $fresh = $this->em->getRepository(AreaOfInterest::class)->find((int) $area->getId());
        self::assertInstanceOf(AreaOfInterest::class, $fresh);

        /** @var \Uhifadhi\Bundle\RegistryBundle\Service\AreaModuleService $modules */
        $modules = static::getContainer()->get('test_public.registry.area_modules');

        return array_map(
            static fn (\Uhifadhi\Bundle\RegistryBundle\Entity\AreaModule $am): string => (string) $am->getModule()?->getSlug(),
            $modules->activeFor($fresh),
        );
    }

    private function modulesPath(AreaOfInterest $area): string
    {
        return '/areas/'.$area->getUuidString().'/modules';
    }

    private function get(string $path): Response
    {
        $this->browser()->request('GET', $path);

        return $this->browser()->getResponse();
    }

    private function body(string $path): string
    {
        return (string) $this->get($path)->getContent();
    }

    /** @param array<string, string|list<string>> $parameters */
    private function post(string $path, array $parameters): Response
    {
        // The token the screen mints for this area, read back from the page the
        // control lives on rather than generated here: a test that mints its own
        // proves nothing about the form.
        $parameters['_token'] = $this->tokenOn($path);
        $this->browser()->request('POST', $path, $parameters);

        return $this->browser()->getResponse();
    }

    private function tokenOn(string $path): string
    {
        $customize = substr($path, 0, (int) strrpos($path, '/'));
        preg_match('#name="_token" value="([^"]+)"#', $this->body($customize), $m);

        return $m[1] ?? '';
    }
}
