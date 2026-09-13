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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Unit\Test\Fixtures;

use Uhifadhi\Bundle\ShellBundle\Test\TimeConformanceTestCase;

/**
 * A MODULE THAT PRINTS THE SERVER'S ZONE, every way at once — the conformance
 * base pointed at a bundle whose times were formatted where the data is.
 *
 * Its page prints a date with no element around it, so nothing can rewrite it;
 * it draws a `<time>` with no machine instant, which the frame reads and skips;
 * and it asks for a shape the frame does not answer, which silently becomes
 * Intl's paragraph in a cell built for a stamp. Each of those is one of the
 * base's assertions, and this fixture is what proves the assertion can fail — a
 * conformance suite nobody has watched fail is a conformance suite that might be
 * asserting nothing.
 *
 * The file is named for the bundle rather than ending in `Test`, so the runner
 * collects it as a fixture rather than as a suite.
 */
final class DriftingTimeBundle extends TimeConformanceTestCase
{
    protected static function bundlePath(): string
    {
        return __DIR__.'/drifttime';
    }

    /**
     * What the base read, for the one assertion that is about the reading rather
     * than about a drift: a Twig comment is prose and must be gone before
     * anything is looked for.
     *
     * @return array<string, string>
     */
    public static function pages(): array
    {
        return self::templates();
    }
}
