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

use Uhifadhi\Bundle\ShellBundle\Test\TimeConformanceTestCase;

/**
 * THE CORE'S OWN SCREENS HOLD TO WHAT THE CORE ASKS OF A MODULE. Every instant
 * these templates print reaches the browser as a `<time datetime>` element, so
 * the frame reads it in the zone of whoever is looking.
 */
final class TimeConformanceTest extends TimeConformanceTestCase
{
    protected static function bundlePath(): string
    {
        return \dirname(__DIR__, 3);
    }
}
