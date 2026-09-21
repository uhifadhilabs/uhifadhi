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
 * ONE THING SOMEBODY HAS TO DECIDE, and the department it is about.
 *
 * A DECISION IS NOT A FIGURE AND NOT A MOVEMENT. "13 vacant" is a
 * figure; "coverage fell while distance rose" is a movement; "this post
 * has stood empty 74 days — fill it or close it" is a thing a person
 * does something about. A briefing that listed figures would be the
 * page before it, read again.
 *
 * THE WORDS ARE THE TOPIC'S AND SO IS THE ASK. Only the topic knows
 * that a post past the threshold wants filling rather than measuring,
 * and the host must not be guessing an instruction from a number.
 *
 * IT NAMES A DEPARTMENT, because a decision nobody owns is the kind
 * that waits. Where a topic genuinely cannot name one — a figure about
 * the organization itself — the mark and the name are empty and the
 * row simply reads as the organization's.
 */
final readonly class TopicDecision
{
    public function __construct(
        /** What is wrong, in the topic's own words. */
        public string $what,
        /** What somebody is being asked to do about it. */
        public string $ask,
        /** Whose it is. Empty where the decision belongs to the organization. */
        public string $departmentName = '',
        /** The two letters every surface draws a department by. */
        public string $departmentMark = '',
        /** Where the decision is acted on, where there is such a place. */
        public ?string $url = null,
        public MovementTone $tone = MovementTone::Attention,
    ) {
        if ('' === trim($what) || '' === trim($ask)) {
            throw new \InvalidArgumentException('A decision states what is wrong AND what is being asked; half of it is a figure with an opinion.');
        }
    }
}
