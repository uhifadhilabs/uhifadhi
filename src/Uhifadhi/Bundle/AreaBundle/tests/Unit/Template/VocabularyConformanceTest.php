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

use Uhifadhi\Bundle\AtlasBundle\AtlasBundle;
use Uhifadhi\Bundle\ShellBundle\Test\VocabularyConformanceTestCase;

/**
 * THE AREA SCREENS SPEND NOTHING NOBODY SHIPS.
 *
 * THE CHAIN IS THE SHEETS A PAGE ACTUALLY LINKS, and nothing else: the shell's
 * document links shell.css, an area page that draws a map links the atlas's,
 * and this bundle's own sheet is last because it is the one allowed to
 * decorate. The widget library's sheet is deliberately absent — no template
 * here links it, so a name it happens to define is a name these pages do not
 * get.
 */
final class VocabularyConformanceTest extends VocabularyConformanceTestCase
{
    protected static function bundlePath(): string
    {
        return \dirname(__DIR__, 3);
    }

    protected static function alias(): string
    {
        return 'area';
    }

    protected static function ownStylesheets(): array
    {
        return ['area.css'];
    }

    protected static function linkedStylesheets(): array
    {
        return [
            ...parent::linkedStylesheets(),
            \dirname(new \ReflectionClass(AtlasBundle::class)->getFileName() ?: '').'/public/map.css',
        ];
    }
}
