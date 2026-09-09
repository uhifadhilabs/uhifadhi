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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Unit\Test;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\ShellBundle\Test\VocabularyConformanceTestCase;
use Uhifadhi\Bundle\ShellBundle\Tests\Unit\Test\Fixtures\DriftingBundle;

/**
 * THE CONFORMANCE BASE, WATCHED FAILING.
 *
 * Everything this base ships is a build failure a module gets instead of a
 * broken page, and a failure nobody has ever seen happen is a failure that may
 * not happen at all: a regex that quietly stopped matching turns every one of
 * those assertions into a green loop over nothing. So a bundle that has drifted
 * every way at once is kept beside it, and each assertion is pointed at that
 * bundle and required to say no.
 *
 * @see DriftingBundle
 */
#[CoversClass(VocabularyConformanceTestCase::class)]
final class VocabularyConformanceTestCaseTest extends TestCase
{
    /** A public library's prefix belongs to the installation, not to a bundle. */
    public function testAPrefixTheBundleDoesNotOwnFails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/lucide/');

        self::drifting()->testEveryIconReferenceUsesAPrefixThisBundleMayUse();
    }

    /** A name under the bundle's own prefix with no file behind it is an empty box. */
    public function testAnIconTheBundleNamesButDoesNotShipFails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/drift:absent/');

        self::drifting()->testEveryIconUnderThisBundlesPrefixResolvesFromTheDirectoryItShips();
    }

    /** Two definitions of one control render whichever loaded last. */
    public function testASelectorTheChainAlreadyShipsFails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/\.cta/');

        self::drifting()->testTheOwnSheetsRestateNoSelectorTheChainShips();
    }

    /** The reader is watched working too, or a failure could be an empty sweep. */
    public function testTheDriftingBundlesOwnVocabularyIsSeen(): void
    {
        self::drifting()->testEveryClassTheTemplatesWriteIsShippedBySomebody();
        self::drifting()->testTheOwnSheetsSpendNoTokenTheChainDoesNotDefine();
    }

    private static function drifting(): DriftingBundle
    {
        return new DriftingBundle('conformance');
    }
}
