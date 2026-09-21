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

namespace Uhifadhi\Contracts\Tests\Access;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Contracts\Access\Grant;
use Uhifadhi\Contracts\Access\Verb;

/**
 * ONE SPELLING OF A PAIR, held by a test, because four places write it.
 *
 * A position stores it, a route names it, a door draws it and the build
 * tests match them up. The day two of them disagree about where the dot
 * goes, a power is enforced that nobody granted - so the spelling is not a
 * convention here, it is a specification.
 */
final class GrantTest extends TestCase
{
    public function testAPairIsWrittenConcernDotVerb(): void
    {
        self::assertSame('zones.configure', (string) Grant::of('zones', Verb::Configure));
    }

    public function testAConcernKeyMayCarryHyphensBecauseTheVerbIsTheLastSegment(): void
    {
        $grant = Grant::parse('personal-details.read');

        self::assertSame('personal-details', $grant->concern);
        self::assertSame(Verb::Read, $grant->verb);
    }

    #[DataProvider('everyVerb')]
    public function testEveryVerbRoundTrips(Verb $verb): void
    {
        $grant = Grant::of('watches', $verb);

        self::assertTrue($grant->equals(Grant::parse((string) $grant)));
    }

    /** @return iterable<string, array{Verb}> */
    public static function everyVerb(): iterable
    {
        foreach (Verb::cases() as $verb) {
            yield $verb->value => [$verb];
        }
    }

    public function testTwoPairsAreTheSameWhenBothHalvesAre(): void
    {
        self::assertTrue(Grant::of('zones', Verb::Read)->equals(Grant::of('zones', Verb::Read)));
        self::assertFalse(Grant::of('zones', Verb::Read)->equals(Grant::of('zones', Verb::Delete)));
        self::assertFalse(Grant::of('zones', Verb::Read)->equals(Grant::of('areas', Verb::Read)));
    }

    public function testAStringWithNoVerbIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/names no verb/');

        Grant::parse('zones');
    }

    public function testAStringEndingInSomethingThatIsNotOneOfTheSixIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no verb this product has/');

        Grant::parse('zones.approve');
    }

    public function testAConcernWithADotIsRefusedBecauseThePairWouldBeAmbiguous(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/ambiguous/');

        Grant::of('area.zones', Verb::Read);
    }

    /**
     * A grant left behind by an uninstalled module is read, not crashed on:
     * the matrix draws it muted and revoking it still works.
     */
    public function testAnUnreadablePairCanBeAskedAboutWithoutThrowing(): void
    {
        self::assertNull(Grant::tryParse('leftover-from-a-module'));
        self::assertNotNull(Grant::tryParse('watches.manage'));
    }
}
