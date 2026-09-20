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

namespace Uhifadhi\Contracts\Settings;

/**
 * HOW SOON SOMEBODY HAS TO ACT ON AN INSTALLATION-LEVEL ITEM.
 *
 * THE SAME THREE THE AREA'S ATTENTION LIST USES, and deliberately the same
 * three: a person who has learnt to read one queue has learnt to read the
 * other, and a second vocabulary for the same judgement would be two scales
 * on one screen.
 */
enum DecisionUrgency: string
{
    /** Today. Somebody is waiting, or something is already wrong. */
    case Now = 'now';

    /** This week. It will be wrong if nobody gets to it. */
    case Soon = 'soon';

    /** Standing. True until somebody decides otherwise, and worth knowing. */
    case Watch = 'watch';
}
