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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Unit\Template;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * FOLD ALL / OPEN ALL — THE SAME THREE NAMES ON BOTH SIDES OF THE SEAM.
 *
 * A control whose action names a method the controller does not have is a
 * button that renders perfectly and does nothing, and no test that speaks HTTP
 * can see it: the server's job ended when the markup left. So the seam is
 * asserted as TEXT — the identifier in the template and the identifier in the
 * shipped asset must be literally the same string, and the asset must be one
 * the host is told to enable.
 *
 * THE FOLDS THEMSELVES ARE NOT TESTED HERE, because they are native
 * `<details>` and the browser answers for them. What is tested is only what we
 * wrote: the pair of shortcuts over them.
 */
#[CoversNothing]
final class FoldShortcutsAreWiredTest extends TestCase
{
    private const string CONTROLLER = 'uhifadhi--team-bundle--folds';

    /** @return list<array{string}> */
    public static function surfaces(): array
    {
        return [['positions/show.html.twig'], ['positions/configure.html.twig']];
    }

    /** Both matrix surfaces attach the controller the shortcuts need. */
    #[DataProvider('surfaces')]
    public function testTheCardCarryingTheMatrixAttachesTheController(string $template): void
    {
        self::assertStringContainsString(
            'data-controller="'.self::CONTROLLER.'"',
            self::template($template),
            'The shortcuts are drawn inside this card, so the controller has to be on it.',
        );
    }

    /** Every fold the shortcuts claim to reach is a target they actually reach. */
    public function testEveryFoldIsATargetIncludingTheOrphanedOne(): void
    {
        $matrix = self::template('positions/_matrix.html.twig');

        self::assertSame(
            substr_count($matrix, '<details class="pmf pmfg"'),
            substr_count($matrix, 'data-'.self::CONTROLLER.'-target="fold"'),
            'A fold with no target is a group "Fold all" silently skips.',
        );
    }

    /** The two actions name two methods the shipped controller defines. */
    public function testTheTwoActionsNameMethodsTheAssetDefines(): void
    {
        $matrix = self::template('positions/_matrix.html.twig');
        $asset = file_get_contents(\dirname(__DIR__, 3).'/assets/controllers/folds_controller.js');
        self::assertIsString($asset);

        foreach (['foldAll', 'openAll'] as $method) {
            self::assertStringContainsString(
                'data-action="'.self::CONTROLLER.'#'.$method.'"',
                $matrix,
            );
            self::assertStringContainsString($method.'()', $asset);
        }

        self::assertStringContainsString("static targets = ['fold']", $asset);
    }

    /** A controller a host never enables is a file nobody loads. */
    public function testTheAssetIsDeclaredForTheHostToEnable(): void
    {
        $package = file_get_contents(\dirname(__DIR__, 3).'/assets/package.json');
        self::assertIsString($package);

        /** @var array{symfony: array{controllers: array<string, array{main: string}>}} $declared */
        $declared = json_decode($package, true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(
            'controllers/folds_controller.js',
            $declared['symfony']['controllers']['folds']['main'] ?? null,
        );
    }

    private static function template(string $path): string
    {
        $source = file_get_contents(\dirname(__DIR__, 3).'/templates/'.$path);
        self::assertIsString($source);

        return $source;
    }
}
