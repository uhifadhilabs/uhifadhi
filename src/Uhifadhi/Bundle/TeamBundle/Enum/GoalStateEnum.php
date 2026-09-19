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

namespace Uhifadhi\Bundle\TeamBundle\Enum;

/**
 * HOW A GOAL READS, AND THE FIVE ANSWERS THERE ARE.
 *
 * TWO EMPTINESSES, NOT ONE. A department with no goal declared and a goal
 * whose module has published nothing look alike on a page and mean
 * opposite things: nobody set a target, versus nobody has reported one.
 * The design draws the first as a DASHED ring and the second as a SOLID
 * hollow one, and a rail that ships four states lies about whichever it
 * left out.
 *
 * THE STATE IS DERIVED, NEVER STORED — see {@see \Uhifadhi\Bundle\TeamBundle\Entity\DepartmentGoal::stateFor()}.
 * A stored state is a state somebody has to remember to update, and the
 * rail would go on saying "met" for a month after the figure moved.
 */
enum GoalStateEnum: string
{
    /** The figure is on the right side of the target. */
    case Met = 'met';

    /** It is not, and the period is still open — the only actionable state. */
    case AtRisk = 'at_risk';

    /** It is not, and the period has closed. */
    case Missed = 'missed';

    /** A goal is declared and no installed module has published its figure. */
    case NoFigure = 'no_figure';

    /** Nobody declared one. Not a failure, and never drawn as one. */
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Met => 'met',
            self::AtRisk => 'at risk',
            self::Missed => 'missed',
            self::NoFigure => 'no figure yet',
            self::None => 'no goal declared',
        };
    }

    /** The mark's own modifier, as the design's dot rail spells it. */
    public function mark(): string
    {
        return match ($this) {
            self::Met => '',
            self::AtRisk => 'warn',
            self::Missed => 'fail',
            self::NoFigure => 'nofig',
            self::None => 'none',
        };
    }
}
