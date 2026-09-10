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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Unit\Frame;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Uhifadhi\Bundle\ShellBundle\Frame\Controller\ConfigureController;
use Uhifadhi\Bundle\ShellBundle\Frame\Registry\ConfigurationSectionsRegistry;
use Uhifadhi\Bundle\ShellBundle\Frame\Registry\ModuleTabsRegistry;
use Uhifadhi\Bundle\ShellBundle\Frame\Service\ModuleFrameService;
use Uhifadhi\Bundle\ShellBundle\Model\AreaTab;
use Uhifadhi\Bundle\ShellBundle\Service\AreaShell;
use Uhifadhi\Contracts\Shell\ConfigurationSection;
use Uhifadhi\Contracts\Shell\ConfigurationSectionsInterface;
use Uhifadhi\Contracts\Shell\ModuleTab;
use Uhifadhi\Contracts\Shell\ModuleTabsInterface;

/**
 * ONE STRIP, ONE POSITION, AND THE REQUEST DECIDES WHAT IS IN IT.
 *
 * These are the frame's rulings stated as behaviour: a module's page shows the
 * module's data places, a configure page shows that surface's sections INSTEAD
 * of the data tabs, everything else falls back to the area's own screens, and
 * the one `Configure` control is lit exactly while you are configuring.
 */
final class ModuleFrameServiceTest extends TestCase
{
    private const string AREA = '0192f7a0-0000-7000-8000-000000000000';

    public function testAModulesOwnPageShowsTheModulesDataPlaces(): void
    {
        $frame = $this->frame($this->moduleRequest('patrol_dashboard'));

        self::assertSame(['Overview', 'Patrols'], $this->labels($frame->tabs()));
    }

    public function testExactlyTheTabTheViewerIsOnIsLit(): void
    {
        $lit = array_values(array_filter(
            $this->frame($this->moduleRequest('patrol_list'))->tabs(),
            static fn (AreaTab $tab): bool => $tab->current,
        ));

        self::assertCount(1, $lit);
        self::assertSame('Patrols', $lit[0]->label);
    }

    /**
     * A LIST AND ITS DETAIL SCREEN ARE ONE PLACE — the module said so, and the
     * strip stays lit on the tab that led there.
     */
    public function testADetailScreenKeepsItsListsTabLit(): void
    {
        $lit = array_values(array_filter(
            $this->frame($this->moduleRequest('patrol_detail'))->tabs(),
            static fn (AreaTab $tab): bool => $tab->current,
        ));

        self::assertSame(['Patrols'], $this->labels($lit));
    }

    /**
     * A MODULE ON A PAGE IT NEVER DECLARED SAYS NOTHING rather than the wrong
     * thing. A strip that lights nothing reads as a row of links to somewhere
     * else, which is worse than no strip.
     */
    public function testAModulePageOutsideEveryDeclaredPlaceGetsNoStrip(): void
    {
        self::assertSame([], $this->frame($this->moduleRequest('patrol_export'))->tabs());
    }

    /**
     * ON A CONFIGURE PAGE THE DATA TABS ARE NOT SHOWN. The section strip stands
     * exactly where they stand, in the same component and the same position.
     */
    public function testAConfigurePageShowsTheSectionsInsteadOfTheDataTabs(): void
    {
        $strip = $this->frame($this->configureRequest('patrols'))->tabs();

        self::assertSame(['Widget library', 'Observation kinds', 'Settings'], $this->labels($strip));
        self::assertNotContains('Patrols', $this->labels($strip));
    }

    /** The bare configure address is the surface's LAST rendered section. */
    public function testTheBareConfigureAddressOpensOnSettingsAndLightsIt(): void
    {
        $lit = array_values(array_filter(
            $this->frame($this->configureRequest('patrols'))->tabs(),
            static fn (AreaTab $tab): bool => $tab->current,
        ));

        self::assertSame(['Settings'], $this->labels($lit));
    }

    public function testTheNamedSectionIsTheOneLit(): void
    {
        $lit = array_values(array_filter(
            $this->frame($this->configureRequest('patrols', 'kinds'))->tabs(),
            static fn (AreaTab $tab): bool => $tab->current,
        ));

        self::assertSame(['Observation kinds'], $this->labels($lit));
    }

    public function testTheConfigureActionOpensTheConfigurePageFromAModulesOwnPage(): void
    {
        $action = $this->frame($this->moduleRequest('patrol_dashboard'))->configure();

        self::assertNotNull($action);
        self::assertFalse($action->current);
        self::assertSame('/areas/'.self::AREA.'/modules/patrols/configure', $action->url);
    }

    /**
     * ONE CONTROL, TWO STATES. On the configure page it is lit and goes back to
     * the module's first data place — so no module writes a "Back to dashboard"
     * of its own, and none has to.
     */
    public function testTheConfigureActionIsLitAndGoesBackWhileConfiguring(): void
    {
        $action = $this->frame($this->configureRequest('patrols'))->configure();

        self::assertNotNull($action);
        self::assertTrue($action->current);
        self::assertSame('/areas/'.self::AREA.'/modules/patrols', $action->url);
    }

    public function testAModuleThatDeclaredNoSectionsHasNoConfigureAction(): void
    {
        $frame = $this->frame($this->moduleRequest('patrol_dashboard', slug: 'sightings'));

        self::assertNull($frame->configure());
    }

    /**
     * A PAGE INSIDE NO MODULE FALLS BACK TO THE AREA'S OWN SCREENS, which is
     * every page the frame did not change.
     */
    public function testAPageInsideNoModuleKeepsTheAreasOwnStrip(): void
    {
        $request = Request::create('/areas/'.self::AREA);
        $request->attributes->set('_route', 'area_show');
        $request->attributes->set('uuid', self::AREA);

        $frame = $this->frame($request, areaTabs: [
            new AreaTab('Overview', '/areas/'.self::AREA, current: true),
            new AreaTab('Zones', '/areas/'.self::AREA.'/zones'),
        ]);

        self::assertSame(['Overview', 'Zones'], $this->labels($frame->tabs()));
    }

