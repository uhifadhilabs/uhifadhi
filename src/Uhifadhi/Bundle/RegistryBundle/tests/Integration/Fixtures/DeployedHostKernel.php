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

namespace Uhifadhi\Bundle\RegistryBundle\Tests\Integration\Fixtures;

/**
 * THE SAME INSTALLATION, WITHOUT DEBUG — which is the only state in which the
 * cache commands carry their full set of warmers.
 *
 * doctrine-bundle registers its metadata cache warmer only when `kernel.debug`
 * is false, so a specification that boots a debug kernel cannot see the
 * ordering rule that warmer enforces, and cannot see a deploy break on it.
 *
 * @see vendor/doctrine/doctrine-bundle/src/DependencyInjection/DoctrineExtension.php — `createMetadataCache()` registers the warmer inside `if (! $container->getParameter('kernel.debug'))`
 */
final class DeployedHostKernel extends HostKernel
{
    /**
     * Its own directory, told from the debug kernels' by the environment: two
     * environments sharing one cache directory would share the build artefacts
     * a deploy is judged by.
     */
    public function getCacheDir(): string
    {
        return $this->checkoutTempDir(
            'cache/deployed/'.$this->getEnvironment().'/'
            .substr(hash('xxh128', serialize(self::$modules)), 0, 12),
        );
    }
}
