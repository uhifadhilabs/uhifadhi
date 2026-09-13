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
use Uhifadhi\Bundle\ShellBundle\Model\TimeShape;
use Uhifadhi\Bundle\ShellBundle\Test\TimeConformanceTestCase;
use Uhifadhi\Bundle\ShellBundle\Tests\Unit\Test\Fixtures\DriftingTimeBundle;

/**
 * THE CONFORMANCE BASE, WATCHED FAILING.
 *
 * Everything this base ships is a build failure a module gets instead of a
 * reading somebody trusted, and a failure nobody has ever seen happen is a
 * failure that may not happen at all: a regex that quietly stopped matching
 * turns each assertion into a green loop over nothing. So a bundle that prints
 * the server's zone every way at once is kept beside it, and each assertion is
 * pointed at that bundle and required to say no.
 *
 * @see DriftingTimeBundle
 */
#[CoversClass(TimeConformanceTestCase::class)]
final class TimeConformanceTestCaseTest extends TestCase
{
    /** A date printed outside an element is a date nothing can rewrite. */
    public function testADatePrintedWithNoElementAroundItFails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/page\.html\.twig/');

        self::drifting()->testEveryInstantATemplatePrintsIsInsideATimeElement();
    }

    /** A `<time>` with no machine instant is read and skipped, so the server's text stands. */
    public function testATimeElementWithNoMachineInstantFails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/data-localtime-format="stamp"/');

        self::drifting()->testEveryTimeElementCarriesTheMachineInstant();
    }

    /** A misspelt shape does not throw in the browser; it silently draws the wrong one. */
    public function testAShapeTheFrameDoesNotAnswerFails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/timestamp/');

        self::drifting()->testEveryShapeATemplateAsksForIsOneTheShellShips();
    }

    /**
     * A TWIG COMMENT IS NOT MARKUP. Half the reason this base can be adopted at
     * all is that the templates documenting the idiom write the idiom out —
     * `{{ x|date('c') }}` as prose, inside `{# #}`. Read as markup, every such
     * template fails for explaining itself, and the answer would be to exempt it,
     * after which the check is gone.
     */
    public function testProseInACommentIsNotReadAsAPrint(): void
    {
        $templates = DriftingTimeBundle::pages();

        self::assertCount(1, $templates);
        self::assertStringNotContainsString('must not be read', implode('', $templates), 'The comment is stripped before anything is looked for.');
    }

    /**
     * THE SHAPES THE BASE CHECKS AGAINST ARE THE SHAPES THAT EXIST. The enum is
     * the list both this and the controller are written from; a case dropped
     * from it turns a real failure into a pass.
     */
    public function testTheShapesItChecksAgainstAreTheOnesTheShellNames(): void
    {
        self::assertSame(
            ['datetime', 'date', 'time', 'stamp', 'daystamp', 'clock', 'clocks', 'day', 'daylong'],
            TimeShape::names(),
        );
    }

    private static function drifting(): DriftingTimeBundle
    {
        return new DriftingTimeBundle('drift');
    }
}
