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

namespace Uhifadhi\Bundle\TeamBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentPeriodFigureRepository;

/**
 * WHAT ONE FIGURE WAS, FOR ONE DEPARTMENT, IN ONE PERIOD THAT HAS CLOSED.
 *
 * A CLOSED PERIOD CANNOT BE RECOMPUTED. The tables hold what is true now:
 * positions are filled and emptied, people move between posts, a module's
 * records are edited. "Positions filled in July" is not a query anybody can
 * write in September — so the host writes it down while July is still true,
 * and reads its own record afterwards.
 *
 * EVERY MOVEMENT ON THE PERFORMANCE PAGE READS THIS. The delta beside a
 * figure, the six-period sparkline under it, the rank that moved, and
 * "the same period last year": all of them are comparisons against a period
 * that closed, and none of them exists without this table.
 *
 * ONE ROW PER DEPARTMENT, PERIOD AND FIGURE — unique, because a snapshot run
 * by hand after a scheduled one must correct the history rather than double
 * it.
 *
 * THE KEY IS THE FIGURE'S PUBLISHED NAME, `staffing.filled` or
 * `patrols.covered`: the host's own figures and every module's live in one
 * table under one naming rule, so a module that arrives later needs no
 * schema change to have a history.
 */
#[ORM\Entity(repositoryClass: DepartmentPeriodFigureRepository::class)]
#[ORM\Table(name: 'team_department_period_figure')]
#[ORM\UniqueConstraint(name: 'uniq_department_period_figure', columns: ['department_id', 'period_key', 'figure_key'])]
class DepartmentPeriodFigure
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\ManyToOne(targetEntity: Department::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Department $department = null;

    /**
     * THE PERIOD, AS A SORTABLE STRING: `2026-08` for a month, `2026-Q3`
     * for a quarter, `2026` for a year. A pair of instants would make
     * "which period is this" a range query on every read of every cell.
     */
    #[ORM\Column(name: 'period_key', length: 16)]
    private string $periodKey = '';

    #[ORM\Column(name: 'figure_key', length: 80)]
    private string $figureKey = '';

    /**
     * NULLABLE, AND THAT IS THE POINT. A figure nobody published in a
     * period is written as absent rather than left out, so a sparkline can
     * draw the hole and a delta can say there is nothing to compare with —
     * both of which are different from nought.
     */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $value = null;

    /** When the snapshot ran, which is not the period it is about. */
    #[ORM\Column(name: 'written_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $writtenAt;

    public function __construct()
    {
        $this->writtenAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDepartment(): ?Department
    {
        return $this->department;
    }

    public function setDepartment(Department $department): static
    {
        $this->department = $department;

        return $this;
    }

    public function getPeriodKey(): string
    {
        return $this->periodKey;
    }

    public function setPeriodKey(string $periodKey): static
    {
        $this->periodKey = $periodKey;

        return $this;
    }

    public function getFigureKey(): string
    {
        return $this->figureKey;
    }

    public function setFigureKey(string $figureKey): static
    {
        $this->figureKey = $figureKey;

        return $this;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function setValue(?float $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getWrittenAt(): \DateTimeImmutable
    {
        return $this->writtenAt;
    }

    public function setWrittenAt(\DateTimeImmutable $writtenAt): static
    {
        $this->writtenAt = $writtenAt;

        return $this;
    }
}
