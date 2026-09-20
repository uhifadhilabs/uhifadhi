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
 * IT IS READ IN TWO PLACES. On a topic's five figures it says what a
 * scope-wide total is; on a MATRIX COLUMN it says the same thing per
 * department, which is the only way the host can add a figure up for
 * one department across every topic. The same role means the same
 * thing in both.
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

    /**
     * HOW MANY THINGS THIS MODULE PUT IN FRONT OF SOMEBODY — an open
     * incident, an overdue patrol, a claim past its target.
     *
     * ONLY A MODULE RAISES ONE. A department that attaches nothing has
     * no items and cannot have any, which is why the host's Attention
     * topic folds those departments rather than drawing them at nought:
     * a nought there would say they were asked and answered none.
     */
    case ItemsRaised = 'items_raised';

    /**
     * AND HOW MANY OF THEM NOBODY OWNS — raised against a department
     * but with no position answerable for them.
     *
     * A SEPARATE ROLE BECAUSE IT IS A SEPARATE QUESTION. "52 raised" is
     * workload and "9 unowned" is a gap in the org chart; one number
     * covering both would hide whichever moved.
     */
    case ItemsUnowned = 'items_unowned';

    /**
     * ITEMS THE MODULE CLOSED IN THIS PERIOD — the other half of the
     * attention reading, and the half a reader needs to know whether a
     * rising count of raised items is a rising workload or a standing
     * one being worked through.
     *
     * A MODULE THAT RAISES ITEMS SHOULD PUBLISH THIS. Until it does the
     * card states its own absence rather than reading zero: "nothing
     * resolved" and "nobody reported resolving anything" are different
     * facts, and the second is the honest one to draw.
     */
    case ItemsResolved = 'items_resolved';
}
