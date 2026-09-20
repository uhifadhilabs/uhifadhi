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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Fixtures;

use Uhifadhi\Contracts\Roster\Watch;
use Uhifadhi\Contracts\Roster\WatchProviderInterface;

/**
 * A ROSTER, STANDING IN FOR THE MODULE THAT OWNS ONE.
 *
 * WHEN A WATCH WAS MEANT TO END is the roster's to say, and this bundle has
 * no roster — so without a stand-in, the whole "the watch is over and nobody
 * checked out" branch of {@see \Uhifadhi\Bundle\AreaBundle\Service\PresenceService}
 * is unreachable from its own suite. That is how the branch came to read the
 * wall clock without anybody noticing.
 *
 * TAGGED BY HAND in the test kernel, exactly as a real module bundle has to
 * tag it: a fixture that leaned on autoconfiguration would prove a wiring no
 * installation uses.
 *
 * SETTABLE AT RUNTIME, because a suite asking "is the watch over at 10:30?"
 * and "is it over at 18:00?" is asking about one roster, not two.
 */
final class FixtureRoster implements WatchProviderInterface
{
    /** @var list<Watch> */
    public array $watches = [];

    public function watchesFor(string $areaUuid, string $personUuid, string $from, string $to): array
    {
        return $this->watches;
    }
}
