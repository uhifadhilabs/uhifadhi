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

use Uhifadhi\Contracts\Devkit\CommandDescriptor;
use Uhifadhi\Contracts\Devkit\CommandProviderInterface;

/**
 * DEVKIT, STANDING IN — the half of the seam that is not this bundle's.
 *
 * devkit installs through `require-dev` and is not a dependency of the core, so
 * nothing here can boot the real collector. What the real one does is exactly
 * this much: iterate the services tagged `uhifadhi.devkit.command_provider`,
 * read each provider's descriptors, and invoke a descriptor's handler with the
 * argument tail, using the returned int as the exit status.
 *
 * Standing in for it is what lets this bundle prove its provider is COLLECTABLE
 * and its handler RUNNABLE without depending on the tool that will do the
 * collecting.
 *
 * @see vendor-less: uhifadhi/devkit-module's ProviderCommandLoader + DescriptorCommand
 */
final readonly class DevkitCommandCollector
{
    /** @param iterable<CommandProviderInterface> $providers */
    public function __construct(private iterable $providers)
    {
    }

    /**
     * Every command name the installed providers offer.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $names = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->commands() as $command) {
                $names[] = $command->name;
            }
        }

        return $names;
    }

    public function get(string $name): CommandDescriptor
    {
        foreach ($this->providers as $provider) {
            foreach ($provider->commands() as $command) {
                if ($command->name === $name) {
                    return $command;
                }
            }
        }

        throw new \InvalidArgumentException(\sprintf('No installed provider offers the command "%s".', $name));
    }

    /** @param list<string> $arguments */
    public function run(string $name, array $arguments): int
    {
        return ($this->get($name)->handler)($arguments);
    }
}
