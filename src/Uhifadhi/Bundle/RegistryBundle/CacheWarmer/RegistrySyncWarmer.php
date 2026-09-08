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

namespace Uhifadhi\Bundle\RegistryBundle\CacheWarmer;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Uhifadhi\Bundle\RegistryBundle\Service\RegistrySyncService;

/**
 * THE ONCE-PER-DEPLOY HOOK. The core ships no console command — devkit owns
 * commands — so reconciling the registry with the installed module providers
 * hangs off the one thing Symfony runs exactly when a deploy happens:
 *
 *   "[Cache warmers are executed] when you run the cache:warmup command, when
 *    you run the cache:clear command (unless you pass --no-warmup), [and] when
 *    handling a request, if it wasn't done by one of the commands yet."
 *   — https://symfony.com/doc/current/reference/dic_tags.html#kernel-cache-warmer
 *
 * That is strictly better than a `kernel.request` listener, which would ask the
 * same question on every request forever to answer it once.
 *
 * Two decisions here, each with a vendor warmer to read alongside it:
 *
 *   the collaborator is fetched through a service locator rather than injected
 *   ("dependencies should be lazy-loaded, that's why a container should be
 *   injected") — a warmer must not force an entity manager to be built on every
 *   container boot;
 *
 *   @see vendor/symfony/framework-bundle/CacheWarmer/TranslationsCacheWarmer.php
 *
 *   and what it cannot do, it declines to do rather than fataling, per mapped
 *   class.
 *   @see vendor/symfony/framework-bundle/CacheWarmer/ValidatorCacheWarmer.php
 *
 * The second one is the load-bearing half here: `cache:clear` runs on a fresh
 * installation BEFORE the first migration, so this warmer routinely meets a
 * database with no registry tables — or, in a build container, no database at
 * all. Either must leave `cache:clear` working. The schema check lives in
 * {@see RegistrySyncService::sync()}; the catch below is the outer guard for
 * everything else a connection can do.
 */
final class RegistrySyncWarmer implements CacheWarmerInterface, ServiceSubscriberInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        try {
            $sync = $this->container->get(RegistrySyncService::class);
            \assert($sync instanceof RegistrySyncService);

            $sync->sync();
        } catch (\Throwable) {
            // An unreachable database is not a broken cache. The registry is
            // reconciled on the next warm-up, which a migration is followed by.
        }

        // Rows, not files: there is nothing to preload.
        return [];
    }

    /**
     * NOT OPTIONAL. The documented rule is that "a warmer should return true if
     * the cache can be generated incrementally and on-demand" — this one cannot.
     * An installation whose registry was never reconciled has an empty
     * catalogue and every module page answers 404, so `--no-optional-warmers`
     * must not skip it.
     *
     * @see https://symfony.com/doc/current/reference/dic_tags.html#kernel-cache-warmer
     */
    public function isOptional(): bool
    {
        return false;
    }

    public static function getSubscribedServices(): array
    {
        return [
            RegistrySyncService::class,
        ];
    }
}
