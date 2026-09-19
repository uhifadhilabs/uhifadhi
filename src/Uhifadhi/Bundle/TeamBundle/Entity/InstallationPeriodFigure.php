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
use Uhifadhi\Bundle\TeamBundle\Repository\InstallationPeriodFigureRepository;

/**
 * WHAT ONE FIGURE WAS FOR THE WHOLE INSTALLATION, IN ONE PERIOD THAT HAS
 * CLOSED.
 *
 * THE SAME MECHANISM AS {@see DepartmentPeriodFigure}, ONE SCOPE UP. A closed
 * period cannot be recomputed — the tables hold what is true now — so the host
 * writes the figure down while the period is still true and reads its own
 * record afterwards. Every movement the Team overview draws is a comparison
 * against one of these rows, and none of them exists without this table.
 *
 * WHY IT IS NOT A ROW IN THE DEPARTMENT TABLE. Three of the figures here have
 * no department to belong to: an account with no position is in no
 * department, a posting is the area's, and the tiers are the installation's.
 * Summing the department rows would quietly drop the loose bucket the
 * overview draws as its own line — a delta that disagreed with the figure it
 * sits under is worse than no delta at all. So the scope is a different
 * table rather than a nullable column, which also keeps "a department's
 * figure" a question with one honest answer.
 *
 * ONE ROW PER PERIOD AND FIGURE — unique, because a snapshot run by hand
 * after a scheduled one must correct the history rather than double it.
 *
 * THE KEY IS THE FIGURE'S PUBLISHED NAME, `team.people` or `team.postings`,
 * under the same naming rule the department table uses.
 */
#[ORM\Entity(repositoryClass: InstallationPeriodFigureRepository::class)]
#[ORM\Table(name: 'team_period_figure')]
#[ORM\UniqueConstraint(name: 'uniq_installation_period_figure', columns: ['period_key', 'figure_key'])]
class InstallationPeriodFigure
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\Column(name: 'period_key', length: 16)]
    private string $periodKey = '';

    #[ORM\Column(name: 'figure_key', length: 80)]
    private string $figureKey = '';

    /**
     * NULL IS "NOBODY COULD SAY", and it is not nought. A period in which a
     * figure could not be computed must not read as a period in which it was
     * zero — the first is a hole in the history and the second is a fact.
     */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $value = null;

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
