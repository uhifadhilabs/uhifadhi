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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures;

use Uhifadhi\Contracts\Devkit\ContentProviderInterface;

/**
 * DEVKIT, STANDING IN — the collecting half of the content contract.
 *
 * devkit installs through `require-dev` and is no dependency of the core, so
 * nothing here can boot the real seeder. What the real one does is this much:
 * iterate the services tagged with the content tag, order them so that
 * everything a provider dependsOn() runs before it, and call load() on each.
 *
 * Standing in for it is what lets this bundle prove its provider is
 * COLLECTABLE and its content SEEDABLE without depending on the tool that will
 * do the collecting.
 */
final readonly class DevkitContentCollector
{
    /** @param iterable<ContentProviderInterface> $providers */
    public function __construct(private iterable $providers)
    {
    }

    /**
     * Every content key the installed providers offer.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = [];
        foreach ($this->providers as $provider) {
            $keys[] = $provider->key();
        }

        return $keys;
    }

    public function get(string $key): ContentProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->key() === $key) {
                return $provider;
            }
        }

        throw new \InvalidArgumentException(\sprintf('No installed provider offers the content "%s".', $key));
    }

    /**
     * Seed one contribution, after everything it names in dependsOn(). A named
     * dependency nothing offers is the failure the real seeder reports rather
     * than a silently skipped edge.
     */
    public function seed(string $key): void
    {
        foreach ($this->get($key)->dependsOn() as $earlier) {
            $this->seed($earlier);
        }

        $this->get($key)->load();
    }
}
