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

namespace Uhifadhi\Bundle\TeamBundle\Performance;

/**
 * ONE STATE CHIP, with the tone this platform gives it.
 *
 * The publisher's word and the platform's tone: a topic says "met" and
 * that it reads well, and what "reads well" looks like is settled once,
 * here, so two topics cannot ship two greens.
 */
final readonly class CellChip
{
    public function __construct(
        public string $label,
        /** 'good', 'bad', or '' where the state makes no claim. */
        public string $tone,
        public string $title = '',
    ) {
    }
}
