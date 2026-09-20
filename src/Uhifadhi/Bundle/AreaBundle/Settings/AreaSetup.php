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

namespace Uhifadhi\Bundle\AreaBundle\Settings;

use Uhifadhi\Contracts\Settings\AreaRun;
use Uhifadhi\Contracts\Settings\ModuleMatrixSourceInterface;

/**
 * WHICH AREAS ARE REGISTERED AND EMPTY — read once, and read by both of the
 * screens that care.
 *
 * THIS IS THE ONE FAILURE MODE AN INSTALLATION CANNOT SEE FROM ANYWHERE ELSE.
 * A registered area with every module off is absent from every figure and
 * every queue in the product — not because anything is broken, but because
 * there is nothing installed in it to contribute one. The whole organisation
 * then reads as smaller and quieter than it is, and nothing says why. So the
 * area bundle raises it twice, where each reading is looked for: as a health
 * CHECK on the installation screen, and as a DECISION in the section's queue.
 *
 * TWO READINGS OF ONE FACT, NOT TWO FACTS, and this class is what makes that
 * literally true: both sources hold the same instance, so a check that passes
 * while the queue still complains is not a state the pair can be in — and the
 * matrix is built once rather than once per reading.
 */
final class AreaSetup
{
    /** @var list<AreaRun>|null */
    private ?array $waiting = null;

    private ?int $areas = null;

    public function __construct(private readonly ModuleMatrixSourceInterface $matrix)
    {
    }

    /**
     * The areas that run nothing at all, in the register's own order.
     *
     * @return list<AreaRun>
     */
    public function waiting(): array
    {
        $this->read();
        \assert(null !== $this->waiting);

        return $this->waiting;
    }

    /** How many areas there are — what the finding is out of. */
    public function areas(): int
    {
        $this->read();

        return (int) $this->areas;
    }

    /** The areas that are waiting, named, for a row that has to say which. */
    public function namesWaiting(): string
    {
        return implode(' · ', array_map(static fn (AreaRun $row): string => $row->name, $this->waiting()));
    }

    private function read(): void
    {
        if (null !== $this->waiting) {
            return;
        }

        $matrix = $this->matrix->moduleMatrix();
        $this->areas = \count($matrix->rows);
        $this->waiting = array_values(array_filter(
            $matrix->rows,
            static fn (AreaRun $row): bool => !$row->isLive(),
        ));
    }
}
