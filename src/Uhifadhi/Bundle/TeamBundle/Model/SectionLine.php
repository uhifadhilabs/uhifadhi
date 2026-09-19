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
 * ONE LABEL-AND-VALUE ROW inside a bounded card — the shell's `.rln`.
 *
 * A card built of these shows its latest N and hands the rest to the register
 * through the bound under it; it never grows to the data and never scrolls
 * inside itself.
 */
final readonly class SectionLine
{
    public function __construct(
        public string $label,
        public ?string $uuid = null,
        public string $note = '',
        public ?string $tone = null,
    ) {
    }
}
