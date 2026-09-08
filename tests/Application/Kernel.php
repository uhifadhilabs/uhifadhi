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

namespace Uhifadhi\Core\Tests\Application;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Bundle\TeamBundle\Entity\User;

/**
 * THE THROWAWAY APPLICATION the core's own functional tests run inside.
 *
 * It exists so a specification can ask what a real installation does — every
 * core bundle in one kernel, one database, one router — without any repository
 * outside this one being involved. It is export-ignored: nobody installs it.
 *
 * It sits at the monorepo root rather than inside one bundle because the
 * bundles here are released together and their integration IS the product.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        /** @var array<class-string<BundleInterface>, array<string, bool>> $contents */
        $contents = require __DIR__.'/config/bundles.php';

        foreach ($contents as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/uhifadhi-core-tests/application/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/uhifadhi-core-tests/application/log';
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
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);

        $container->extension('doctrine', [
            'dbal' => ['url' => '%env(UHIFADHI_TEST_DATABASE_URL)%'],
            'orm' => ['controller_resolver' => ['auto_mapping' => false]],
        ]);

        // The security file an installation gets from the skeleton, as this
        // throwaway application's own: the hashers, the entity provider over
        // the account TeamBundle owns, the checker that refuses a deactivated
        // one, the web firewall on the sign-in routes, and the two /api
        // firewalls the field client meets.
        $container->extension('security', [
            'password_hashers' => [
                PasswordAuthenticatedUserInterface::class => [
                    'algorithm' => 'auto',
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
                'main' => [
                    'lazy' => true,
                    'provider' => 'team_user_provider',
                    'user_checker' => 'team.user_checker',
                    'form_login' => [
                        'login_path' => 'team_login',
                        'check_path' => 'team_login',
                        'enable_csrf' => true,
                        'default_target_path' => '/',
                    ],
                    'logout' => ['path' => 'team_logout', 'target' => 'team_login'],
                    'remember_me' => ['secret' => '%kernel.secret%', 'lifetime' => 604800],
                ],
            ],
            'role_hierarchy' => [
                'ROLE_ADMIN' => ['ROLE_USER'],
                'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'],
            ],
            'access_control' => [
                ['path' => '^/login', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/reset-password', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/invite/', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/api/auth/token$', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/api', 'roles' => 'ROLE_USER'],
                ['path' => '^/', 'roles' => 'ROLE_USER'],
            ],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // The shell ships its welcome route as a RESOURCE it never loads; an
        // application imports it, or does not, and owns the address either way.
        // This one does, because a throwaway app with no route at all cannot
        // answer whether the core serves a page.
        $routes->import(ShellBundle::ROUTES);

        // Every screen TeamBundle draws, mounted where an installation's own
        // config/routes/team.yaml mounts it.
        $routes->import('@TeamBundle/Controller/', 'attribute');
    }
}
