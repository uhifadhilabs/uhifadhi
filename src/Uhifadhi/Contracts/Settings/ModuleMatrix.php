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

namespace Uhifadhi\Contracts\Settings;

/**
 * WHAT RUNS WHERE — every area of the installation against every module it
 * could run.
 *
 * ONE READING, DRAWN TWICE. The Installation tab asks it as "what runs where"
 * and the Modules tab asks it as "the catalogue"; they are the same table
 * because they are the same fact, and two queries for it would be two answers
 * with no way to say which was right.
 *
 * IT IS ASSEMBLED BY WHOEVER HAS BOTH HALVES — the areas and the ledger of
 * what is switched on in each — and that is one bundle, which is why this
 * arrives whole rather than as a collected list.
 */
final readonly class ModuleMatrix
{
    /**
     * @param list<ModuleColumn> $columns the modules, in the order they are drawn
     * @param list<AreaRun>      $rows    the areas, in the order they are drawn
     */
    public function __construct(
        public array $columns = [],
        public array $rows = [],
    ) {
    }

    /** How many areas run at least one module. */
    public function liveAreas(): int
    {
        return \count(array_filter($this->rows, static fn (AreaRun $row): bool => $row->isLive()));
    }

    /** How many are registered and empty — the number the setup decision is about. */
    public function awaitingSetup(): int
    {
        return \count($this->rows) - $this->liveAreas();
    }

    public function isEmpty(): bool
    {
        return [] === $this->rows && [] === $this->columns;
    }
}
