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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversClass;
use Uhifadhi\Bundle\TeamBundle\Controller\DepartmentController;
use Uhifadhi\Bundle\TeamBundle\Entity\DepartmentGoal;
use Uhifadhi\Bundle\TeamBundle\Enum\GoalDirectionEnum;

/**
 * DECLARING WHAT A DEPARTMENT WILL DO, ON THE DEPARTMENT'S OWN PAGE.
 *
 * A GOAL IS DECLARED WHERE THE DEPARTMENT IS READ, not on a page of its
 * own: the target only means anything beside the figures it is judged by,
 * and a separate screen would be a second place to look for one.
 *
 * IT GRANTS NOBODY ANYTHING. A goal is a commitment and a measurement; no
 * permission, no filing and no access follows from declaring one, which is
 * why the write is the department-management gate and nothing more.
 */
#[CoversClass(DepartmentController::class)]
final class DepartmentGoalsTest extends WebTestCaseWithSchema
{
    public function testADepartmentDeclaresAGoalFromItsOwnPage(): void
    {
        $this->administrator();
        $ecology = $this->department('Ecology');
        $this->em->flush();
        $uuid = (string) $ecology->getUuidString();

        $this->post('/departments/'.$uuid.'/goals', [
            'statement' => 'Cover the ground every month',
            'kpiRef' => 'patrols.covered',
            'target' => '60',
            'unit' => '%',
            'direction' => 'at_least',
            'opensAt' => '2026-09-01',
            'closesAt' => '2026-09-30',
        ], $uuid);

        $goal = $this->em->getRepository(DepartmentGoal::class)->findOneBy([]);
        self::assertInstanceOf(DepartmentGoal::class, $goal);
        self::assertSame('Cover the ground every month', $goal->getStatement());
        self::assertSame('patrols.covered', $goal->getKpiRef());
        self::assertSame(60.0, $goal->getTarget());
        self::assertSame(GoalDirectionEnum::AtLeast, $goal->getDirection());
        self::assertSame($ecology->getId(), $goal->getDepartment()?->getId());
    }

    /** Somebody answers for it, and it is a POST rather than a person. */
    public function testAGoalCanNameThePositionThatAnswersForIt(): void
    {
        $this->administrator();
        $ecology = $this->department('Ecology');
        $lead = $this->position('Lead Ecologist');
        $this->em->flush();
        $uuid = (string) $ecology->getUuidString();

        $this->post('/departments/'.$uuid.'/goals', [
            'statement' => 'Survey every quarter',
            'target' => '4',
            'unit' => '',
            'direction' => 'at_least',
            'opensAt' => '2026-07-01',
            'closesAt' => '2026-09-30',
            'owner' => (string) $lead->getUuidString(),
        ], $uuid);

        $goal = $this->em->getRepository(DepartmentGoal::class)->findOneBy([]);
        self::assertSame('Lead Ecologist', $goal?->getOwner()?->getName());
    }

    /** A goal with no sentence is not a goal, and nothing is written. */
    public function testAGoalWithNoStatementIsRefused(): void
    {
        $this->administrator();
        $ecology = $this->department('Ecology');
        $this->em->flush();
        $uuid = (string) $ecology->getUuidString();

        $this->post('/departments/'.$uuid.'/goals', [
            'statement' => '  ',
            'target' => '60',
            'unit' => '%',
            'direction' => 'at_least',
            'opensAt' => '2026-09-01',
            'closesAt' => '2026-09-30',
        ], $uuid);

        self::assertNull($this->em->getRepository(DepartmentGoal::class)->findOneBy([]));
    }

    /** A window that closes before it opens is a mis-post, and it is refused. */
    public function testAWindowThatClosesBeforeItOpensIsRefused(): void
    {
        $this->administrator();
        $ecology = $this->department('Ecology');
        $this->em->flush();
        $uuid = (string) $ecology->getUuidString();

        $this->post('/departments/'.$uuid.'/goals', [
            'statement' => 'Cover the ground',
            'target' => '60',
            'unit' => '%',
            'direction' => 'at_least',
            'opensAt' => '2026-09-30',
            'closesAt' => '2026-09-01',
        ], $uuid);

        self::assertNull($this->em->getRepository(DepartmentGoal::class)->findOneBy([]));
    }

    /** Withdrawing one takes it away entirely: an undeclared goal leaves no trace. */
    public function testAGoalIsWithdrawn(): void
    {
        $this->administrator();
        $ecology = $this->department('Ecology');
        $this->em->flush();
        $uuid = (string) $ecology->getUuidString();

        $this->post('/departments/'.$uuid.'/goals', [
            'statement' => 'Cover the ground',
            'target' => '60',
            'unit' => '%',
            'direction' => 'at_least',
            'opensAt' => '2026-09-01',
            'closesAt' => '2026-09-30',
        ], $uuid);

        $goal = $this->em->getRepository(DepartmentGoal::class)->findOneBy([]);
        self::assertInstanceOf(DepartmentGoal::class, $goal);

        $this->post('/departments/'.$uuid.'/goals/'.$goal->getUuidString().'/withdraw', [], $uuid);

        $this->em->clear();
        self::assertNull($this->em->getRepository(DepartmentGoal::class)->findOneBy([]));
    }

    /** The department's page states what it declared, and how it reads. */
    public function testTheDepartmentsPageListsItsGoalsAndTheirState(): void
    {
        $this->administrator();
        $ecology = $this->department('Ecology');
        $this->em->flush();
        $uuid = (string) $ecology->getUuidString();

        $this->post('/departments/'.$uuid.'/goals', [
            'statement' => 'Cover the ground every month',
            'kpiRef' => 'patrols.covered',
            'target' => '60',
            'unit' => '%',
            'direction' => 'at_least',
            'opensAt' => '2026-09-01',
            'closesAt' => '2036-09-30',
        ], $uuid);

        $page = $this->client->request('GET', '/departments/'.$uuid);

        self::assertStringContainsString('Cover the ground every month', $page->text());
        // NO MODULE PUBLISHES ITS FIGURE HERE, and that is its own state —
        // never a miss, because nobody measured it.
        self::assertStringContainsString('no figure yet', $page->text());
    }

    /**
     * @param array<string, string> $fields
     */
    private function post(string $url, array $fields, string $uuid): void
    {
        // THE TOKEN IS THE REGISTER'S: one CSRF id serves every department
        // write, and the register always renders a form that carries it.
        $token = $this->tokenFrom('/departments');

        $this->client->request('POST', $url, [...$fields, '_token' => $token]);
    }
}
