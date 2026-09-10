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

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use FundiStadi\PostGISBundle\FundiStadiPostGISBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\Icons\UXIconsBundle;
use Symfony\UX\Map\UXMapBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Uhifadhi\Bundle\AreaBundle\AreaBundle;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\CheckoutTempDirTrait;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web\Fixtures\HostUser;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web\Fixtures\PatrolsModuleTabs;
use Uhifadhi\Bundle\AtlasBundle\AtlasBundle;
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * AN INSTALLATION WITH SCREENS — the same minimal kernel as
 * {@see \Uhifadhi\Bundle\AreaBundle\Tests\Integration\TestKernel}, plus the three things a
 * page needs: twig to render in, security to be gated by, and the shell to be
 * framed by.
 *
 * THE SHELL IS HERE BUT IT IS A `suggest`, NOT A `require`, and that asymmetry
 * is the point. These screens render in the shell's frame when an installation
 * has one and render unframed when it does not, so the suite has to be able to
 * boot BOTH — this kernel is the framed half, and
 * {@see \Uhifadhi\Bundle\AreaBundle\Tests\Integration\TestKernel} is still the bare one.
 *
 * THE FIREWALL IS REAL. The permissions these screens are gated on are answered
 * by TeamBundle's voter in a real installation, and team is not a
 * dependency of this one. So the suite ships its own voter over the same
 * permission strings: what is being tested here is that the screens ASK, not
 * what somebody else answers.
 */
final class WebKernel extends Kernel
{
    /*
     * MicroKernelTrait, not a hand-rolled Kernel: it is what tags the `kernel`
     * service as a route loader and points framework.router at
     * `kernel::loadRoutes`. Without it configureRoutes() below is never called
     * and every route in this suite silently does not exist.
     */
    use CheckoutTempDirTrait;
    use MicroKernelTrait;

    /** @var list<string> */
    public array $grants = [];

