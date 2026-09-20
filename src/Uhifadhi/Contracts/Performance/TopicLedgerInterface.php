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
 * A TOPIC WHOSE READING IS A LEDGER, NOT A FIGURE.
 *
 * Most topics answer the across-the-topics strip with one number and where it
 * moved: patrols logged, incidents open, people posted. A LEDGER does not
 * answer that question at all — goals declared is a list of commitments with
 * a target and a date each, and the number of rows in it is not a performance
 * reading, it is how many rows there are. The topic is read in full where the
 * ledger is drawn, and the strip is not that place.
 *
 * WHY THIS IS AN INTERFACE AND NOT A LIST OF KEYS. The strip is FOUR CARDS
 * (ruled: a figure row is four to a row, and a fifth wrapped an orphan onto a
 * second line on a small laptop), so something has to give way — and what
 * gives way is decided by the topic, which knows what kind of reading it has,
 * rather than by a page naming a topic it is not allowed to know about. A
 * module's topic may declare this for exactly the same reason the host's does.
 *
 * THE TOPIC IS NOT HIDDEN. It keeps its row in the sidebar, its tab in the
 * Topics register and its own record page; what it declines is a slot on the
 * one strip that asks every topic for a single moving number.
 *
 * Declared beside {@see PerformanceTopicProviderInterface}, never instead of
 * it — this says something about a topic, it is not a second kind of topic.
 */
interface TopicLedgerInterface
{
}
