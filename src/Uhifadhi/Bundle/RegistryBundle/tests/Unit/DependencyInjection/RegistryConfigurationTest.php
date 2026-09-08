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

namespace Uhifadhi\Bundle\RegistryBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Uhifadhi\Bundle\RegistryBundle\DependencyInjection\RegistryConfiguration;

final class RegistryConfigurationTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        $builder = new TreeBuilder('registry');
        RegistryConfiguration::define($builder->getRootNode());

        /** @var array<string, mixed> $processed */
        $processed = new Processor()->process($builder->buildTree(), ['registry' => $config]);

        return $processed;
    }

    /**
     * OPERATIONS IS WHAT AN UNPLACED MODULE IS — the wave-1 ruling, and the
     * default a deployment gets without saying anything. This is an operations
     * platform: a module the catalogue cannot place is far likelier to be
     * somebody's daily work than a reading of the ecosystem.
     */
    public function testDefaultsFileAnUnplacedModuleUnderOperationsWithoutDevTools(): void
    {
        $config = $this->process([]);

        self::assertSame('operations', $config['default_category']);
        self::assertFalse($config['dev_tools']);
    }

    public function testADeploymentMayChooseADifferentFallback(): void
    {
        self::assertSame('flux', $this->process(['default_category' => 'flux'])['default_category']);
    }

    public function testAnEmptyFallbackIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        // An empty fallback means "coerce an unknown category to nothing",
        // which is how a module ends up in no group at all.
        $this->process(['default_category' => '']);
    }

    /**
     * THE TREE IS CLOSED, and here that matters more than usual: the one thing
     * a host might reach for is a list of modules to enable, and there is no
     * such key by design — installing a bundle IS the declaration. An invented
     * key must fail loudly rather than be ignored while an admin waits for it
     * to take effect.
     */
    public function testTheTreeIsClosedToUnknownKeys(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['modules' => ['sightings' => ['enabled' => true]]]);
    }
}
