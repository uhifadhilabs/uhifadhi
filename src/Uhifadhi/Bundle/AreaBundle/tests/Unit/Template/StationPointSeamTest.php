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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;

/**
 * THE PICKER'S TWO SIDES SPELL THE SAME NAMES.
 *
 * NOTHING THAT TALKS HTTP CAN CATCH A TARGET THAT DRIFTED. A functional test
 * posts a form and never looks at an attribute, and the picker is entirely
 * attributes: a renamed target does not fail anything — it just stops
 * working, and the page goes on rendering a plate that answers no click.
 *
 * SO THE TEMPLATE IS READ AS TEXT against the controller's own declarations.
 * The three caption states are asserted too: a plate that is armed and does
 * not say what for is a plate that moves the wrong post.
 */
final class StationPointSeamTest extends TestCase
{
    private const string CONTROLLER = 'uhifadhi--area-bundle--station-point';

    /** Every target the controller declares, as the template must spell it. */
    public function testTheTemplateExposesEveryTargetTheControllerDeclares(): void
    {
        $template = self::template();

        foreach (self::declaredTargets() as $target) {
            self::assertStringContainsString(
                \sprintf('data-%s-target="%s"', self::CONTROLLER, $target),
                $template,
                \sprintf('The picker declares a "%s" target that the stations section never renders.', $target),
            );
        }
    }

    /** The actions the template calls are methods the controller has. */
    public function testEveryActionTheTemplateCallsIsAMethodTheControllerHas(): void
    {
        $controller = self::controller();

        foreach (['arm', 'use'] as $method) {
            self::assertStringContainsString(
                \sprintf('data-action="%s#%s"', self::CONTROLLER, $method),
                self::template(),
            );
            self::assertMatchesRegularExpression(
                '/\n    '.$method.'\(/',
                $controller,
                \sprintf('The template calls #%s and the controller does not have it.', $method),
            );
        }
    }

    /**
     * ARMING NAMES A MODE, A FORM AND A STATION, because a click on the ground
     * has to reach one pair of boxes and the caption has to name one post.
     */
    public function testArmingCarriesTheModeTheFormAndTheName(): void
    {
        $template = self::template();

        foreach (['mode', 'form', 'name'] as $param) {
            self::assertStringContainsString(\sprintf('data-%s-%s-param=', self::CONTROLLER, $param), $template);
        }

        self::assertStringContainsString('-mode-param="add"', $template);
        self::assertStringContainsString('-mode-param="move"', $template);
    }

    /** The three states the caption can be in, in the design's words. */
    public function testTheCaptionCarriesTheThreeStates(): void
    {
        $template = self::template();

        self::assertStringContainsString('<b>Pick the point</b> &middot; click the ground', $template);
        self::assertStringContainsString('&middot; click the ground', $template);
        self::assertStringContainsString('&middot; drag the pin', $template);
        // The station a caption is about is written in by the controller.
        self::assertSame(2, substr_count($template, 'data-station-name'));
    }

    /** @return list<string> */
    private static function declaredTargets(): array
    {
        preg_match('/static targets = \[(.+?)\];/s', self::controller(), $found);
        preg_match_all("/'([^']+)'/", $found[1] ?? '', $targets);

        self::assertNotEmpty($targets[1], 'the picker declares no targets at all');

        return $targets[1];
    }

    private static function controller(): string
    {
        return (string) file_get_contents(__DIR__.'/../../../assets/controllers/station_point_controller.js');
    }

    private static function template(): string
    {
        return (string) file_get_contents(__DIR__.'/../../../templates/station/configure.html.twig');
    }
}
