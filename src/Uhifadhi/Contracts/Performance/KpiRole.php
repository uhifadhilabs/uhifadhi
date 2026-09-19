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

namespace Uhifadhi\Contracts\Performance;

/**
 * WHAT ONE OF A TOPIC'S FIVE FIGURES IS FOR, where the host needs to know.
 *
 * MOST FIGURES HAVE NO ROLE, AND THAT IS THE NORMAL CASE. A topic's
 * figures are its own: the host draws them, names them as the module
 * named them, and makes no claim about what they mean. A role is the
 * exception — a figure the HOST has to find among somebody else's five
 * in order to add it up across topics.
 *
 * THE FIRST IS RECORDS. "Records written, by department" sums what every
 * topic wrote this period, and without a role the host would have to
 * guess which of five figures that is — by label, which is the module's
 * own word and may be "cases", "sightings" or "patrols logged".
 *
 * THE ENUM GROWS ONE CASE AT A TIME, and each one is a question the host
 * genuinely asks across topics. A role that only one surface reads is a
 * key, not a role.
 */
enum KpiRole: string
{
    /** How many records this topic's module wrote in the period. */
    case Records = 'records';
}
