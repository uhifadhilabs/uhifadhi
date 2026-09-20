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

namespace Uhifadhi\Bundle\AtlasBundle\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Uhifadhi\Bundle\AtlasBundle\Calendar\CalendarBuilder;
use Uhifadhi\Bundle\AtlasBundle\Twig\CalendarRuntime;
use Uhifadhi\Contracts\Atlas\CalendarDay;
use Uhifadhi\Contracts\Atlas\CalendarFeedInterface;
use Uhifadhi\Contracts\Atlas\CalendarMonth;
use Uhifadhi\Contracts\Atlas\CalendarPill;
use Uhifadhi\Contracts\Atlas\PillHue;
use Uhifadhi\Contracts\Atlas\YearMonth;

/**
 * THE MONTH THE ATLAS DRAWS, RENDERED.
 *
 * A SURFACE NAMES A FEED AND GETS A MONTH. There is no tag and no
 * collection: a month of patrols and a month of watches are different
 * pages, not one page that merged them, so the caller says which feed it
 * wants exactly as it says what a plate's subject is.
 *
 * WHAT THE ATLAS OWNS AND WHAT THE MODULE OWNS, asserted rather than
 * described: the grid, the day head, the cell, its fixed height, the day
 * number and the "+N more" are drawn here for every module; the label, the
 * hue ROLE and the address are the module's, and the role is painted by
 * the atlas so that two modules cannot disagree about what "a problem"
 * looks like.
 *
 * THE MARK ITSELF IS THE SHELL'S. It was host vocabulary before this
 * component existed, and the atlas must not restate it — see the
 * vocabulary conformance suite, which fails on a second copy.
 */
final class CalendarContractTest extends TestCase
{
    /** The design's cell, and the one property that changes it. */
    private const string CELL_HEIGHT_PROPERTY = '--cal-cell-height';
    private const string DEFAULT_CELL_HEIGHT = '96px';

    public function testASurfaceNamesAFeedAndTheAtlasDrawsItsMonth(): void
    {
        $html = $this->render($this->feed([
            '2026-09-19' => new CalendarDay('2026-09-19', [new CalendarPill('day 2')]),
        ]), '2026-09');

        self::assertStringContainsString('class="cal"', $html);
        self::assertStringContainsString('<div class="dh">mon</div>', $html);
        self::assertStringContainsString('september 2026', $html);
        self::assertStringContainsString('day 2', $html);
    }

    /**
     * A HUE IS A ROLE AND THE ATLAS PICKS THE PAINT. A module handing over a
     * colour would be deciding what red is, in a product with two themes and
     * a palette that moves; it names a meaning and the atlas maps it to a
     * token — so the mark carries a custom property and never a literal.
     */
    public function testAHueRoleIsPaintedFromTheAtlasesOwnTokens(): void
    {
        $html = $this->render($this->feed([
            '2026-09-19' => new CalendarDay('2026-09-19', [new CalendarPill('night 1 of 2', PillHue::Problem)]),
        ]), '2026-09');

        self::assertStringContainsString('--pill-hue:var(--fail)', $html);
        self::assertStringNotContainsString('#', $html, 'a hue is a token, never a literal colour');
    }

    /** Finished is hollow — the same reading the map plate uses. */
    public function testAClosedPillIsDrawnHollow(): void
    {
        $html = $this->render($this->feed([
            '2026-09-19' => new CalendarDay('2026-09-19', [new CalendarPill('P-0145', PillHue::Good, closed: true)]),
        ]), '2026-09');

        self::assertStringContainsString('cal-mark done', $html);
    }

    /**
     * THE RULE THE COMPONENT EXISTS FOR, on the page: what does not fit is a
     * "+N more" and the cell stays one height.
     */
    public function testASurplusIsDrawnAsMoreAndNotAsATallerCell(): void
    {
        $pills = [];
        foreach (['one', 'two', 'three', 'four', 'five'] as $label) {
            $pills[] = new CalendarPill($label);
        }

        $html = $this->render($this->feed(['2026-09-19' => new CalendarDay('2026-09-19', $pills, url: '/day')]), '2026-09');

        self::assertStringContainsString('+2 more', $html);
        self::assertStringContainsString('href="/day"', $html);
        self::assertStringNotContainsString('five', $html, 'what does not fit is counted, not drawn');
    }

    /** The day's own count, so a full cell still says how full it is. */
    public function testTheDaysRealCountIsPrintedEvenWhereTheCellCannotHoldIt(): void
    {
        $html = $this->render($this->feed([
            '2026-09-19' => new CalendarDay('2026-09-19', [new CalendarPill('day 2')], count: 40),
        ]), '2026-09');

        self::assertStringContainsString('<span class="dcount">40</span>', $html);
        self::assertStringContainsString('+39 more', $html);
    }

    /**
     * AN EMPTY SEPTEMBER IS A FACT ABOUT SEPTEMBER. The grid is drawn and a
     * sentence under it says why it is bare — a missing month would be a
     * broken page, and an empty grid with no explanation reads as one.
     */
    public function testAMonthNobodyPublishedAnythingForIsStillDrawn(): void
    {
        $html = $this->render($this->feed([]), '2026-09');

        self::assertStringContainsString('class="cal"', $html);
        self::assertStringContainsString('Nothing on this month yet', $html);
    }

