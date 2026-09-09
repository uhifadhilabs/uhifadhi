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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\ShellBundle\Model\CorePart;
use Uhifadhi\Bundle\ShellBundle\Service\Installation;

/**
 * WHAT THE CORE IS MADE OF — its own manifests, read from the directory it was
 * installed into.
 *
 * The core is one package and one row, and a row that says only
 * `uhifadhi/uhifadhi` tells an operator nothing about the parts inside it. The
 * parts are not separate installs and have no versions of their own — they ride
 * with the core — so what is read is each one's own manifest: the package name
 * it would answer to on its own, and the one line it describes itself with.
 *
 * INSTALLED MEANS REGISTERED. A part on disk that the kernel does not boot is a
 * directory, not something this installation has, and listing it would tell an
 * operator that a screen exists where none does.
 *
 * The manifests are read from a fixture install path here, because reading the
 * real one would make this assert what the repository happens to contain today
 * rather than what the reader does.
 */
#[CoversClass(Installation::class)]
final class CorePartsTest extends TestCase
{
    private const string FIXTURE = __DIR__.'/../Fixtures/core-install';

    public function testEveryRegisteredPartIsNamedByItsOwnManifest(): void
    {
        $parts = self::partsOf([
            'ShellBundle' => 'Uhifadhi\Bundle\ShellBundle\ShellBundle',
            'TeamBundle' => 'Uhifadhi\Bundle\TeamBundle\TeamBundle',
        ]);

        self::assertSame(
            ['uhifadhi/contracts', 'uhifadhi/shell-bundle', 'uhifadhi/team-bundle'],
            array_map(static fn (CorePart $part): string => $part->name, $parts),
        );
    }

    public function testEachPartCarriesTheOneLineItsManifestDescribesItWith(): void
    {
        $parts = self::partsOf(['ShellBundle' => 'Uhifadhi\Bundle\ShellBundle\ShellBundle']);

        $described = [];
        foreach ($parts as $part) {
            $described[$part->name] = $part->description;
        }

        self::assertSame('The promises a module is built against.', $described['uhifadhi/contracts']);
        self::assertSame('The visible shell every page grows into.', $described['uhifadhi/shell-bundle']);
    }

    /**
     * A directory the kernel does not boot is not something this installation
     * has — the fixture ships AreaBundle's manifest and nothing registers it.
     */
    public function testAPartOnDiskThatNothingRegistersIsNotListed(): void
    {
        $names = array_map(
            static fn (CorePart $part): string => $part->name,
            self::partsOf(['ShellBundle' => 'Uhifadhi\Bundle\ShellBundle\ShellBundle']),
        );

        self::assertNotContains('uhifadhi/area-bundle', $names);
    }

    /** Somebody else's bundle in the kernel is somebody else's, not a part of this. */
    public function testABundleFromOutsideTheCoreIsNotAPart(): void
    {
        $parts = self::partsOf([
            'ShellBundle' => 'Uhifadhi\Bundle\ShellBundle\ShellBundle',
            'YourVendorSightingsBundle' => 'YourVendor\Sightings\YourVendorSightingsBundle',
        ]);

        self::assertCount(2, $parts, 'Only the contracts and the shell are the core here.');
    }

    /**
     * A registered part whose manifest is not where it should be is left out
     * rather than printed half-known: the page reports what it can read.
     */
    public function testAPartWithNoManifestIsLeftOut(): void
    {
        $parts = self::partsOf(['AtlasBundle' => 'Uhifadhi\Bundle\AtlasBundle\AtlasBundle']);

        self::assertSame(['uhifadhi/contracts'], array_map(static fn (CorePart $part): string => $part->name, $parts));
    }

    /** No install path is no reading, and the page falls back to the one row. */
    public function testNothingIsReportedWhenTheCoreCannotBeLocated(): void
    {
        self::assertSame([], new Installation()->coreParts(null, ['ShellBundle' => 'Uhifadhi\Bundle\ShellBundle\ShellBundle']));
    }

    /**
     * @param array<string, string> $bundles
     *
     * @return list<CorePart>
     */
    private static function partsOf(array $bundles): array
    {
        return new Installation()->coreParts(self::FIXTURE, $bundles);
    }
}
