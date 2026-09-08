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

use Uhifadhi\Bundle\ShellBundle\Contract\AreaShellSourceInterface;

/**
 * A host's answer to "which sibling screens does the thing I am currently
 * looking at have, and which one am I on".
 *
 * Note the shape of the question: the shell does not pass an area, because it
 * does not know what an area is and has no entity to pass. The source resolves
 * the current request itself — which the host is already doing in
 * SidebarRuntime, and which keeps the area's type out of the shell entirely.
 */
final class FixtureAreaShellSource implements AreaShellSourceInterface
{
    public function tabs(): iterable
    {
        return HostKernel::$areaTabs;
    }

    public function place(): ?string
    {
        return HostKernel::$place;
    }
}
