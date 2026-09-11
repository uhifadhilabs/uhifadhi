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

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Everything that reached the `kernel.cache_warmer` tag — the same iterator the
 * framework's warmer aggregate receives, which is what makes "the registry
 * contributes no warmer of its own" an assertable fact rather than a reading of
 * the service file.
 */
final readonly class CollectedCacheWarmers
{
    /**
     * @param iterable<CacheWarmerInterface> $warmers
     */
    public function __construct(
        private iterable $warmers,
    ) {
    }

    /**
     * @return list<class-string>
     */
    public function classNames(): array
    {
        $names = [];
        foreach ($this->warmers as $warmer) {
            $names[] = $warmer::class;
        }

        return $names;
    }
}
