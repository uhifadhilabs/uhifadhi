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
 * WHAT MOVED IN THIS TOPIC THIS PERIOD, in the topic's own words.
 *
 * A SENTENCE, BECAUSE THE FIGURES DO NOT SAY IT. "Coverage 58 %, down
 * one point" is on the card already; what a reader cannot get from it
 * is that distance ROSE while coverage fell — longer patrols over less
 * ground — and that is the line somebody acts on. Only the topic knows
 * which of its own figures explain each other.
 *
 * ONE LINE, AND A FRAGMENT RATHER THAN A PARAGRAPH. It is read in a
 * card the size of a card; a topic with two things to say says the one
 * that matters, which is the judgment a topic is asked to make here.
 */
final readonly class TopicMovement
{
    public function __construct(
        /** One line, in the topic's own vocabulary. */
        public string $sentence,
        public MovementTone $tone = MovementTone::Quiet,
    ) {
        if ('' === trim($sentence)) {
            throw new \InvalidArgumentException('A movement is a sentence; a topic with nothing to say publishes no movement at all.');
        }
    }
}
