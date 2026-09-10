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
 * A BUNDLE WITH OTHER PEOPLE'S FILES UNDER IT — the conformance base pointed at
 * a checkout, rather than at a package as it was published.
 *
 * A working copy holds far more than the bundle: another module under
 * `vendor/`, a JavaScript package under `node_modules/`, the framework's
 * rendered templates under `var/`, the asset pipeline's digested copies under
 * `public/assets/`, and the suite's own fixtures under `tests/`. Every one of
 * those carries markup, and none of it is markup this bundle draws.
 *
 * So each of those five directories holds a file naming an icon prefix this
 * bundle may not use — `storage:`, `nodemod:`, `cached:`, `compiled:`,
 * `suite:`. The bundle's own page draws `vendoring:mark` and nothing else, and
 * the conformance base has to agree with it.
 *
 * The file is named for the bundle rather than ending in `Test`, so the runner
 * collects it as a fixture rather than as a suite.
 */
final class VendoringBundle extends VocabularyConformanceTestCase
{
    protected static function bundlePath(): string
    {
        return __DIR__.'/vendoring';
    }

    protected static function alias(): string
    {
        return 'vendoring';
    }

    protected static function ownStylesheets(): array
    {
        return ['vendoring.css'];
    }
}
