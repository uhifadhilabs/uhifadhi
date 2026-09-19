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

namespace Uhifadhi\Contracts\Area;

/**
 * HOW SOMEBODY'S DAY READS — derived, on every read, and stored nowhere.
 *
 * THE CLAIM IS THE RANGER'S AND THIS IS THE READING OF IT. A check-in
 * says "at post"; whether the positions bear that out is this. It is
 * computed from the post's catchment at the moment of asking, so a
 * catchment corrected next month re-derives every day that used it —
 * which is exactly what a stored verdict could not do.
 *
 * THE TWO AT-POST STATES ARE ONE CLAIM WITH AND WITHOUT PROOF.
 * `Unverified` is never an accusation: no fix arrived, or the post has
 * no ring, or the fix fell outside it — {@see UnverifiedReason} says
 * which, and none of the three is "absent".
 *
 * NO CHECK-IN IS ITS OWN STATE, and it is not "absent" either: somebody
 * may be rostered and not yet have tapped, and a page says so rather
 * than marking them missing at 06:01.
 */
enum DayState: string
{
    /** At post, and a position inside the post's ring says so. */
    case AtPostVerified = 'at_post_verified';

    /** At post, and nothing proves it either way. */
    case AtPostUnverified = 'at_post_unverified';

    /** On duty away from a post — escorting, in court, outside the park. */
    case WorkingElsewhere = 'working_elsewhere';

    /** On duty inside the park, on an assignment that is not a post. */
    case Special = 'special';

    /** Not on duty: unfit, on leave, stood down. */
    case NotWorking = 'not_working';

    /** Nobody has reported this day. Not the same as not working. */
    case NoCheckIn = 'no_check_in';

    /** Whether the person counted as on duty. */
    public function countsAsPresent(): bool
    {
        return self::NotWorking !== $this && self::NoCheckIn !== $this;
    }

    public function label(): string
    {
        return match ($this) {
            self::AtPostVerified => 'at post',
            self::AtPostUnverified => 'at post, unverified',
            self::WorkingElsewhere => 'working elsewhere',
            self::Special => 'special assignment',
            self::NotWorking => 'not working',
            self::NoCheckIn => 'no check-in',
        };
    }
}
