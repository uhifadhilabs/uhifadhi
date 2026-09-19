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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use FundiStadi\PostGISBundle\FundiStadiPostGISBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Uhifadhi\Bundle\AreaBundle\AreaBundle;
use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneRepository;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\Fixtures\CollectedModules;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\Fixtures\HostPerson;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\Fixtures\TaggedFigureProvider;
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;
use Uhifadhi\Contracts\Entity\UserInterface;
use Uhifadhi\Contracts\Kpi\ZoneFigureProviderInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * AN INSTALLATION, MINIMALLY — framework, doctrine, PostGIS, the registry and this
 * bundle, against a REAL PostGIS database (UHIFADHI_TEST_DATABASE_URL, see
 * phpunit.dist.xml).
 *
 * THE REGISTRY IS HERE ON PURPOSE, and it is the whole point of the suite. The registry
 * owns a per-area table with a NOT NULL foreign key to an area it does not
 * define; every installation that has ever carried it has had to answer that
 * association by hand. This kernel answers it with NOTHING: there is no
 * `resolve_target_entities` in `configureContainer()`, and the schema still
 * builds. If the prepend in {@see AreaBundle::prependExtension()} ever
 * stopped happening, this kernel would fail to produce a schema at all and most
 * of the suite would go red at once.
 *
 * PostGIS IS NOT OPTIONAL HERE either: the boundary is a multipolygon column, so
 * a kernel that dropped the bundle would fail at CREATE TABLE and prove nothing
 * about what the bundle actually stores.
 */
class TestKernel extends Kernel
{
    use CheckoutTempDirTrait;

    public function __construct()
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new FundiStadiPostGISBundle();
        yield new RegistryBundle();
        yield new AreaBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'php_errors' => ['log' => true],
        ]);

        $container->extension('doctrine', [
            'dbal' => ['url' => '%env(UHIFADHI_TEST_DATABASE_URL)%'],
            'orm' => [
                // The skeleton's own choice, mirrored here so the bundle's
                // metadata-driven SQL meets the column names it will actually
                // meet in an installation.
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore',
                // NO `mappings` FOR THIS BUNDLE and NO `resolve_target_entities`
                // FOR THE AREA CONTRACT, deliberately: both are the bundle's own
                // prepend, and an installation writes neither.
                //
                // THE USER CONTRACT IS THE OTHER WAY ROUND. A posting points at
                // a person, and who a person IS belongs to the team bundle,
                // which this kernel deliberately does not install — so the
                // installation's end of that contract is answered here with
                // {@see HostPerson}, exactly as a real installation answers it.
                // That the area can hold a posting without team is the point.
                'resolve_target_entities' => [
                    UserInterface::class => HostPerson::class,
                ],
                'mappings' => [
                    'AreaTestPeople' => [
                        'type' => 'attribute',
                        'dir' => __DIR__.'/Fixtures',
                        'prefix' => 'Uhifadhi\\Bundle\\AreaBundle\\Tests\\Integration\\Fixtures',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        $services = $container->services();

        // The repository is private, as a reusable bundle's services should be.
        // One public alias so the suite can reach it, keyed by service id.
        $services->alias('test_public.area.repository', AreaOfInterestRepository::class)->public();
        $services->alias('test_public.area.zones', 'area.zones')->public();
        $services->alias('test_public.area.zone_set', 'area.zone_set')->public();
        $services->alias('test_public.area.zone_figures', 'area.zone_figures')->public();
        $services->alias('test_public.area.stations', 'area.stations')->public();
        $services->alias('test_public.area.postings', 'area.postings')->public();

        /*
         * A MODULE'S ZONE-FIGURE PROVIDER, tagged BY HAND exactly as a real
         * module tags its own — a reusable bundle is not autoconfigured, so a
         * fixture that relied on autoconfiguration would prove a wiring no
         * installation uses.
         */
        $services->set(TaggedFigureProvider::class)
            ->tag(ZoneFigureProviderInterface::TAG);
        $services->alias('test_public.area.zone_import', 'area.zone_import')->public();
        $services->alias('test_public.area.zone_export', 'area.zone_export')->public();
        $services->alias('test_public.area.zone_events', 'area.zone_events')->public();
        $services->alias('test_public.area.zone_repository', ZoneRepository::class)->public();

        // Stands in for the registry's catalogue. It is here to record an ABSENCE —
        // see CatalogueAbstentionTest for why an area is not a module of itself.
        $services->set(CollectedModules::class)
            ->args([tagged_iterator('uhifadhi.module')])
            ->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // This kernel mounts no screens: it is the bare installation, the one
        // that carries the model and renders nothing. The screens have their own
        // kernel — see Web\WebKernel.
    }

    public function getCacheDir(): string
    {
        return $this->checkoutTempDir('area/cache');
    }

    public function getLogDir(): string
    {
        return $this->checkoutTempDir('area/log');
    }
}
