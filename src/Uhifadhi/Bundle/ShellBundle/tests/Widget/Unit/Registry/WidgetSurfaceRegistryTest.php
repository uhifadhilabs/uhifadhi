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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Widget\Unit\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\Widget;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetCatalog;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetGroup;
use Uhifadhi\Bundle\ShellBundle\Widget\Registry\WidgetSurfaceInterface;
use Uhifadhi\Bundle\ShellBundle\Widget\Registry\WidgetSurfaceRegistry;

/**
 * WHICH DASHBOARDS AN INSTALLATION HAS is exactly the set of modules that
 * declared one — nothing stored, nothing configured.
 */
#[CoversClass(WidgetSurfaceRegistry::class)]
final class WidgetSurfaceRegistryTest extends TestCase
{
    private static function surface(string $name): WidgetSurfaceInterface
    {
        return new class($name) implements WidgetSurfaceInterface {
            public function __construct(private readonly string $name)
            {
            }

            public function catalog(): WidgetCatalog
            {
                return new WidgetCatalog(
                    $this->name,
                    [new WidgetGroup('g', 'Group', 'One line.')],
                    [new Widget('w', 'Widget', 'g')],
                );
            }
        };
    }

    public function testAnInstallationWithNoModulesHasNoSurfaces(): void
    {
        $registry = new WidgetSurfaceRegistry([]);

        self::assertSame([], $registry->surfaces());
        self::assertFalse($registry->has('sightings'));
        self::assertNull($registry->catalog('sightings'));
    }

    public function testEveryDeclaredSurfaceIsClaimed(): void
    {
        $registry = new WidgetSurfaceRegistry([self::surface('sightings'), self::surface('patrols')]);

        self::assertSame(['sightings', 'patrols'], $registry->surfaces(), 'Registration order, which is installation order.');
        self::assertTrue($registry->has('patrols'));
        self::assertSame('patrols', $registry->catalog('patrols')?->surface);
    }

    /**
     * A surface string arrives from a URL or from a stored row, so an unknown
     * one is an ordinary answer rather than an exception.
     */
    public function testAnUnclaimedSurfaceAnswersNullRatherThanThrowing(): void
    {
        $registry = new WidgetSurfaceRegistry([self::surface('sightings')]);

        self::assertFalse($registry->has('retired'));
        self::assertNull($registry->catalog('retired'));
    }

    public function testTwoModulesClaimingOneSurfaceIsARefusal(): void
    {
        $registry = new WidgetSurfaceRegistry([self::surface('sightings'), self::surface('sightings')]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Two widget surfaces both claim "sightings"');

        $registry->surfaces();
    }
}
