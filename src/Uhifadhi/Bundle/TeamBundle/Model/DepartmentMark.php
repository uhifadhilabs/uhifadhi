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
 * THE TWO LETTERS A DEPARTMENT IS DRAWN BY, from the name nobody typed
 * them for.
 *
 * ONE RULE, BECAUSE A DEPARTMENT IS RECOGNISED BY IT. The mark is on
 * the register's cards, on every matrix row, on a decision in the
 * briefing and in the sidebar; if two of those derived it differently
 * the same department would wear two marks on one screen, which is
 * worse than wearing none.
 *
 * It was written out three times before this class existed, which is
 * how a rule becomes two rules.
 */
final readonly class DepartmentMark
{
    public static function of(string $name): string
    {
        $words = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));
        if ([] === $words) {
            return '—';
        }

        return mb_strtoupper(1 === \count($words)
            ? mb_substr($words[0], 0, 2)
            : mb_substr($words[0], 0, 1).mb_substr((string) end($words), 0, 1));
    }
}
