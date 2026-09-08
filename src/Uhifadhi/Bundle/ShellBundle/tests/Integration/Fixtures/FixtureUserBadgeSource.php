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

use Uhifadhi\Contracts\Shell\UserBadge;
use Uhifadhi\Contracts\Shell\UserBadgeSourceInterface;

/**
 * A host's answer to "who does the top bar name". A real one resolves the
 * signed-in account and folds its organisation and role into the context line;
 * that it can be replaced by one static is the registry working — and that it
 * returns null by default is the shell's charter, that a host with no viewer to
 * name is a working installation.
 *
 * Read at call time, never at construction — the same-day promise the navigation contract
 * makes too.
 */
final class FixtureUserBadgeSource implements UserBadgeSourceInterface
{
    public function badge(): ?UserBadge
    {
        return HostKernel::$userBadge;
    }
}