    /**
     * THE SIDEBAR ASKS ABOUT AREAS THE VIEWER IS NOT IN, so the module's places
     * are answered for a named area and only the one being viewed is lit.
     */
    public function testTheTreeCanAskAModulesPlacesForAnyArea(): void
    {
        $other = '0192f7a0-0000-7000-8000-00000000ffff';
        $tabs = $this->frame($this->moduleRequest('patrol_list'))->tabsOf('patrols', $other);

        self::assertSame(['Overview', 'Patrols'], $this->labels($tabs));
        self::assertSame('/areas/'.$other.'/modules/patrols', $tabs[0]->url);
    }

    /**
     * @param list<AreaTab> $tabs
     *
     * @return list<string>
     */
    private function labels(array $tabs): array
    {
        return array_map(static fn (AreaTab $tab): string => $tab->label, $tabs);
    }

    private function moduleRequest(string $route, string $slug = 'patrols'): Request
    {
        $request = Request::create('/areas/'.self::AREA.'/modules/'.$slug);
        $request->attributes->set('_route', $route);
        $request->attributes->set('uuid', self::AREA);
        $request->attributes->set(ModuleFrameService::MODULE_ROUTE_ATTRIBUTE, $slug);

        return $request;
    }

    private function configureRequest(string $slug, ?string $section = null): Request
    {
        $request = Request::create('/areas/'.self::AREA.'/modules/'.$slug.'/configure'.(null === $section ? '' : '/'.$section));
        $request->attributes->set('_route', ConfigureController::MODULE_ROUTE);
        $request->attributes->set('uuid', self::AREA);
        $request->attributes->set('slug', $slug);
        $request->attributes->set('section', $section);

        return $request;
    }

    /** @param list<AreaTab> $areaTabs */
    private function frame(Request $request, array $areaTabs = []): ModuleFrameService
    {
        $requests = new RequestStack();
        $requests->push($request);

        return new ModuleFrameService(
            $requests,
            $this->router(),
            new ModuleTabsRegistry([$this->tabs()]),
            new ConfigurationSectionsRegistry([$this->sections()]),
            new AreaShell(new class($areaTabs) implements \Uhifadhi\Bundle\ShellBundle\Contract\AreaShellSourceInterface {
                /** @param list<AreaTab> $tabs */
                public function __construct(private readonly array $tabs)
                {
                }

                public function tabs(): iterable
                {
                    return $this->tabs;
                }

                public function place(): ?string
                {
                    return null;
                }
            }),
            ConfigureController::AREA_ROUTE,
            ConfigureController::MODULE_ROUTE,
            ModuleFrameService::MODULE_ROUTE_ATTRIBUTE,
            ModuleFrameService::AREA_PARAMETER,
        );
    }

    private function tabs(): ModuleTabsInterface
    {
        return new class implements ModuleTabsInterface {
            public function slug(): string
            {
                return 'patrols';
            }

            public function tabs(): array
            {
                return [
                    new ModuleTab('Overview', 'patrol_dashboard'),
                    new ModuleTab('Patrols', 'patrol_list', lightsFor: ['patrol_list', 'patrol_detail']),
                ];
            }
        };
    }

    private function sections(): ConfigurationSectionsInterface
    {
        return new class implements ConfigurationSectionsInterface {
            public function slug(): string
            {
                return 'patrols';
            }

            public function heading(): string
            {
                return 'Patrols';
            }

            public function summary(): ?string
            {
                return null;
            }

            public function sections(): array
            {
                return [
                    ConfigurationSection::page('widgets', 'Widget library', '@Fixture/_widgets.html.twig'),
                    ConfigurationSection::page('kinds', 'Observation kinds', '@Fixture/_kinds.html.twig'),
                    ConfigurationSection::page('settings', 'Settings', '@Fixture/_settings.html.twig'),
                ];
            }
        };
    }

    /**
     * A REAL ROUTER OVER A HAND-BUILT COLLECTION, not a mock: what is being
     * specified includes the urls the frame generates, and a mock that returned
     * whatever it was told would specify nothing.
     */
    private function router(): RouterInterface
    {
        $routes = new RouteCollection();
        $routes->add('patrol_dashboard', new Route('/areas/{uuid}/modules/patrols'));
        $routes->add('patrol_list', new Route('/areas/{uuid}/modules/patrols/patrols'));
        $routes->add(ConfigureController::AREA_ROUTE, new Route('/areas/{uuid}/configure/{section}', ['section' => null]));
        $routes->add(ConfigureController::MODULE_ROUTE, new Route('/areas/{uuid}/modules/{slug}/configure/{section}', ['section' => null]));

        return new class($routes) implements RouterInterface {
            private UrlGenerator $generator;

            public function __construct(private readonly RouteCollection $routes)
            {
                $this->generator = new UrlGenerator($routes, new RequestContext());
            }

            public function setContext(RequestContext $context): void
            {
                $this->generator->setContext($context);
            }

            public function getContext(): RequestContext
            {
                return $this->generator->getContext();
            }

            public function getRouteCollection(): RouteCollection
            {
                return $this->routes;
            }

            /**
             * @param array<string, mixed> $parameters
             */
            public function generate(string $name, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
            {
                return $this->generator->generate($name, $parameters, $referenceType);
            }

            /**
             * @return array<string, mixed>
             */
            public function match(string $pathinfo): array
            {
                throw new \LogicException('The frame generates urls; it never matches one.');
            }
        };
    }
}
