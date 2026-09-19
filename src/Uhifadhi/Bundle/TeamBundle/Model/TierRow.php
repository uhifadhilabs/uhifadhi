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

use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;

/**
 * ONE OF THE THREE TIERS, as the Roles tab reads it.
 *
 * A TIER IS NOT A ROLE AND GRANTS NOTHING BY ITSELF — except for the two that
 * grant everything. Administering the team is an ordinary permission, so "who
 * runs this place" is not answerable from this table alone, and the row says
 * what the tier means rather than implying it is the answer.
 */
final readonly class TierRow
{
    public function __construct(
        public TeamRoleEnum $tier,
        public int $people,
        public string $who,
        public string $meaning,
    ) {
    }
}
