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

use Uhifadhi\Bundle\ShellBundle\Test\VocabularyConformanceTestCase;

/**
 * THE MAP'S SHEET SPENDS ONLY WHAT THE CHAIN DEFINES.
 *
 * This bundle draws no pages of its own — a map is mounted into somebody
 * else's — so the class sweep has nothing to read and the sheet is the whole
 * of what is checked: its tokens come from the shell's, and it restates none of
 * the shell's rules.
 */
final class VocabularyConformanceTest extends VocabularyConformanceTestCase
{
    protected static function bundlePath(): string
    {
        return \dirname(__DIR__, 3);
    }

    protected static function alias(): string
    {
        return 'atlas';
    }

    protected static function ownStylesheets(): array
    {
        return ['map.css'];
    }
}
