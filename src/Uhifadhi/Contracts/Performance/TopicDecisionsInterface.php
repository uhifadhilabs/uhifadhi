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

use Uhifadhi\Contracts\Kpi\FigurePeriod;

/**
 * A TOPIC THAT CAN SAY WHAT SOMEBODY HAS TO DECIDE.
 *
 * THE THIRD OPTIONAL SEAM, and the same bargain the other two strike
 * ({@see TopicMovementInterface},
 * {@see \Uhifadhi\Bundle\AreaBundle\Overview\ContributesStylesheetInterface}):
 * a topic with no decisions to raise has no answer to give, so it does
 * not implement this and is not asked.
 *
 * WHY THE HOST CANNOT DERIVE THEM. It can see that a post has stood
 * empty 74 days; it cannot know that the ask is "fill it or close the
 * post" rather than "explain the figure", because that is what the
 * figure MEANS and meaning never crosses a seam. Every decision a topic
 * raises must come from figures it already holds — a seam that started
 * running its own queries for a briefing would be a second, slower
 * answer to a question the page has already asked.
 *
 * ORDER IS THE TOPIC'S. A topic returns them worst first; the page
 * prints the first few and says how many there are in all, because
 * "5 of 12" is a decision about attention and the page is allowed to
 * make that one.
 *
 * @see TopicMovement — one line about the period; a decision is one line about a THING TO DO
 */
interface TopicDecisionsInterface
{
    /**
     * Worst first, and empty where nothing needs deciding.
     *
     * @return list<TopicDecision>
     */
    public function decisions(PerformanceScope $scope, FigurePeriod $period): array;
}
