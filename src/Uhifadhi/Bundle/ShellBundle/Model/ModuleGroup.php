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
 * A CATEGORY OF TILES, and possibly the department that leads it.
 *
 * WHICH cards land in which group, in which order, and which department is
 * said to lead one, is a reading of the catalogue for a particular viewer on a
 * particular area — three things the shell does not have and must not acquire.
 * Whoever has them composes this; the shell draws it.
 *
 * `department` is a LENS, never a gate: it says who leads a body of work, and
 * the marker renders only when somebody actually does. A marker on every group
 * would say nothing at all.
 */
final class ModuleGroup
{
    /**
     * @param string           $label      the category heading
     * @param list<ModuleCard> $cards      its tiles, in the order they should render
     * @param string|null      $department the leading department's name, if one leads
     */
    public function __construct(
        public string $label,
        public array $cards = [],
        public ?string $department = null,
    ) {
    }
}