    /** @param list<string> $grants */
    public function __construct(array $grants = [])
    {
        $this->grants = $grants;
        // The cache is keyed by what the viewer holds: two kernels with
        // different grants must not share a compiled container.
        parent::__construct('test'.md5(implode(',', $grants)), true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
        yield new FundiStadiPostGISBundle();
        yield new RegistryBundle();
        // The shell's frame draws its icons with ux_icon(); it is a hard
        // requirement of the shell, so an installation that has the shell has it.
        yield new UXIconsBundle();
        yield new StimulusBundle();
        // UX Map and its Leaflet bridge: the atlas's plate is built on them,
        // and every area screen that draws a map renders through them.
        yield new UXMapBundle();
        yield new ShellBundle();
        // The atlas: these pages link its map sheet by the constant it
        // publishes and render their plates through it, so an installation that
        // draws an area's boundary has it and so does this kernel.
        yield new AtlasBundle();
        yield new AreaBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'php_errors' => ['log' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            // The module shop WRITES, so every control on it carries a token
            // and the screen refuses a post without one.
            'csrf_protection' => true,
            // The shell's document links its own stylesheet through asset(), and
            // renders the application's importmap. AssetMapper takes over path
            // resolution here, so the hrefs come out content-digested exactly as
            // they do in a real installation — which is why the assertions match
            // a stem rather than a literal filename.
            'assets' => [],
            'asset_mapper' => [
                'paths' => [__DIR__.'/Fixtures/app/assets' => ''],
            ],
        ]);

        /*
         * THE ICONS ARE LOCAL, and they come from the shell: `shell:` is an
         * icon SET the shell registers, and a set is answered only from its own
         * directory. So this kernel keeps an empty icon directory of its own —
         * an application always has one — and on-demand fetching is off, which
         * is what makes the suite prove that the screens render rather than
         * that the machine has internet.
         */
        $container->extension('ux_icons', [
            'icon_dir' => __DIR__.'/icons',
            'iconify' => ['on_demand' => false],
        ]);

        // STRICT: a template that reads a variable the controller did not pass
        // fails here rather than rendering a blank cell in an installation.
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('ux_map', ['renderer' => 'leaflet://default']);

        $container->extension('doctrine', [
            'dbal' => ['url' => '%env(UHIFADHI_TEST_DATABASE_URL)%'],
            'orm' => [
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore',
                /*
                 * THE ONE LINE AN INSTALLATION WRITES, WRITTEN HERE. The shell
                 * keeps a widget layout per person and points it at the
                 * contracts' UserInterface without resolving it; whoever owns
                 * the account class states the resolution, and this bundle is
                 * not that package. So this kernel plays the installation's
                 * end of it with {@see HostUser}, and nothing here depends on
                 * a sibling bundle to build a schema.
                 */
                'resolve_target_entities' => [
                    UserInterface::class => HostUser::class,
                ],
                'mappings' => [
                    'AreaTestHost' => [
                        'type' => 'attribute',
                        'dir' => __DIR__.'/Fixtures',
                        'prefix' => 'Uhifadhi\\Bundle\\AreaBundle\\Tests\\Integration\\Web\\Fixtures',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        $container->extension('security', [
            'providers' => ['in_memory' => ['memory' => ['users' => ['ranger' => ['password' => 'x', 'roles' => ['ROLE_USER']]]]]],
            'firewalls' => ['main' => ['pattern' => '^/', 'security' => false]],
        ]);

        $services = $container->services();

        /*
         * A CATALOGUE WITH BUNDLES BEHIND IT. The registry's catalogue is the
         * intersection of `module` rows and REGISTERED PROVIDERS, so rows alone
         * would read as an empty catalogue. These stand in for module bundles
         * this one must never depend on — see InstallableModule.
         *
         * `patrols` carries an entry route this kernel actually serves, so its
         * tile is a link; `forest-loss` carries none, so its tile is inert. Both
         * halves of that rule are asserted.
         */
        /*
         * A MODULE'S OWN DATA PLACES, STOOD IN FOR — what fills the sidebar's
         * fourth rung. Tagged by hand, exactly as a reusable module bundle must.
         */
        $services->set(PatrolsModuleTabs::class)
            ->tag('uhifadhi.module_tabs');

        foreach ([
            ['patrols', 'Patrols', 'pressure', 'live', 'GPS field tracks', 'test_module_entry'],
            ['incidents', 'Incidents', 'pressure', 'live', 'field reports', null],
            ['forest-loss', 'Forest loss', 'flux', 'template', 'Hansen GFC', null],
        ] as $i => $module) {
            $services->set('test.module.'.$module[0], InstallableModule::class)
                ->args($module)
                ->tag('uhifadhi.module');
        }

        /*
         * A MODULE'S LAYERS ON THE AREA PAGE'S PLATE, STOOD IN FOR. The overview
         * gathers map layers from every module switched on in the area, through
         * the `uhifadhi.map.layer` contribution — patrol tracks, incident points — and
         * this bundle must depend on none of them. These fakes contribute over
         * the same contribution so the suite can prove the area page draws a contributed
         * layer's geometry and its legend group where its module is on, and
         * leaves both out where it is off.
         */
        $services->set('test.map_layers.patrols', FakeMapLayers::class)
            ->args(['patrols', 'Patrols'])
            ->tag('uhifadhi.map.layer');
        $services->set('test.map_layers.incidents', FakeMapLayers::class)
            ->args(['incidents', 'Incidents'])
            ->tag('uhifadhi.map.layer');

        /*
         * A MODULE'S REGISTER-CARD FIGURES, STOOD IN FOR. The overview contributions the
         * register card reads its operational figures through — now-tiles (its
         * stat cells and "out right now" chip), attention (its alert flag) and
         * pulse (its "last check-in") — are the same contributions the overview draws
         * from, and this bundle depends on no real module. These fakes contribute
         * over those contributions for `patrols` so the suite can prove the area page lays out
         * a contributed figure on a card where its module is on, and shows the
         * boundary-only card where it is off.
         */
        $services->set('test.now_tiles.patrols', FakeNowTiles::class)
            ->args(['patrols'])
            ->tag('uhifadhi.overview.now_tile');
        $services->set('test.attention.patrols', FakeAttention::class)
            ->args(['patrols'])
            ->tag('uhifadhi.overview.attention');
        $services->set('test.pulse.patrols', FakePulse::class)
            ->args(['patrols'])
            ->tag('uhifadhi.overview.pulse');

        // The suite's own voter, standing in for TeamBundle's. It answers
        // the same permission strings, which is the whole of what these screens
        // depend on.
        $services->set(GrantedPermissions::class)
            ->args([$this->grants])
            ->tag('security.voter')
            ->public();

        $services->alias('test_public.area.shell_source', 'area.shell_source')->public();
        // The ledger's writer, so a test can arrange an area's composition the
        // same way the screen does rather than inserting rows behind it.
        $services->alias('test_public.registry.area_modules', 'registry.area_modules')->public();
        $services->alias('test_public.area.navigation', 'area.navigation')->public();
        $services->alias('test_public.event_dispatcher', 'event_dispatcher')->public();
        $services->alias('test_public.token_storage', 'security.token_storage')->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        /*
         * WHAT THE RECIPE MOUNTS. An installation imports these from
         * config/routes/area.yaml and may prefix or remove them; the suite
         * mounts them where the recipe does, so a url asserted here is the url
         * an installation serves.
         */
        $routes->import(
            ['path' => \dirname(__DIR__, 3).'/Controller/', 'namespace' => 'Uhifadhi\Bundle\AreaBundle\Controller'],
            'attribute',
        );

        /*
         * THE SHELL'S CONFIGURE PAGE, mounted as an installation mounts it. The
         * area's one configuration entry leads here, so a suite that did not
         * mount it would be asserting a `Configure` action that goes nowhere.
         */
        $routes->import(ShellBundle::CONFIGURE_ROUTES);

        /*
         * A MODULE'S OWN PAGE, STOOD IN FOR. A tile links where the registry's entry
         * resolver names a route the application actually mounted; in a real
         * installation that is the patrol module's dashboard. This suite mounts
         * one route with that shape so the linked and the inert tile can both be
         * asserted without depending on a module bundle.
         */
        $routes->add('test_module_entry', '/areas/{uuid}/modules/patrols')
            ->controller('kernel::moduleEntry');

        // The module's second data place, so the fourth rung of the tree has
        // more than one rung to be.
        $routes->add('test_module_list', '/areas/{uuid}/modules/patrols/patrols')
            ->controller('kernel::moduleEntry');
    }

    public function moduleEntry(): Response
    {
        return new Response('a module page');
    }

    /**
     * THE STAND-IN HOST'S PROJECT DIRECTORY — a Flex-installed application's
     * asset side and nothing else. The shell's document renders the importmap
     * of whatever application it is installed in, so a suite that renders a
     * page through the page frame needs an application that has one. Pointing
     * the kernel at a fixture is how it gets one without this bundle growing an
     * importmap of its own, which a shipped bundle has no business carrying.
     */
    public function getProjectDir(): string
    {
        return __DIR__.'/Fixtures/app';
    }

    public function getCacheDir(): string
    {
        return $this->checkoutTempDir('area/web-cache/'.$this->getEnvironment());
    }

    public function getLogDir(): string
    {
        return $this->checkoutTempDir('area/web-log');
    }
}
