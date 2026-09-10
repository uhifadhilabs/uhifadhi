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

use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Bundle\ShellBundle\Test\VocabularyConformanceTestCase;

/**
 * THE TEAM SCREENS SPEND NOTHING NOBODY SHIPS.
 *
 * THE CHAIN IS THE SHEETS A PAGE ACTUALLY LINKS. `_stylesheets.html.twig` links
 * the frame's sheet, then the widget library's — the roster and the matrix are
 * both drawn as widget canvases — and then this bundle's own, which is last
 * because it is the one allowed to decorate.
 */
final class VocabularyConformanceTest extends VocabularyConformanceTestCase
{
    protected static function bundlePath(): string
    {
        return \dirname(__DIR__, 3);
    }

    protected static function alias(): string
    {
        return 'team';
    }

    protected static function ownStylesheets(): array
    {
        return ['team.css'];
    }

    protected static function linkedStylesheets(): array
    {
        $shell = \dirname(new \ReflectionClass(ShellBundle::class)->getFileName() ?: '').'/public';

        return [$shell.'/shell.css', $shell.'/widget.css'];
    }
}
