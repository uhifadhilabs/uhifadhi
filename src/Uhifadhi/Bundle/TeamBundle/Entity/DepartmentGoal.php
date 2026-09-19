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
use Uhifadhi\Bundle\TeamBundle\Entity\Trait\TimestampableTrait;
use Uhifadhi\Bundle\TeamBundle\Entity\Trait\UuidTrait;
use Uhifadhi\Bundle\TeamBundle\Enum\GoalDirectionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\GoalStateEnum;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentGoalRepository;

/**
 * WHAT A DEPARTMENT SAID IT WOULD DO, BY WHEN, AND WHO ANSWERS FOR IT.
 *
 * A GOAL IS A SENTENCE WITH A NUMBER IN IT. The statement is what a person
 * reads; the target, the unit and the direction are what a page can judge;
 * the KPI reference is which published figure answers it, so nobody has to
 * type the number in every month. A goal whose figure no installed module
 * computes is still a goal — it reads "no figure yet", which is not a miss.
 *
 * THE STATE IS DERIVED AND NEVER STORED. {@see stateFor()} takes one figure
 * and one clock and answers; a stored state is one somebody has to remember
 * to update, and the rail would go on saying "met" a month after the figure
 * moved.
 *
 * SOMEBODY ANSWERS FOR IT. The owning POSITION, never a person: people come
 * and go from a post and the goal stays with the post. A goal nobody owns is
 * allowed — the page says so and asks for an owner — because refusing to
 * record a goal until somebody is named is how goals end up in a
 * spreadsheet instead.
 *
 * THE WINDOW IS EXPLICIT. A period kind would make this class guess what
 * "this quarter" means in an installation whose year starts in July; two
 * instants cannot be misread, and the page that creates a goal is the one
 * that knows which quarter the director meant.
 */
#[ORM\Entity(repositoryClass: DepartmentGoalRepository::class)]
#[ORM\Table(name: 'team_department_goal')]
#[ORM\HasLifecycleCallbacks]
class DepartmentGoal
{
    use TimestampableTrait;
    use UuidTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\ManyToOne(targetEntity: Department::class, inversedBy: 'goals')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Department $department = null;

    /** What a person reads: "Cover 60 % of the ground every month". */
    #[ORM\Column(length: 200)]
    private string $statement = '';

    /**
     * WHICH PUBLISHED FIGURE ANSWERS IT — a topic matrix's column key, such
     * as `patrols.covered`. Nullable, because a department may declare a
     * goal before any module publishes anything to judge it by.
     */
    #[ORM\Column(name: 'kpi_ref', length: 80, nullable: true)]
    private ?string $kpiRef = null;

    #[ORM\Column(type: 'float')]
    private float $target = 0.0;

    /** The target's own unit, as the figure states it: `%`, `d`, `km`. */
    #[ORM\Column(length: 16)]
    private string $unit = '';

    #[ORM\Column(enumType: GoalDirectionEnum::class)]
    private GoalDirectionEnum $direction = GoalDirectionEnum::AtLeast;

    /**
     * THE POST THAT ANSWERS FOR IT, nulled rather than cascaded: a position
     * may be retired while the goal it owned is still being reported on, and
     * the goal then reads "nobody owns this" rather than vanishing.
     */
    #[ORM\ManyToOne(targetEntity: Position::class)]
    #[ORM\JoinColumn(name: 'owner_position_id', nullable: true, onDelete: 'SET NULL')]
    private ?Position $owner = null;

    #[ORM\Column(name: 'opens_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $opensAt = null;

    #[ORM\Column(name: 'closes_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $closesAt = null;

    /**
     * HOW THIS GOAL READS, given one figure and one clock.
     *
     * NULL IS NOT NOUGHT. A figure nobody published leaves the goal in its
     * own state; judging it would turn "no module computes this" into a
     * miss, which is a verdict on a department for something nobody
     * measured.
     */
    public function stateFor(?float $figure, \DateTimeImmutable $now): GoalStateEnum
    {
        if (null === $figure) {
            return GoalStateEnum::NoFigure;
        }

        if ($this->isReached($figure)) {
            return GoalStateEnum::Met;
        }

        // A PERIOD THAT HAS CLOSED SHORT IS A MISS; one still open is a
        // warning, and the difference between them is the only thing
        // anybody can still act on.
        return $this->daysLeft($now) > 0 ? GoalStateEnum::AtRisk : GoalStateEnum::Missed;
    }

    /** Whether the figure is on the right side of the target. */
    public function isReached(float $figure): bool
    {
        return match ($this->direction) {
            GoalDirectionEnum::AtLeast => $figure >= $this->target,
            GoalDirectionEnum::AtMost => $figure <= $this->target,
        };
    }

    /** How long is left to act — nought once the window has closed. */
    public function daysLeft(\DateTimeImmutable $now): int
    {
        if (null === $this->closesAt) {
            return 0;
        }

        return max(0, (int) $now->setTime(0, 0)->diff($this->closesAt->setTime(0, 0))->format('%r%a'));
    }

    /**
     * WHAT THE REMAINING DAYS HAVE TO DELIVER — the distance still to cover,
     * which is what a director acts on. Null when there is nothing to do:
     * the goal is reached, the window has closed, or nobody published a
     * figure to be short of.
     */
    public function paceFor(?float $figure, \DateTimeImmutable $now): ?float
    {
        if (null === $figure || $this->isReached($figure) || 0 === $this->daysLeft($now)) {
            return null;
        }

        return abs($this->target - $figure);
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

    public function getStatement(): string
    {
        return $this->statement;
    }

    public function setStatement(string $statement): static
    {
        $this->statement = $statement;

        return $this;
    }

    public function getKpiRef(): ?string
    {
        return $this->kpiRef;
    }

    public function setKpiRef(?string $kpiRef): static
    {
        $this->kpiRef = '' === $kpiRef ? null : $kpiRef;

        return $this;
    }

    public function getTarget(): float
    {
        return $this->target;
    }

    public function setTarget(float $target): static
    {
        $this->target = $target;

        return $this;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getDirection(): GoalDirectionEnum
    {
        return $this->direction;
    }

    public function setDirection(GoalDirectionEnum $direction): static
    {
        $this->direction = $direction;

        return $this;
    }

    public function getOwner(): ?Position
    {
        return $this->owner;
    }

    public function setOwner(?Position $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getOpensAt(): ?\DateTimeImmutable
    {
        return $this->opensAt;
    }

    public function setOpensAt(\DateTimeImmutable $opensAt): static
    {
        $this->opensAt = $opensAt;

        return $this;
    }

    public function getClosesAt(): ?\DateTimeImmutable
    {
        return $this->closesAt;
    }

    public function setClosesAt(\DateTimeImmutable $closesAt): static
    {
        $this->closesAt = $closesAt;

        return $this;
    }
}
