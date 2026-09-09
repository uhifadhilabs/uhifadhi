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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Integration;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\Icons\UXIconsBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;
use Uhifadhi\Bundle\RegistryBundle\Security\ApiTokenAuthenticator;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\TeamBundle;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\DeclaringModuleProvider;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\GuardedController;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\ShellPageController;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\SilentModuleProvider;

/**
 * The smallest host this bundle can live in: framework + twig + doctrine +
 * security + the shell the sign-in screen renders through, talking to a REAL
 * database (UHIFADHI_TEST_DATABASE_URL, see phpunit.dist.xml).
 *
 * THE SECURITY CONFIG HERE MIRRORS THE FILE THE SKELETON SHIPS. What this
 * kernel writes under `security:` is what an installation gets in its own
 * config/packages/security.yaml — the password hashers, the entity provider,
 * the user checker, the web firewall pointing at this bundle's routes, the two
 * `/api` firewalls and the access ladder. The two are kept in step
 * deliberately: a test kernel that invented its own firewall would prove the
 * bundle works in a shape no installation has.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new StimulusBundle();
        yield new UXIconsBundle();
        yield new DoctrineBundle();
        yield new SecurityBundle();
        yield new RegistryBundle();
        yield new ShellBundle();
        yield new TeamBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'router' => ['utf8' => true],
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            // A form login needs a session and a CSRF token manager, exactly as
            // a real host has them.
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection' => ['enabled' => true],
            // asset() has to exist, because both the shell's document and this
            // module's own page link a stylesheet with it. AssetMapper is a dev
            // dependency of this bundle and takes over path resolution here, so
            // the hrefs come out content-digested exactly as they do in a real
            // installation — which is why the assertions match a stem and not a
            // literal filename.
            'assets' => true,
            'asset_mapper' => [
                'paths' => [__DIR__.'/Fixtures/app/assets' => ''],
            ],
        ]);

        $container->extension('security', [
            'password_hashers' => [
                'Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface' => [
                    'algorithm' => 'auto',
                    // Test-only cost floor, the documented Symfony practice.
                    'cost' => 4,
                    'time_cost' => 3,
                    'memory_cost' => 10,
                ],
            ],
            'providers' => [
                'team_user_provider' => [
                    'entity' => ['class' => User::class, 'property' => 'email'],
                ],
            ],
            'firewalls' => [
                /*
                 * WHERE A FIELD CLIENT SIGNS IN, deliberately firewall-free: a
                 * handset whose token has expired still holds it and still
                 * sends it, and that stale header must never be what stops
                 * somebody signing in again. The endpoint checks the
                 * credentials itself.
                 */
                'api_auth' => [
                    'pattern' => '^/api/auth/token$',
                    'security' => false,
                ],
                /*
                 * THE MACHINE DOOR: bearer tokens, no session, no form, no
                 * remembering. Stateless is not an optimisation — a client
                 * syncs in bursts of hundreds after hours offline, and a
                 * session per burst would be a lie about a conversation that is
                 * not happening. The entry point is what makes "no token at
                 * all" a 401 rather than a 403.
                 */
                'api' => [
                    'pattern' => '^/api',
                    'stateless' => true,
                    'provider' => 'team_user_provider',
                    'user_checker' => 'team.user_checker',
                    'custom_authenticators' => [ApiTokenAuthenticator::class],
                    'entry_point' => ApiTokenAuthenticator::class,
                ],
                'main' => [
                    'lazy' => true,
                    'provider' => 'team_user_provider',
                    // Exactly as the README tells an installation to write it: a
                    // deactivated account is refused at the door, with a reason.
                    'user_checker' => 'team.user_checker',
                    'form_login' => [
                        'login_path' => 'team_login',
                        'check_path' => 'team_login',
                        'enable_csrf' => true,
                        'default_target_path' => '/',
                    ],
                    'logout' => [
                        'path' => 'team_logout',
                        'target' => 'team_login',
                    ],
                    // A rule the ladder below admits on a remembered token is
                    // only honestly exercised by a firewall that can issue one.
                    'remember_me' => [
                        'secret' => '%kernel.secret%',
                        'lifetime' => 604800,
                        'always_remember_me' => false,
                    ],
                ],
            ],
            // THE TIER LADDER, and only it. A tier is a coarse standing an
            // installation's own rules may name; the granular permissions a
            // position grants are decided by a voter, never by a role.
            'role_hierarchy' => [
                'ROLE_ADMIN' => ['ROLE_USER'],
                'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'],
            ],
            // DEFAULT-CLOSED: the paths a stranger has to reach are named, and
            // the catch-all shuts everything else. `/_guarded` needs no rule of
            // its own — the catch-all is what guards it, which is the point.
            'access_control' => [
                ['path' => '^/login', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/reset-password', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/invite/', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/api/auth/token$', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/api', 'roles' => 'ROLE_USER'],
                ['path' => '^/', 'roles' => 'ROLE_USER'],
            ],
        ]);

        $container->extension('doctrine', [
            'dbal' => ['url' => '%env(UHIFADHI_TEST_DATABASE_URL)%'],
            'orm' => [
                // The skeleton's own choice, mirrored so the bundle's SQL is
                // exercised against the column names it will actually meet.
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore',
                // NO resolve_target_entities FOR THE USER CONTRACT HERE,
                // DELIBERATELY. The bundle prepends it, and this kernel is the
                // proof: the shell keeps a widget layout per PERSON and points
                // at the contract to do it, so if the prepend ever stopped
                // happening the schema would stop before it reached a single
                // team_ table and every test in this suite would say so at once.
                //
                // THE AREA CONTRACT IS THE HOST'S TO ANSWER, so this kernel — a
                // host, minimally — answers it, exactly as a real installation
                // does through AreaBundle. A department carries a
                // nullable area, so its metadata cannot be built until the
                // platform's AreaInterface (the contracts) resolves to a concrete entity. This bundle never
                // resolves it itself; it only points at it.
                'resolve_target_entities' => [
                    \Uhifadhi\Contracts\Entity\AreaInterface::class => Fixtures\Area\HostArea::class,
                ],
                'mappings' => [
                    'TeamTestArea' => [
                        'type' => 'attribute',
                        'dir' => __DIR__.'/Fixtures/Area',
                        'prefix' => 'Uhifadhi\\Bundle\\TeamBundle\\Tests\\Integration\\Fixtures\\Area',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        // No extra twig paths: every template this bundle renders is its own,
        // reached through the @Team namespace the bundle registers.

        $container->extension('ux_icons', [
            'icon_dir' => __DIR__.'/Fixtures/icons',
            'ignore_not_found' => true,
        ]);

        // A module standing in for every installed module bundle: it DECLARES a
        // permission, which the catalogue must fold in beside the core seven.
        $container->services()
            ->set(DeclaringModuleProvider::class)
            ->tag('uhifadhi.module');

        // And one that declares NOTHING, which is what most modules do. The
        // matrix has to draw it rather than skip it, so the catalogue has to
        // know it is there.
        $container->services()
            ->set(SilentModuleProvider::class)
            ->tag('uhifadhi.module');

        // The thing behind the firewall (see configureRoutes).
        $container->services()->set(GuardedController::class)->public();

        // A page in the shell's frame that is NOT this bundle's, so the sidebar
        // suite can ask what a viewer sees from somewhere else.
        $container->services()->set(ShellPageController::class)
            ->args([new Reference('twig')])
            ->public();

        // The framework's own hasher, made reachable: a suite proving a stored
        // password verifies has to use the same service the firewall does.
        $container->services()->alias('test_public.hasher', 'security.user_password_hasher')->public();

        // Public aliases so a test can hold the bundle's private services.
        foreach ([
            \Uhifadhi\Bundle\TeamBundle\Service\PermissionCatalogue::class => 'team.permissions',
            \Uhifadhi\Bundle\TeamBundle\Security\PermissionVoter::class => 'team.permission_voter',
            \Uhifadhi\Bundle\TeamBundle\ArgumentResolver\AreaValueResolver::class => 'team.area_value_resolver',
            \Uhifadhi\Bundle\TeamBundle\Repository\UserRepository::class => \Uhifadhi\Bundle\TeamBundle\Repository\UserRepository::class,
            \Uhifadhi\Bundle\TeamBundle\Repository\PositionRepository::class => \Uhifadhi\Bundle\TeamBundle\Repository\PositionRepository::class,
            \Uhifadhi\Bundle\TeamBundle\Repository\DepartmentRepository::class => \Uhifadhi\Bundle\TeamBundle\Repository\DepartmentRepository::class,
            \Uhifadhi\Bundle\TeamBundle\Repository\DepartmentScopeChangeRepository::class => \Uhifadhi\Bundle\TeamBundle\Repository\DepartmentScopeChangeRepository::class,
            \Uhifadhi\Bundle\TeamBundle\Repository\ApiTokenRepository::class => \Uhifadhi\Bundle\TeamBundle\Repository\ApiTokenRepository::class,
            \Uhifadhi\Bundle\TeamBundle\Service\ApiTokenManager::class => 'team.api_token.manager',
            \Uhifadhi\Bundle\TeamBundle\Service\SuperAdminInvariant::class => 'team.super_admin_invariant',
            \Uhifadhi\Bundle\TeamBundle\Service\TeamOverview::class => 'team.overview',
            \Uhifadhi\Bundle\ShellBundle\Widget\Registry\WidgetSurfaceRegistry::class => 'shell.widget.surfaces',
        ] as $class => $serviceId) {
            $container->services()->alias('test_public.'.$class, $serviceId)->public();
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // Mounted exactly as the recipe's config/routes/team.yaml mounts it.
        $routes->import('@TeamBundle/Controller/', 'attribute');

        // Something behind the firewall, so "an anonymous visitor is sent to
        // /login" is a fact this suite can assert rather than assume.
        $routes->add('guarded', '/_guarded')
            ->controller(GuardedController::class);

        // The same thing behind the API firewall, so "a bearer token reaches
        // what a session reaches" is a fact this suite can assert.
        $routes->add('api_guarded', '/api/_guarded')
            ->controller(GuardedController::class);

        // Somewhere else in the shell, open to anybody, so the sidebar suite can
        // ask what an anonymous visitor and a colleague without team.manage see.
        $routes->add('elsewhere', '/_elsewhere')
            ->controller(ShellPageController::class);

        // The front door every installation has (the skeleton points `/` at the
        // shell's welcome page). It exists here because the firewall's
        // default_target_path sends a fresh sign-in to it, and a redirect to
        // nowhere would make the suite prove nothing.
        $routes->add('home', '/')->controller(GuardedController::class);
    }

    /**
     * THE STAND-IN HOST'S PROJECT DIRECTORY — a Flex-installed application's
     * asset side and nothing else. The shell's document renders the importmap
     * of whatever application it is installed in, so a suite that renders any
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
        return sys_get_temp_dir().'/uhifadhi-core-tests/team/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/uhifadhi-core-tests/team/log';
    }
}
