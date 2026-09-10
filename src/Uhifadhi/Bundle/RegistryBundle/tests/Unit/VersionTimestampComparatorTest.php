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

namespace Uhifadhi\Bundle\RegistryBundle\Tests\Unit;

use Doctrine\Migrations\Version\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\RegistryBundle\Version\VersionTimestampComparator;

#[CoversClass(VersionTimestampComparator::class)]
final class VersionTimestampComparatorTest extends TestCase
{
    public function testTheTimestampDecidesAndNotTheNamespaceItIsIn(): void
    {
        // What the shipped comparator would do with these: Area before
        // Registry before Shell before Team, in that order, whatever the dates
        // say — because it is a strcmp over the whole class name.
        $versions = [
            'Uhifadhi\\Bundle\\ShellBundle\\Migrations\\Version20260101000400',
            'Uhifadhi\\Bundle\\AreaBundle\\Migrations\\Version20260101000000',
            'Uhifadhi\\Bundle\\TeamBundle\\Migrations\\Version20260101000300',
            'Uhifadhi\\Bundle\\RegistryBundle\\Migrations\\Version20260101000200',
        ];

        self::assertSame(
            [
                'Uhifadhi\\Bundle\\AreaBundle\\Migrations\\Version20260101000000',
                'Uhifadhi\\Bundle\\RegistryBundle\\Migrations\\Version20260101000200',
                'Uhifadhi\\Bundle\\TeamBundle\\Migrations\\Version20260101000300',
                'Uhifadhi\\Bundle\\ShellBundle\\Migrations\\Version20260101000400',
            ],
            $this->sorted($versions),
        );
    }

    public function testAnInstallationsOwnVersionTakesItsPlaceByDateAmongTheCores(): void
    {
        // The recipe names the installation's namespace `DoctrineMigrations`,
        // which sorts before `Uhifadhi` under a strcmp — so an installation's
        // first migration would otherwise be planned BEFORE the core tables its
        // own entities point at.
        self::assertSame(
            [
                'Uhifadhi\\Bundle\\AreaBundle\\Migrations\\Version20260101000000',
                'DoctrineMigrations\\Version20260714091500',
            ],
            $this->sorted([
                'DoctrineMigrations\\Version20260714091500',
                'Uhifadhi\\Bundle\\AreaBundle\\Migrations\\Version20260101000000',
            ]),
        );
    }

    public function testTwoVersionsSharingATimestampFallBackToTheirName(): void
    {
        self::assertSame(
            [
                'Aardvark\\Migrations\\Version20260101000000',
                'Zebra\\Migrations\\Version20260101000000',
            ],
            $this->sorted([
                'Zebra\\Migrations\\Version20260101000000',
                'Aardvark\\Migrations\\Version20260101000000',
            ]),
        );
    }

    public function testANameThatCarriesNoTimestampSortsLastAndStaysStable(): void
    {
        // Nothing shipped looks like this, and a version that does has opted out
        // of being orderable at all; putting it after everything dated is the
        // only answer that cannot silently reorder a dated one.
        self::assertSame(
            [
                'Some\\Migrations\\Version20260101000000',
                'Some\\Migrations\\VersionHandWritten',
            ],
            $this->sorted([
                'Some\\Migrations\\VersionHandWritten',
                'Some\\Migrations\\Version20260101000000',
            ]),
        );
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function sorted(array $names): array
    {
        $comparator = new VersionTimestampComparator();
        $versions = array_map(static fn (string $name): Version => new Version($name), $names);

        usort($versions, $comparator->compare(...));

        return array_map(strval(...), $versions);
    }
}
