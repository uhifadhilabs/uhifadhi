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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Integration\Fixtures;

use Uhifadhi\Bundle\ShellBundle\Contract\StylesheetSourceInterface;

/**
 * A package that ships a component drawn on other people's pages, in the
 * smallest honest form: something that names the sheets its component
 * needs and nothing else.
 *
 * Read at render time, never at construction — the same live read every
 * other shell contract keeps.
 */
final class FixtureStylesheetSource implements StylesheetSourceInterface
{
    public function stylesheets(): iterable
    {
        yield from HostKernel::$stylesheets;
    }
}
