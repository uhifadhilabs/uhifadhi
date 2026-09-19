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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Integration\Performance;

use PHPUnit\Framework\Attributes\CoversClass;
use Uhifadhi\Bundle\RegistryBundle\Entity\Module;
use Uhifadhi\Bundle\RegistryBundle\Enum\ModuleCategory;
use Uhifadhi\Bundle\RegistryBundle\Enum\ModuleStatus;
use Uhifadhi\Bundle\TeamBundle\Service\PerformanceTopics;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\IntegrationTestCase;
use Uhifadhi\Contracts\Kpi\FigurePeriod;
use Uhifadhi\Contracts\Performance\PerformanceScope;

/**
 * WHICH TOPICS A PERFORMANCE PAGE HAS, AND IN WHICH ORDER.
 *
 * THE HOST'S FIRST, THEN THE MODULES' IN THE ORDER SOMEBODY ARRANGED. The
 * host's topics are figures every department has whatever it attaches, so
 * they lead; a module's follows, in the organisation's own module order.
 * Alphabetical would be the dictionary's opinion about an organisation's
 * priorities, and tag order is the order the container happened to build
 * services in.
 *
 * A MODULE NOBODY RUNS PUBLISHES NOTHING HERE. A topic from a module this
 * installation does not have — or, in an area's scope, one that area does
 * not run — is not an empty section: it is not a section.
 */
#[CoversClass(PerformanceTopics::class)]
final class PerformanceTopicsTest extends IntegrationTestCase
{
    public function testTheHostsTopicsLeadAndTheModulesFollowInTheirOwnOrder(): void
    {
        $this->aCatalogue(['incidents' => 0, 'patrols' => 1]);

        self::assertSame(
            ['staffing', 'goals', 'incidents', 'patrols'],
            $this->keysOf($this->topics()->forScope(PerformanceScope::organisation(), self::period())),
        );
    }

    /** Rearranged, the page reads the new order. */
    public function testTheOrderFollowsTheOrganisationsOwnArrangement(): void
    {
        $this->aCatalogue(['patrols' => 0, 'incidents' => 1]);

        self::assertSame(
            ['staffing', 'goals', 'patrols', 'incidents'],
            $this->keysOf($this->topics()->forScope(PerformanceScope::organisation(), self::period())),
        );
    }

    /** A module this installation does not carry has no topic. */
    public function testAModuleTheInstallationDoesNotHaveHasNoTopic(): void
    {
        $this->aCatalogue(['patrols' => 0]);

        self::assertSame(
            ['staffing', 'goals', 'patrols'],
            $this->keysOf($this->topics()->forScope(PerformanceScope::organisation(), self::period())),
        );
    }

    /** One topic by its own key, for the topic's own page. */
    public function testATopicIsFoundByItsKey(): void
    {
        $this->aCatalogue(['patrols' => 0]);

        $topic = $this->topics()->byKey('patrols', PerformanceScope::organisation(), self::period());

        self::assertNotNull($topic);
        self::assertSame('Patrols', $topic->title());
        self::assertNull($this->topics()->byKey('nothing', PerformanceScope::organisation(), self::period()));
    }

    /**
     * FIVE FIGURES, ALWAYS. The contract says a topic publishes five and
     * the collector is where that is checked, once, rather than in each of
     * five renderers.
     */
    public function testEveryTopicPublishesFiveHeadlineFigures(): void
    {
        $this->aCatalogue(['patrols' => 0]);

        foreach ($this->topics()->forScope(PerformanceScope::organisation(), self::period()) as $topic) {
            self::assertCount(5, $topic->kpis(PerformanceScope::organisation(), self::period()), $topic->key());
        }
    }

    /**
     * THE STAFFING TOPIC COUNTS WHAT HAS STOOD EMPTY TOO LONG, from the day
     * each post fell vacant — and says how many it could not count,
     * because a post that was already empty before the day was recorded
     * may be the oldest vacancy there is.
     */
    public function testTheStaffingTopicCountsThePostsThatHaveStoodEmptyTooLong(): void
    {
        $department = new \Uhifadhi\Bundle\TeamBundle\Entity\Department()->setName('Protection Service');
        $this->em->persist($department);

        $long = new \Uhifadhi\Bundle\TeamBundle\Entity\Position()->setName('Ranger')->setDepartment($department);
        $long->setPermissionValues([], []);
        $long->setVacantSince(new \DateTimeImmutable('-96 days'));
        $fresh = new \Uhifadhi\Bundle\TeamBundle\Entity\Position()->setName('Warden')->setDepartment($department);
        $fresh->setPermissionValues([], []);
        $fresh->setVacantSince(new \DateTimeImmutable('-3 days'));
        $undated = new \Uhifadhi\Bundle\TeamBundle\Entity\Position()->setName('Scout')->setDepartment($department);
        $undated->setPermissionValues([], []);
        $this->em->persist($long);
        $this->em->persist($fresh);
        $this->em->persist($undated);
        $this->em->flush();

        $staffing = $this->topics()->byKey('staffing', PerformanceScope::organisation(), self::period());
        self::assertNotNull($staffing);

        $figures = $staffing->kpis(PerformanceScope::organisation(), self::period());
        $overThreshold = $figures[4];

        self::assertSame('staffing.over_threshold', $overThreshold->key);
        self::assertSame(1.0, $overThreshold->value);
        self::assertStringContainsString('1 more stood empty', $overThreshold->caption);
    }

