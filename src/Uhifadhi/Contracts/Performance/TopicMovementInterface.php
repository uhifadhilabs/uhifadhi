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
 * A TOPIC THAT CAN SAY WHAT MOVED, in one line.
 *
 * A SECOND, OPTIONAL INTERFACE rather than a method on
 * {@see PerformanceTopicProviderInterface} — the same bargain
 * {@see \Uhifadhi\Bundle\AreaBundle\Overview\ContributesStylesheetInterface}
 * strikes, and for the same reason: a topic with nothing to say about a
 * period has no answer to give, and a contract that made it answer
 * anyway is a contract that has started guessing. A topic that does not
 * implement this is simply not asked.
 *
 * WHY THE HOST CANNOT WRITE IT. The host has every figure on the page
 * and still cannot say that distance rose while coverage fell, because
 * knowing that those two explain each other is knowing what the figures
 * MEAN — which is the one thing a seam never carries. So the sentence
 * is the topic's, and the tone the topic's too; the host places it.
 *
 * WHERE IT IS READ. The topics register puts it under each card's
 * figure, and the briefing's "what changed" is one line per topic.
 * Both are the same read: a director's page asks every topic the same
 * question and prints the answers in the order the topics come.
 */
interface TopicMovementInterface
{
    /** Null where nothing about this period is worth a sentence. */
    public function movement(PerformanceScope $scope, FigurePeriod $period): ?TopicMovement;
}
