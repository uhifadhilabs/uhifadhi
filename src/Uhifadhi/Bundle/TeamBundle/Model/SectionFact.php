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

namespace Uhifadhi\Bundle\TeamBundle\Model;

/**
 * ONE FACT IN A SECTION'S IDENTITY BAND — a label, the figure, and the line
 * under it that says what the figure is made of.
 *
 * It is a fragment, never a sentence: the band states facts, and a clause with
 * a verb in it belongs in a comment or in the docs.
 */
final readonly class SectionFact
{
    public function __construct(
        public string $label,
        public string $value,
        public ?string $qualifier = null,
    ) {
    }
}
