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

namespace Uhifadhi\Bundle\RegistryBundle\Tests\Integration;

/**
 * TWO CHECKOUTS OF THIS REPOSITORY MUST NEVER SHARE A COMPILED CONTAINER.
 *
 * Symfony derives a kernel's cache, build and log directories from
 * `getProjectDir()`, and a kernel that answers with a bare path under the
 * system temp directory drops that key: every checkout on the machine then
 * compiles into the same place, and a suite in one of them loads classes
 * another compiled. The project directory is put back as the key here — it is
 * the checkout the kernel belongs to — and the suffix keeps kernels inside one
 * checkout apart, exactly as before.
 *
 * @see https://symfony.com/doc/current/configuration/override_dir_structure.html#override-the-cache-directory
 * @see vendor/symfony/http-kernel/Kernel.php — `getCacheDir()` returns `$this->getProjectDir().'/var/cache/'.$this->environment`, `getBuildDir()` returns the cache directory and `getLogDir()` returns `$this->getProjectDir().'/var/log'`
 */
trait CheckoutTempDirTrait
{
    abstract public function getProjectDir(): string;

    /**
     * @param non-empty-string $suffix what tells this kernel's directory from the others in the same checkout
     */
    protected function checkoutTempDir(string $suffix): string
    {
        return sys_get_temp_dir().'/uhifadhi-core-tests/'
            .substr(hash('xxh128', $this->getProjectDir()), 0, 12).'/'.$suffix;
    }
}
