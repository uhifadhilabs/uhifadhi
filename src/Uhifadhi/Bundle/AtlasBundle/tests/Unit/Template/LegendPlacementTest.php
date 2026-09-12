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

/**
 * THE LEGEND SITS BELOW THE MAP, NOT OVER IT.
 *
 * A legend floating in the imagery's bottom-right corner covers the ground it
 * describes, and on a short plate — an incident report's 176px rail, a
 * thumbnail — it covers most of it. So the legend is a row UNDER the map, the
 * way the design workspace draws it (`.maplegend` after a `.viewer`: a wrapping
 * flex row, 22px between groups, 12px under the plate), and it floats only in
 * fullscreen, where there is imagery to spare and nothing else on the screen to
 * put it beside.
 *
 * A TEXT CHECK over the shipped sheet and template, and that is the limit of
 * what it promises: it catches the legend being floated again, or the rule being
 * written some other way — never a legend that renders wrongly for some other
 * reason. Rendered fidelity is a sweep, not a unit test.
 */
final class LegendPlacementTest extends TestCase
{
    /**
     * THE LEGEND IS A SIBLING UNDER THE MAP BODY, inside the plate because
     * fullscreen expands the plate and the legend comes along, and after the
     * body because it is drawn under the map it explains. What the rendered
     * plate makes of that is asserted through a real render, in the Twig
     * integration test.
     */
    public function testTheLegendFollowsTheMapBodyAsItsSibling(): void
    {
        $template = self::template();

        $body = strpos($template, '<div class="map-body">');
        $closed = strpos($template, '</div>{# the map body');
        $legend = strpos($template, 'class="map-legend"');

        self::assertIsInt($body, 'The plate stacks the filter row and the imagery in one box, which the height sizes.');
        self::assertIsInt($closed, 'That box is closed before the legend, so the legend is not inside the sized box.');
        self::assertIsInt($legend);
        self::assertLessThan($closed, $body);
        self::assertLessThan($legend, $closed, 'A legend inside the map body is a legend the plate height squeezes.');
    }

    /** And nothing in the plate is drawn between the map and its legend. */
    public function testTheLegendIsTheLastThingInThePlate(): void
    {
        self::assertStringEndsWith(
            "{% endif %}\n</div>\n",
            self::template(),
            'The legend closes the plate; anything after it would come between it and the map on the next change.',
        );
    }

    /**
     * THE DESIGN'S OWN VALUES — `uhifadhi.css`'s `.maplegend{display:flex;
     * gap:22px;flex-wrap:wrap;margin-top:12px}`, in the atlas's token names.
     */
    public function testTheLegendIsTheDesignsRowUnderThePlate(): void
    {
        $rule = self::rule('.map-legend');

        self::assertStringContainsString('display: flex', $rule);
        self::assertStringContainsString('gap: 22px', $rule);
        self::assertStringContainsString('flex-wrap: wrap', $rule);
        self::assertStringContainsString('margin-top: 12px', $rule);
    }

    /**
     * AND IT IS IN THE FLOW. An absolutely positioned legend is a legend over
     * the imagery, whatever else the rule says.
     */
    public function testTheLegendIsNotFloatedOverTheImagery(): void
    {
        $rule = self::rule('.map-legend');

        self::assertStringNotContainsString('position: absolute', $rule);
        self::assertStringNotContainsString('bottom:', $rule);
        self::assertStringNotContainsString('z-index', $rule);
    }

    /**
     * THE PANEL DRESSING BELONGS TO THE FLOATING STATE. Under the plate the
     * legend sits on the page's own ground and needs no plate of its own; over
     * imagery it needs the dark panel to be readable at all.
     */
    public function testThePanelUnderTheLegendIsTheFloatingStatesAlone(): void
    {
        $rule = self::rule('.map-legend');

        self::assertStringNotContainsString('background', $rule);
        self::assertStringNotContainsString('border', $rule);
        self::assertStringNotContainsString('padding', $rule);
    }

    /**
     * FULLSCREEN FLOATS IT — the one state where the legend goes back into the
     * imagery's bottom-right corner, because the plate is the whole screen and
     * the legend has to come along into it.
     */
    public function testFullscreenFloatsTheLegendInTheImagerysCorner(): void
    {
        $sheet = self::stylesheet();

        self::assertMatchesRegularExpression(
            '/\.map-plate:fullscreen > \.map-legend,\s*\n:fullscreen \.map-plate > \.map-legend \{[^}]*position: absolute/',
            $sheet,
            'Fullscreen is what floats the legend, and it is stated for a plate inside anything else that goes fullscreen too.',
        );

        $rule = self::floatingRule();

        self::assertStringContainsString('right: 10px', $rule);
        self::assertStringContainsString('bottom: 10px', $rule);
        self::assertStringContainsString('margin-top: 0', $rule, 'The gap under the plate is not a gap over the imagery.');
        self::assertStringContainsString('z-index: 5', $rule, 'Under the chrome, never over it: the controls must stay clickable.');
    }

    /** The rule for one selector of its own, as the sheet states it. */
    private static function rule(string $selector): string
    {
        $matched = preg_match(
            '/^'.preg_quote($selector, '/').' \{[^}]*\}/m',
            self::stylesheet(),
            $m,
        );
        self::assertSame(1, $matched, \sprintf('The sheet states a `%s` rule.', $selector));

        return $m[0];
    }

    /** The rule that floats the legend, which names the two fullscreen shapes. */
    private static function floatingRule(): string
    {
        $matched = preg_match(
            '/^\.map-plate:fullscreen > \.map-legend,\n:fullscreen \.map-plate > \.map-legend \{[^}]*\}/m',
            self::stylesheet(),
            $m,
        );
        self::assertSame(1, $matched, 'The sheet floats the legend in fullscreen, and only there.');

        return $m[0];
    }

    private static function stylesheet(): string
    {
        $sheet = file_get_contents(\dirname(__DIR__, 3).'/public/map.css');
        self::assertIsString($sheet);

        return $sheet;
    }

    private static function template(): string
    {
        $template = file_get_contents(\dirname(__DIR__, 3).'/templates/plate.html.twig');
        self::assertIsString($template);

        return $template;
    }
}
