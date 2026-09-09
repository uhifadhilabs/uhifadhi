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

use Uhifadhi\Bundle\ShellBundle\Test\VocabularyConformanceTestCase;

/**
 * A MODULE THAT HAS DRIFTED, EVERY WAY AT ONCE — the conformance base pointed
 * at a bundle written by somebody who reached past their own vocabulary.
 *
 * Its page draws `lucide:plus`, which belongs to the installation; it draws
 * `drift:absent`, for which it ships no file; and its sheet restates `.cta`,
 * which the shell already carries. Each of those is one of the base's
 * assertions, and this fixture is what proves the assertion can fail — a
 * conformance suite nobody has watched fail is a conformance suite that might
 * be asserting nothing.
 *
 * The file is named for the bundle rather than ending in `Test`, so the runner
 * collects it as a fixture rather than as a suite.
 */
final class DriftingBundle extends VocabularyConformanceTestCase
{
    protected static function bundlePath(): string
    {
        return __DIR__.'/drift';
    }

    protected static function alias(): string
    {
        return 'drift';
    }

    protected static function ownStylesheets(): array
    {
        return ['drift.css'];
    }
}
