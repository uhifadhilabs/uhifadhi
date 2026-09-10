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
use Uhifadhi\Bundle\ShellBundle\Frame\Registry\ModuleTabsRegistry;
use Uhifadhi\Contracts\Shell\ModuleTab;
use Uhifadhi\Contracts\Shell\ModuleTabsInterface;

/**
 * WHICH MODULE'S STRIP IS WHOSE. The registry files every declaration under the
 * slug that declared it, so two modules never see each other's tabs and a page
 * inside a module that declared none renders no strip rather than somebody
 * else's.
 */
final class ModuleTabsRegistryTest extends TestCase
{
    public function testItAnswersWithTheTabsTheSlugDeclared(): void
    {
        $registry = new ModuleTabsRegistry([
            self::declaration('patrols', [new ModuleTab('Overview', 'patrol_dashboard'), new ModuleTab('Patrols', 'patrol_list')]),
            self::declaration('incidents', [new ModuleTab('Overview', 'incident_dashboard')]),
        ]);

        self::assertSame(['Overview', 'Patrols'], array_map(
            static fn (ModuleTab $tab): string => $tab->label,
            $registry->tabs('patrols'),
        ));
        self::assertSame(['Overview'], array_map(
            static fn (ModuleTab $tab): string => $tab->label,
            $registry->tabs('incidents'),
        ));
    }

    public function testAModuleThatDeclaredNothingHasNoTabsAndIsNotKnown(): void
    {
        $registry = new ModuleTabsRegistry([self::declaration('patrols', [new ModuleTab('Overview', 'patrol_dashboard')])]);

        self::assertFalse($registry->has('sightings'));
        self::assertSame([], $registry->tabs('sightings'));
    }

    /**
     * TWO DECLARATIONS FOR ONE MODULE IS A DISAGREEMENT, not a merge. Whichever
     * one won would be decided by service-definition order, which is the worst
     * possible way to decide what a strip says.
     */
    public function testTwoDeclarationsForOneSlugAreRefused(): void
    {
        $registry = new ModuleTabsRegistry([
            self::declaration('patrols', [new ModuleTab('Overview', 'patrol_dashboard')]),
            self::declaration('patrols', [new ModuleTab('Elsewhere', 'other_dashboard')]),
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/patrols/');

        $registry->tabs('patrols');
    }

    public function testADeclarationWithoutASlugIsRefused(): void
    {
        $registry = new ModuleTabsRegistry([self::declaration('', [new ModuleTab('Overview', 'patrol_dashboard')])]);

        $this->expectException(\LogicException::class);

        $registry->has('patrols');
    }

    /** @param list<ModuleTab> $tabs */
    private static function declaration(string $slug, array $tabs): ModuleTabsInterface
    {
        return new class($slug, $tabs) implements ModuleTabsInterface {
            /** @param list<ModuleTab> $tabs */
            public function __construct(private readonly string $slug, private readonly array $tabs)
            {
            }

            public function slug(): string
            {
                return $this->slug;
            }

            public function tabs(): array
            {
                return $this->tabs;
            }
        };
    }
}
