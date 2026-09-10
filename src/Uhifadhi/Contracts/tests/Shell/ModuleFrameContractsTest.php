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

namespace Uhifadhi\Contracts\Tests\Shell;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Contracts\Shell\ConfigurationSectionsInterface;
use Uhifadhi\Contracts\Shell\ModuleTabsInterface;

/**
 * THE MODULE FRAME'S TWO CONTRACTS, as a module author meets them. Both are
 * shaped like every other plug-point in this package: an interface, a published
 * TAG, and verbs that take NOTHING — the shell passes no area, no request and
 * no viewer, because a module that had to be handed those would be a module the
 * shell had to know something about.
 */
final class ModuleFrameContractsTest extends TestCase
{
    public function testTheTabsContractPublishesASlugAndATabList(): void
    {
        $reflection = new \ReflectionClass(ModuleTabsInterface::class);
        self::assertTrue($reflection->isInterface());

        $declared = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(),
        );
        sort($declared);

        self::assertSame(['slug', 'tabs'], $declared);

        foreach (['slug', 'tabs'] as $verb) {
            self::assertSame(
                [],
                (new \ReflectionMethod(ModuleTabsInterface::class, $verb))->getParameters(),
                'The shell passes nothing to a frame contract.',
            );
        }
    }

    public function testTheSectionsContractPublishesASlugAndASectionList(): void
    {
        $reflection = new \ReflectionClass(ConfigurationSectionsInterface::class);
        self::assertTrue($reflection->isInterface());

        $declared = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(),
        );
        sort($declared);

        self::assertSame(['sections', 'slug'], $declared);
    }

    /**
     * A TAG IS A CONSTANT, so a module spells it once and a rename is a compile
     * error rather than a strip that quietly stops being drawn — the same rule
     * the widget surfaces are collected under.
     */
    public function testBothTagsArePublishedAsConstants(): void
    {
        self::assertSame('uhifadhi.module_tabs', ModuleTabsInterface::TAG);
        self::assertSame('uhifadhi.configuration_sections', ConfigurationSectionsInterface::TAG);
    }

    /**
     * THE AREA CONFIGURES ITSELF THROUGH THE SAME CONTRACT AS A MODULE, and it
     * needs a slug to be collected under. The reserved one starts with an
     * underscore, which no module slug may — slugs are lowercase letters — so it
     * can never collide with a module written by somebody else.
     */
    public function testTheAreaIsCollectedUnderAReservedSlugNoModuleCanTake(): void
    {
        self::assertSame('_area', ConfigurationSectionsInterface::AREA);
        self::assertStringStartsWith('_', ConfigurationSectionsInterface::AREA);
    }

    /**
     * THE CONTRACTS NAME NO FRAMEWORK. A module implements them depending on
     * this package alone: no Request, no Router, no Twig, nothing that would
     * make the frame a Symfony concept rather than a product one.
     */
    public function testNeitherContractImportsAFramework(): void
    {
        foreach ([ModuleTabsInterface::class, ConfigurationSectionsInterface::class] as $contract) {
            $file = (new \ReflectionClass($contract))->getFileName();
            self::assertIsString($file);

            $source = file_get_contents($file);
            self::assertIsString($source);

            preg_match_all('/^use\s+([^;]+);/m', $source, $matches);
            foreach ($matches[1] as $import) {
                self::assertStringStartsWith(
                    'Uhifadhi\\Contracts\\',
                    $import,
                    \sprintf('%s imports %s; a contract depends on this package and nothing else.', $contract, $import),
                );
            }
        }
    }
}
