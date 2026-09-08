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

namespace Uhifadhi\Bundle\ShellBundle\Model;

/**
 * A LABELLED GROUP OF SIDEBAR ROWS — the org level a set of rows belongs to.
 *
 * `position` is a declared order with registration as the tie-break. A contract
 * field nothing reads is a lie in the contract, so the shell reads it: a
 * contributing bundle that wants to sit under the host's own sections says so
 * with a number rather than by hoping about container compilation order.
 */
final class NavSection
{
    /**
     * @param string        $label    the section heading
     * @param list<NavItem> $items    its rows, in the order they should render
     * @param int           $position lower sorts first; ties keep registration order
     */
    public function __construct(
        public string $label,
        public array $items = [],
        public int $position = 0,
    ) {
    }
}
