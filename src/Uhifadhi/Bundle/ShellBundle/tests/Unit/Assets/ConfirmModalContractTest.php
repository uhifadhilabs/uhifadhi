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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Unit\Assets;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * THE ONE WAY THE PRODUCT ASKS BEFORE IT DESTROYS SOMETHING.
 *
 * FOUR BUNDLES WRITE THE TRIGGER AND ONE SHIPS THE DIALOG. The shell's widget
 * library, the area's zones and stations, patrol and incident all mark their
 * destructive submit with `confirm-modal` and four values; the controller that
 * answers is here. It was referenced by all of them and shipped by nobody for
 * a while, and the symptom was silent — every one of those buttons deleted
 * without asking, and no test failed, because a functional test builds its
 * own request and never looks at an attribute.
 *
 * SO THIS READS THE FILES AS TEXT, on both sides of the seam: the names the
 * templates write, and the names the controller listens for. A rename on
 * either side fails here rather than in production, silently.
 *
 * THE SIBLING MODULES ARE CHECKED WHERE THEY ARE CHECKED OUT and skipped
 * where they are not: this repo ships alone, and a test that required two
 * other repositories to exist would be a test nobody could run.
 */
#[CoversNothing]
final class ConfirmModalContractTest extends TestCase
{
    /** The identifier every trigger names and the controller is registered as. */
    private const string CONTROLLER = 'confirm-modal';

    /** The four questions a trigger hands over, spelled as Stimulus reads them. */
    private const array VALUES = ['title', 'message', 'confirm-label', 'danger'];

    public function testTheControllerIsShippedAndRegisteredUnderTheNameTheTriggersUse(): void
    {
        self::assertFileExists(self::controllerPath(), 'The confirm-modal controller is referenced by four bundles; it has to exist.');

        $package = json_decode((string) file_get_contents(\dirname(__DIR__, 3).'/assets/package.json'), true);
        self::assertIsArray($package);

        $symfony = $package['symfony'] ?? null;
        self::assertIsArray($symfony);

        $controllers = $symfony['controllers'] ?? null;

        self::assertIsArray($controllers);
        self::assertArrayHasKey(self::CONTROLLER, $controllers, 'A controller nobody registers is a controller nobody loads.');

        $entry = $controllers[self::CONTROLLER];
        self::assertIsArray($entry);
        self::assertSame('controllers/confirm_modal_controller.js', $entry['main'] ?? null);
        self::assertTrue($entry['enabled'] ?? false);
    }

    public function testTheControllerReadsTheFourValuesEveryTriggerHandsIt(): void
    {
        $controller = self::controller();

        foreach (self::VALUES as $value) {
            self::assertMatchesRegularExpression(
                '/'.preg_quote(self::camel($value), '/').':\s*(String|Boolean)/',
                $controller,
                \sprintf('The controller declares no `%s` value, so a trigger that sets it is talking to nobody.', $value),
            );
        }

        // The action the triggers bind, and the two ways out of the question.
        self::assertStringContainsString('ask(', $controller);
        self::assertStringContainsString('preventDefault', $controller);
        self::assertStringContainsString('Escape', $controller);
    }

    /**
     * IT SUBMITS THE FORM IT INTERRUPTED, and announces itself first: a page
     * that wants to know a destructive thing was confirmed listens for the
     * event rather than wrapping the button.
     */
    public function testConfirmingSubmitsTheTriggersOwnFormAndSaysSo(): void
    {
        $controller = self::controller();

        self::assertStringContainsString('confirm-modal:confirmed', $controller);
        self::assertStringContainsString('requestSubmit', $controller);
    }

    /**
     * THE SHORT NAME IS THE CONTRACT, and the shell is what makes it real.
     * StimulusBundle derives `uhifadhi--shell-bundle--confirm-modal` from the
     * composer name; every trigger in the product writes `confirm-modal`,
     * because a module must not have to spell the shell's package to ask a
     * question. The body instance publishes it.
     */
    public function testTheShortPublicNameIsRegisteredByTheBodysOwnInstance(): void
    {
        $controller = self::controller();

        self::assertStringContainsString("'confirm-modal'", $controller);
        self::assertStringContainsString('this.application.register(', $controller);

        self::assertStringContainsString(
            'uhifadhi--shell-bundle--confirm-modal',
            (string) file_get_contents(\dirname(__DIR__, 3).'/templates/document.html.twig'),
            'Nothing mounts the controller, so the short name is never published and every trigger asks nobody.',
        );
    }

    public function testEveryTriggerInThisRepositoryAndItsSiblingsSpellsTheSameNames(): void
    {
        $checked = 0;
        foreach (self::triggerFiles() as $label => $path) {
            $markup = (string) file_get_contents($path);
            ++$checked;

            self::assertStringContainsString("stimulus_controller('".self::CONTROLLER."')", $markup, $label);
            self::assertStringContainsString('click->'.self::CONTROLLER.'#ask', $markup, $label);

            foreach (self::VALUES as $value) {
                self::assertStringContainsString(
                    \sprintf('data-%s-%s-value', self::CONTROLLER, $value),
                    $markup,
                    \sprintf('%s hands over no "%s".', $label, $value),
                );
            }
        }

        self::assertGreaterThan(0, $checked, 'Nothing in this repository asks before it destroys anything.');
    }

    /**
     * The templates that mark a destructive control, here and in the sibling
     * modules that are checked out beside this repository.
     *
     * @return array<string, string>
     */
    private static function triggerFiles(): array
    {
        $core = \dirname(__DIR__, 5);
        $siblings = \dirname($core, 2);

        $candidates = [
            'the shell’s widget library' => $core.'/Bundle/ShellBundle/templates/widget/_library.html.twig',
            'the area’s zones section' => $core.'/Bundle/AreaBundle/templates/zone/configure.html.twig',
            'the area’s stations section' => $core.'/Bundle/AreaBundle/templates/station/configure.html.twig',
            'patrol-module' => $siblings.'/patrol-module/templates/widgets/show.html.twig',
            'incident-module' => $siblings.'/incident-module/templates/dashboard/widgets.html.twig',
        ];

        return array_filter($candidates, is_file(...));
    }

    private static function controllerPath(): string
    {
        return \dirname(__DIR__, 3).'/assets/controllers/confirm_modal_controller.js';
    }

    private static function controller(): string
    {
        return (string) file_get_contents(self::controllerPath());
    }

    /** `confirm-label` is `confirmLabel` to Stimulus. */
    private static function camel(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $value))));
    }
}
