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

namespace Uhifadhi\Contracts\Access;

/**
 * HOW A BUNDLE OR A MODULE PUTS ITS CONCERNS ON THE POSITIONS PAGE.
 *
 * WHOEVER ENFORCES A CONCERN DECLARES IT. The area bundle owns the ground,
 * the roster owns watches, the team owns personal details; each says so here
 * and nowhere else. The alternative - one enum in the middle of the product -
 * ends as a list of everything anybody checks, maintained by hand, outliving
 * the code it describes.
 *
 * DECLARING GRANTS NOBODY ANYTHING. The matrix gains a group of rows; who
 * ticks them is the organization's business. Installing a module must never
 * hand an existing person a new power.
 *
 * A reusable bundle is not autoconfigured, so the tag goes on by hand:
 *
 *     $services->set('area.access.concerns', AreaConcerns::class)
 *         ->tag(ConcernSourceInterface::TAG);
 */
interface ConcernSourceInterface
{
    /** The tag that puts a declaration in the installation's catalogue of concerns. */
    public const string TAG = 'uhifadhi.access.concerns';

    /**
     * Who is declaring these, in the product's words - "Areas", "Team",
     * "Roster". It is the caption on the group in the grants matrix, so that
     * an administrator reading a row can see which package gave it to them,
     * and which package removing would take it away.
     */
    public function declaredBy(): string;

    /**
     * @return iterable<ConcernInterface>
     */
    public function concerns(): iterable;
}