    /**
     * A GOAL'S STATE IS DERIVED AT THE MOMENT OF ASKING, from the figure
     * it names against the target it set — so nothing is stored that a
     * revised figure could contradict.
     *
     * AND "NO FIGURE YET" IS ITS OWN STATE. A goal whose module has
     * published nothing is not a miss: counting a silence as a failure
     * would mark a department down for somebody else's module, and the
     * page states the two facts separately.
     */
    public function testAGoalWithNoFigureIsNeitherMetNorMissed(): void
    {
        $department = new \Uhifadhi\Bundle\TeamBundle\Entity\Department()->setName('Community Development');
        $this->em->persist($department);

        // A target nothing reports against: no module is attached, so the
        // KPI this goal names is published by nobody.
        $goal = new \Uhifadhi\Bundle\TeamBundle\Entity\DepartmentGoal()
            ->setDepartment($department)
            ->setStatement('Claims settled within 10 days')
            ->setKpiRef('incidents.days_to_settle')
            ->setTarget(10.0)
            ->setUnit('days')
            // A goal is a promise over a WINDOW; there is no such thing as
            // one without dates, and the schema says so.
            ->setOpensAt(new \DateTimeImmutable('-30 days'))
            ->setClosesAt(new \DateTimeImmutable('+30 days'));
        $this->em->persist($goal);
        $this->em->flush();

        $goals = $this->topics()->byKey('goals', PerformanceScope::organisation(), self::period());
        self::assertNotNull($goals);

        $figures = [];
        foreach ($goals->kpis(PerformanceScope::organisation(), self::period()) as $kpi) {
            $figures[$kpi->key] = $kpi->value;
        }

        self::assertSame(1.0, $figures['goals.no_figure']);
        self::assertSame(0.0, $figures['goals.met']);
        self::assertSame(0.0, $figures['goals.missed']);
        self::assertSame(1.0, $figures['goals.declared']);
    }

    /**
     * EVERY DEPARTMENT IS A ROW, INCLUDING THE ONES THAT DECLARED NONE.
     * Any department can declare a goal, so a quiet one is a row saying
     * nought rather than a row that is missing — which is the whole
     * reason this matrix does not fold.
     */
    public function testADepartmentThatDeclaredNoGoalIsStillARow(): void
    {
        $quiet = new \Uhifadhi\Bundle\TeamBundle\Entity\Department()->setName('Finance');
        $this->em->persist($quiet);
        $this->em->flush();

        $goals = $this->topics()->byKey('goals', PerformanceScope::organisation(), self::period());
        self::assertNotNull($goals);

        $matrix = $goals->matrix(PerformanceScope::organisation(), self::period());
        $names = array_map(static fn (object $row): string => $row->departmentName, $matrix->rows);

        self::assertContains('Finance', $names);
        self::assertSame(0.0, $matrix->rows[0]->cells['goals.declared']->value);
    }

    /**
     * PACE IS A CHIP PER GOAL, NOT AN AVERAGE OF THEM. Four goals in four
     * states is four facts; a single number would answer a question
     * nobody asked and a reader could not get back to the goals from it.
     */
    public function testThePaceColumnCountsStatesRatherThanMeasuringThem(): void
    {
        $department = new \Uhifadhi\Bundle\TeamBundle\Entity\Department()->setName('Ecology');
        $this->em->persist($department);

        foreach ([['Met one', 1.0], ['Met two', 1.0]] as [$statement, $target]) {
            $goal = new \Uhifadhi\Bundle\TeamBundle\Entity\DepartmentGoal()
                ->setDepartment($department)
                ->setStatement($statement)
                ->setTarget($target)
                ->setUnit('')
                ->setOpensAt(new \DateTimeImmutable('-30 days'))
                ->setClosesAt(new \DateTimeImmutable('+30 days'));
            $this->em->persist($goal);
        }
        $this->em->flush();

        $goals = $this->topics()->byKey('goals', PerformanceScope::organisation(), self::period());
        self::assertNotNull($goals);

        $pace = $goals->matrix(PerformanceScope::organisation(), self::period())->rows[0]->cells['goals.pace'];

        self::assertTrue($pace->isMarked(), 'the pace column counts states');
        self::assertNull($pace->value, 'and states no figure, because there is none to state');
        self::assertCount(2, $pace->marks, 'one chip a goal');
    }

    /** @param array<string, int> $modules slug to the position it is arranged at */
    private function aCatalogue(array $modules): void
    {
        foreach ($modules as $slug => $position) {
            $this->em->persist(new Module()
                ->setSlug($slug)
                ->setName(ucfirst($slug))
                ->setCategory(ModuleCategory::Pressure)
                ->setStatus(ModuleStatus::Live)
                ->setDataSource('the stand-in')
                ->setPosition($position));
        }
        $this->em->flush();
    }

    /**
     * @param list<\Uhifadhi\Contracts\Performance\PerformanceTopicProviderInterface> $topics
     *
     * @return list<string>
     */
    private function keysOf(array $topics): array
    {
        return array_map(static fn ($topic): string => $topic->key(), $topics);
    }

    private function topics(): PerformanceTopics
    {
        /** @var PerformanceTopics $topics */
        $topics = static::getContainer()->get('test_public.'.PerformanceTopics::class);

        return $topics;
    }

    private static function period(): FigurePeriod
    {
        return FigurePeriod::month(new \DateTimeImmutable('2026-09-19'));
    }
}
