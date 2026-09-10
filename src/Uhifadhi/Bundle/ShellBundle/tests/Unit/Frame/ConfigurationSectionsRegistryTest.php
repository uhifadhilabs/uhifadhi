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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Unit\Frame;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\ShellBundle\Frame\Registry\ConfigurationSectionsRegistry;
use Uhifadhi\Contracts\Shell\ConfigurationSection;
use Uhifadhi\Contracts\Shell\ConfigurationSectionsInterface;

/**
 * THE CONFIGURE STRIP READS THE SAME WAY ROUND ON EVERY SURFACE. A module says
 * what its sections ARE; the shell says what ORDER they come in, so a person who
 * has learnt one configure page has learnt all of them.
 */
final class ConfigurationSectionsRegistryTest extends TestCase
{
    public function testWidgetLibraryComesFirstAndSettingsLastWhateverTheModuleDeclared(): void
    {
        $registry = new ConfigurationSectionsRegistry([self::declaration('patrols', [
            ConfigurationSection::page('settings', 'Settings', '@Patrol/configure/_settings.html.twig'),
            ConfigurationSection::page('kinds', 'Observation kinds', '@Patrol/configure/_kinds.html.twig'),
            ConfigurationSection::page('widgets', 'Widget library', '@Patrol/configure/_widgets.html.twig'),
        ])]);

        self::assertSame(['Widget library', 'Observation kinds', 'Settings'], array_map(
            static fn (ConfigurationSection $section): string => $section->label,
            $registry->sections('patrols'),
        ));
    }

    /**
     * WHAT A MODULE PUTS BETWEEN THE ANCHORS KEEPS THE MODULE'S OWN ORDER. The
     * ruling fixes two positions, not the whole list.
     */
    public function testTheSectionsBetweenTheAnchorsKeepTheirDeclaredOrder(): void
    {
        $registry = new ConfigurationSectionsRegistry([self::declaration('incidents', [
            ConfigurationSection::page('settings', 'Settings', '@Incident/configure/_settings.html.twig'),
            ConfigurationSection::page('money', 'Values', '@Incident/configure/_money.html.twig'),
            ConfigurationSection::page('kinds', 'Incident kinds', '@Incident/configure/_kinds.html.twig'),
        ])]);

        self::assertSame(['Values', 'Incident kinds', 'Settings'], array_map(
            static fn (ConfigurationSection $section): string => $section->label,
            $registry->sections('incidents'),
        ));
    }

    public function testTheAreaIsCollectedLikeAnyOtherSurface(): void
    {
        $registry = new ConfigurationSectionsRegistry([self::declaration(ConfigurationSectionsInterface::AREA, [
            ConfigurationSection::page('settings', 'Area settings', '@Area/area/configure/_settings.html.twig'),
            ConfigurationSection::screen('widgets', 'Widget library', 'area_widgets'),
        ])]);

        self::assertTrue($registry->has(ConfigurationSectionsInterface::AREA));
        self::assertSame(['Widget library', 'Area settings'], array_map(
            static fn (ConfigurationSection $section): string => $section->label,
            $registry->sections(ConfigurationSectionsInterface::AREA),
        ));
    }

    public function testASurfaceThatDeclaredNothingHasNoSections(): void
    {
        $registry = new ConfigurationSectionsRegistry([]);

        self::assertFalse($registry->has('patrols'));
        self::assertSame([], $registry->sections('patrols'));
    }

    public function testTwoDeclarationsForOneSurfaceAreRefused(): void
    {
        $registry = new ConfigurationSectionsRegistry([
            self::declaration('patrols', [ConfigurationSection::page('settings', 'Settings', '@Patrol/a.html.twig')]),
            self::declaration('patrols', [ConfigurationSection::page('settings', 'Settings', '@Other/b.html.twig')]),
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/patrols/');

        $registry->sections('patrols');
    }

    /** @param list<ConfigurationSection> $sections */
    private static function declaration(string $slug, array $sections): ConfigurationSectionsInterface
    {
        return new class($slug, $sections) implements ConfigurationSectionsInterface {
            /** @param list<ConfigurationSection> $sections */
            public function __construct(private readonly string $slug, private readonly array $sections)
            {
            }

            public function slug(): string
            {
                return $this->slug;
            }

            public function heading(): string
            {
                return ucfirst($this->slug);
            }

            public function summary(): ?string
            {
                return null;
            }

            public function sections(): array
            {
                return $this->sections;
            }
        };
    }
}
