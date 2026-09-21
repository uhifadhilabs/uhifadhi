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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Contracts\Shell\NavGroup;

/**
 * THE FOUR GROUPS ARE PUBLISHED, AND SO IS THEIR ORDER.
 *
 * This is a published list in the sense the theme contract is: a module
 * compiled against it may name any of the four and nothing else, so the four
 * names, their string values and their order are all part of the contract and
 * change only on purpose.
 */
#[CoversClass(NavGroup::class)]
final class NavGroupTest extends TestCase
{
    /**
     * THE VALUES ARE THE LABELS AS DRAWN, and they do not move: a module
     * still filing under a literal keeps working, which is the whole reason
     * the constants carry strings rather than an enum's cases.
     */
    public function testTheFourGroupsAndTheirOrder(): void
    {
        self::assertSame(
            ['Observatory', 'Organization', 'System', 'Settings'],
            NavGroup::ORDER,
        );

        self::assertSame('Observatory', NavGroup::OBSERVATORY);
        self::assertSame('Organization', NavGroup::ORGANIZATION);
        self::assertSame('System', NavGroup::SYSTEM);
        self::assertSame('Settings', NavGroup::SETTINGS);
    }

    /** Settings is configuration, and configuration comes last. */
    public function testSettingsIsTheLastGroup(): void
    {
        self::assertSame(\count(NavGroup::ORDER) - 1, NavGroup::position(NavGroup::SETTINGS));
    }

    #[DataProvider('theFour')]
    public function testEachGroupKnowsWhereItIsDrawn(int $position, string $group): void
    {
        self::assertTrue(NavGroup::knows($group));
        self::assertSame($position, NavGroup::position($group));
    }

    /**
     * @return \Generator<string, array{int, string}>
     */
    public static function theFour(): \Generator
    {
        yield 'Observatory' => [0, NavGroup::OBSERVATORY];
        yield 'Organization' => [1, NavGroup::ORGANIZATION];
        yield 'System' => [2, NavGroup::SYSTEM];
        yield 'Settings' => [3, NavGroup::SETTINGS];
    }

    /**
     * A NEAR-MISS IS AN ERROR, NOT A FIFTH HEADING — and the error names the
     * four, because the author who typed it is choosing between them.
     */
    #[DataProvider('theNearMisses')]
    public function testAnUnknownGroupIsRefusedByNameAndTheFourAreNamedBack(string $written): void
    {
        self::assertFalse(NavGroup::knows($written));

        try {
            NavGroup::position($written);
            self::fail(\sprintf('"%s" names no group and must be refused.', $written));
        } catch (\InvalidArgumentException $refusal) {
            self::assertStringContainsString(\sprintf('"%s" is not a sidebar group', $written), $refusal->getMessage());

            foreach (NavGroup::ORDER as $group) {
                self::assertStringContainsString($group, $refusal->getMessage());
            }
        }
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function theNearMisses(): \Generator
    {
        // DELIBERATELY THE OTHER SPELLING. The product spells it
        // "Organization" (ruled 2026-09-21); this row exists because a near
        // miss must be REFUSED rather than quietly making a fifth heading,
        // so the literal here is the one the product does not use and must
        // survive any spelling sweep.
        yield 'the British spelling' => ['Organisation'];
        yield 'the lower case' => ['system'];
        yield 'the abbreviation' => ['Org'];
        yield 'a group of ones own' => ['Storage'];
        yield 'nothing at all' => [''];
    }
}
