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
 * ONE DECLARER'S CONCERNS, BOUNDED — the fold a reader opens.
 *
 * Whoever enforces a concern declares it, so a reader has to be able to see
 * where the team's concerns end and a module's begin, and which package
 * removing would take a whole group away.
 */
final readonly class GrantGroup
{
    /**
     * @param list<GrantRow> $rows
     */
    public function __construct(
        public string $declarer,
        public ?string $module,
        public array $rows,
    ) {
    }

    /** Declared by a core bundle rather than by an installed module. */
    public function isCore(): bool
    {
        return null === $this->module;
    }

    public function granted(): int
    {
        return \count(array_filter($this->rows, static fn (GrantRow $row): bool => $row->isGranted()));
    }

    public function total(): int
    {
        return \count($this->rows);
    }

    /** @return list<GrantRow> the concerns this position actually holds something on */
    public function grantedRows(): array
    {
        return array_values(array_filter($this->rows, static fn (GrantRow $row): bool => $row->isGranted()));
    }
}
