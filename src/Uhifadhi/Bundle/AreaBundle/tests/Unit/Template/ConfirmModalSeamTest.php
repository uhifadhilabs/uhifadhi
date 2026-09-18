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
 * EVERY DESTRUCTIVE CONTROL ASKS, AND ASKS THE SAME WAY.
 *
 * THE DIALOG IS NOT THIS BUNDLE'S. An installation ships one `confirm-modal`
 * Stimulus controller and every surface in the product hands it the question;
 * a browser `confirm()`, a bespoke page state or a second dialog of this
 * module's own would each be a second way to ask, and a person would have to
 * learn both.
 *
 * NOTHING THAT TALKS HTTP CAN CATCH A NAME THAT DRIFTED. A functional test
 * builds its own request and never looks at an attribute; only reading the
 * templates AS TEXT and comparing the names on both sides of the seam does. So
 * the spelling here is compared against the SHELL'S own — if the platform
 * renames the controller or one of its values, this fails in the bundle that
 * would otherwise have gone on rendering a button that asks nobody.
 *
 * THE BUTTON IS THE FORM'S OWN SUBMIT, deliberately. An installation that
 * ships no controller still posts, still carries its token, and loses only the
 * question — which is the right way round for a control that must not become
 * unreachable because a script did not load.
 */
final class ConfirmModalSeamTest extends TestCase
{
    /** The five names the trigger and the controller have to agree on. */
    private const array HOOKS = [
        'confirm-modal',
        'click->confirm-modal#ask',
        'data-confirm-modal-title-value',
        'data-confirm-modal-message-value',
        'data-confirm-modal-confirm-label-value',
        'data-confirm-modal-danger-value',
    ];

    public function testTheZonesSectionAsksWithTheNamesTheShellUses(): void
    {
        $ours = self::template();
        $shell = self::shellsOwnTrigger();

        foreach (self::HOOKS as $hook) {
            self::assertStringContainsString($hook, $shell, \sprintf(
                'The shell no longer spells "%s": the confirm-modal contract moved and this bundle is now asking nobody.',
                $hook,
            ));
            self::assertStringContainsString($hook, $ours, \sprintf(
                'The zones section writes a destructive control that does not carry "%s".',
                $hook,
            ));
        }
    }

    /**
     * BOTH of them, not one: the page has two destructive controls — remove one
     * zone, remove the set — and a test that counted only the presence of the
     * attribute would pass with the second one unguarded.
     */
    public function testBothDestructiveControlsAsk(): void
    {
        $template = self::template();

        self::assertSame(2, substr_count($template, "stimulus_controller('confirm-modal')"));
        self::assertSame(2, substr_count($template, 'data-confirm-modal-danger-value="true"'));

        // The question names the thing it is about, in both.
        self::assertStringContainsString('data-confirm-modal-title-value="Remove “{{ row.name }}”?"', $template);
        self::assertStringContainsString('data-confirm-modal-title-value="Remove all {{ set.count }}', $template);
    }

    /** The trigger is the form's own submit, so a page with no script still writes. */
    public function testEachAskingControlSubmitsItsOwnFormAndCarriesItsToken(): void
    {
        $template = self::template();

        foreach (['area_zone_remove', 'area_zones_clear'] as $route) {
            $form = self::formPostingTo($template, $route);
            self::assertStringContainsString('name="_token"', $form, \sprintf('The %s form carries no token.', $route));
            self::assertStringContainsString('type="submit"', $form, \sprintf('The %s trigger is not a submit.', $route));
            self::assertStringContainsString("stimulus_controller('confirm-modal')", $form);
        }
    }

    /** The browser's own dialog is not an acceptable substitute anywhere here. */
    public function testTheBrowsersOwnDialogIsNotUsed(): void
    {
        self::assertStringNotContainsString('confirm(', self::template());
        self::assertStringNotContainsString('onclick=', self::template());
    }

    private static function template(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 3).'/templates/zone/configure.html.twig');
    }

    /** The shell's own asking control, which is the spelling everybody copies. */
    private static function shellsOwnTrigger(): string
    {
        return (string) file_get_contents(
            \dirname(__DIR__, 4).'/ShellBundle/templates/widget/_library.html.twig',
        );
    }

    private static function formPostingTo(string $template, string $route): string
    {
        $start = strpos($template, "path('".$route."'");
        self::assertIsInt($start, \sprintf('Nothing in the template posts to %s.', $route));

        $end = strpos($template, '</form>', $start);
        self::assertIsInt($end);

        return substr($template, $start, $end - $start);
    }
}
