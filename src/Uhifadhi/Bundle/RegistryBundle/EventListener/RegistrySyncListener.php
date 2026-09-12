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

namespace Uhifadhi\Bundle\RegistryBundle\EventListener;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Uhifadhi\Bundle\RegistryBundle\Service\RegistrySyncService;

/**
 * THE ONCE-PER-DEPLOY HOOK. There is no command to reconcile the registry with —
 * devkit owns every command the platform has bar the team bundle's first
 * administrator — so reconciling it with the installed module providers hangs
 * off the moments a deploy is made of: a console command has finished, or a
 * request has arrived. Whichever comes first does it, once per build.
 *
 * WHY NOT A CACHE WARMER, WHICH IS WHAT THIS LOOKS LIKE. Because a warmer that
 * reads the database breaks the cache commands themselves on a pristine prod
 * cache. The kernel warms the cache once while it compiles the container, and
 * that pass runs the NON-OPTIONAL warmers only:
 *
 *   "if ($cacheDir !== $buildDir) { $cacheWarmer->enableOptionalWarmers(); }"
 *
 *   @see vendor/symfony/http-kernel/Kernel.php — `initializeContainer()`
 *
 * doctrine-bundle's metadata warmer is optional, so it is absent from that pass
 * — and it refuses to build its PHP-array cache from a metadata factory
 * somebody else has already filled, which is fatal to the command that follows
 * in the same process:
 *
 *   "DoctrineMetadataCacheWarmer must load metadata first, check priority of
 *    your warmers."
 *   @see vendor/doctrine/doctrine-bundle/src/CacheWarmer/DoctrineMetadataCacheWarmer.php
 *
 * A priority cannot order two warmers that never run in the same pass, and no
 * reconciliation of a database can avoid loading the metadata of the entities it
 * writes. So the work leaves the warmer chain, and the framework's own rule —
 * warmers fill caches from static configuration, nothing else — is kept:
 *
 *   "A cache warmer processes and dumps the contents of a certain cache."
 *   @see https://symfony.com/doc/current/reference/dic_tags.html#kernel-cache-warmer
 *
 * `console.terminate` and not `console.command`, because the command a deploy
 * runs IS `cache:warmup`: reconciling before it executes would poison exactly
 * the pass this listener exists to protect, while reconciling after it has
 * warmed the metadata cache is free of that. So `bin/console cache:clear` on a
 * deploy remains the whole of the operator's instructions, and the registry is
 * in step by the time the command returns.
 *
 * The stamp file is what makes this once-per-deploy rather than once-per-
 * request: it lives in the cache directory, which a deploy replaces, and it is
 * claimed atomically so that two processes arriving together reconcile once.
 */
final class RegistrySyncListener implements ServiceSubscriberInterface
{
    /**
     * @param string $stampFile written once the registry has been reconciled against this build's cache directory
     */
    public function __construct(
        private readonly ContainerInterface $container,
        public readonly string $stampFile,
    ) {
    }

    /**
     * Ahead of everything that reads the catalogue — the parked-module gate runs
     * at priority 8 — so a first request for a module page is answered from a
     * reconciled registry rather than from an empty one.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->reconcileOnce();
    }

    /**
     * The event object is not taken, and symfony/console is not a dependency of
     * this bundle: the registry has no command of its own, so it names no class
     * of that component. A tag is a string, and an installation without a
     * console simply never fires this one.
     */
    public function onConsoleTerminate(): void
    {
        $this->reconcileOnce();
    }

    /**
     * Reconcile unless this build already has been.
     *
     * WHAT IT DECLINES TO DO RATHER THAN FATAL, and why the stamp is removed
     * again in two cases: an installation runs `cache:clear` BEFORE its first
     * migration, so this routinely meets a database with no registry tables in
     * it — or, in a build container, no database at all. Neither may break the
     * command it is attached to, and neither may be remembered as done: a
     * skipped reconciliation leaves no stamp, so the request that follows the
     * first migration is the one that fills the catalogue.
     */
    public function reconcileOnce(): void
    {
        if (is_file($this->stampFile)) {
            return;
        }

        $directory = \dirname($this->stampFile);
        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            return;
        }

        // 'x' fails if the file is there: the process that creates it is the one
        // that reconciles, and the others go on serving their request.
        $claim = @fopen($this->stampFile, 'x');
        if (false === $claim) {
            return;
        }
        fclose($claim);

        try {
            $sync = $this->container->get(RegistrySyncService::class);
            \assert($sync instanceof RegistrySyncService);

            if ($sync->sync()->skipped) {
                @unlink($this->stampFile);
            }
        } catch (\Throwable) {
            @unlink($this->stampFile);
        }
    }

    public static function getSubscribedServices(): array
    {
        // Fetched through the locator rather than injected, so that neither a
        // console command nor a request builds an entity manager for a
        // reconciliation this build has already had.
        return [
            RegistrySyncService::class,
        ];
    }
}