    /** An arrow with nowhere to go is drawn and not offered, so the row never jumps. */
    public function testTheStepperDrawsBothArrowsAndLinksOnlyTheOnesItWasGiven(): void
    {
        $html = $this->render($this->feed([]), '2026-09', nextUrl: '/calendar?m=2026-10');

        self::assertStringContainsString('href="/calendar?m=2026-10"', $html);
        self::assertSame(2, substr_count($html, 'mchip ghost'), 'both arrows, always');
    }

    /**
     * A CELL IS ONE HEIGHT WHATEVER IT HOLDS, off ONE custom property with
     * the design's own default behind it — the same door a plate's and a
     * chart's height come through. A text check over the month's OWN sheet —
     * which is where these rules live, because the month is drawn on pages
     * that link no map — and that is the limit of what it promises.
     */
    public function testTheCellTakesItsHeightFromOneCustomProperty(): void
    {
        $sheet = (string) file_get_contents(\dirname(__DIR__, 3).'/public/calendar.css');

        self::assertStringContainsString(
            \sprintf('height: var(%s, %s);', self::CELL_HEIGHT_PROPERTY, self::DEFAULT_CELL_HEIGHT),
            $sheet,
            'a month that grew with its data would make one week taller than another',
        );
    }

    /**
     * A MONTH IS ONE ROW OF CHROME. The stepper is the component's and
     * the picker is the surface's, and the design puts them on the same
     * line — so the component takes the second rather than leaving every
     * caller to draw a toolbar of its own underneath.
     */
    public function testTheSurfacesOwnControlSharesTheSteppersRow(): void
    {
        $html = $this->render($this->feed([]), '2026-09', controls: '<button class="mchip i-ddt">t. ndosi</button>');

        $nav = (string) preg_replace('/^.*<div class="cal-nav">|<\/div>.*$/s', '', $html);

        self::assertStringContainsString('t. ndosi', $nav, 'the control is inside the stepper row');
        self::assertStringContainsString('<span class="sp"></span>', $nav, 'pushed to the trailing end');
        self::assertLessThan(
            strpos($nav, 't. ndosi') ?: 0,
            strpos($nav, 'september 2026') ?: 0,
            'the stepper leads and the surface\'s control follows it',
        );
    }

    /** A surface with no control of its own gets no spacer and no empty slot. */
    public function testAMonthWithNoControlDrawsNoSlotAtAll(): void
    {
        $html = $this->render($this->feed([]), '2026-09');

        self::assertStringNotContainsString('<span class="sp"></span>', $html);
    }

    /** And a caller may state one, through that door and no other. */
    public function testACallerSizesTheCellThroughThatSameProperty(): void
    {
        $html = $this->render($this->feed([]), '2026-09', attributes: [self::CELL_HEIGHT_PROPERTY => '120px']);

        self::assertStringContainsString('style="--cal-cell-height:120px"', $html);
    }

    /**
     * A ROLE IS PAINTED WITH A COLOUR TOKEN, NEVER A CHANNEL TOKEN.
     *
     * THE SHELL PUBLISHES BOTH AND THEY ARE NOT INTERCHANGEABLE:
     * `--c-acc` is `62 217 168`, three numbers meant to be spent inside
     * `rgb(...)`, and `--acc` is the colour. The pill's dot hands this
     * value straight to a `background`, so a channel token painted
     * nothing at all — and the markup, the class and the custom
     * property were all exactly right while every dot drew empty.
     */
    public function testEveryHueIsAColourTokenAndNotAChannelTriple(): void
    {
        $html = $this->render($this->feed([
            '2026-09-19' => new CalendarDay('2026-09-19', [
                new CalendarPill('subject'),
                new CalendarPill('good', PillHue::Good),
                new CalendarPill('attention', PillHue::Attention),
                new CalendarPill('problem', PillHue::Problem),
                new CalendarPill('quiet', PillHue::Quiet),
            ]),
        ]), '2026-09');

        preg_match_all('/--pill-hue:\s*([^"]+)"/', $html, $found);

        self::assertNotSame([], $found[1], 'a month of pills paints some dots');
        foreach (array_unique($found[1]) as $hue) {
            self::assertDoesNotMatchRegularExpression(
                '/var\(--c-/',
                $hue,
                'A channel token in a background paints nothing: spend --acc, not --c-acc.',
            );
        }
    }

    // ---------------------------------------------------------------- fixtures

    /** @param array<string, bool|string> $attributes */
    private function render(CalendarFeedInterface $feed, string $month, ?string $nextUrl = null, array $attributes = [], string $controls = ''): string
    {
        // THE NAMESPACE THE BUNDLE PREPENDS, given to a bare environment: the
        // template names itself `@Atlas/...` because that is how a module
        // includes it, and a suite that loaded it by path would be rendering
        // a file rather than the component.
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'Atlas');

        $twig = new Environment($loader, ['strict_variables' => true]);

        $runtime = new CalendarRuntime($twig, new CalendarBuilder());

        return $runtime->renderCalendar(
            $feed,
            $month,
            today: new \DateTimeImmutable('2026-09-19'),
            nextUrl: $nextUrl,
            attributes: $attributes,
            controls: $controls,
        );
    }

    /** @param array<string, CalendarDay> $days */
    private function feed(array $days): CalendarFeedInterface
    {
        return new class($days) implements CalendarFeedInterface {
            /** @param array<string, CalendarDay> $days */
            public function __construct(private readonly array $days)
            {
            }

            public function month(YearMonth $month, ?string $scope = null): CalendarMonth
            {
                return new CalendarMonth($month, $this->days);
            }
        };
    }
}
