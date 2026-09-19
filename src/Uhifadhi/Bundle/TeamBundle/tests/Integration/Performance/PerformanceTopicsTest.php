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
            ['staffing', 'incidents', 'patrols'],
            $this->keysOf($this->topics()->forScope(PerformanceScope::organisation(), self::period())),
        );
    }

    /** Rearranged, the page reads the new order. */
    public function testTheOrderFollowsTheOrganisationsOwnArrangement(): void
    {
        $this->aCatalogue(['patrols' => 0, 'incidents' => 1]);

        self::assertSame(
            ['staffing', 'patrols', 'incidents'],
            $this->keysOf($this->topics()->forScope(PerformanceScope::organisation(), self::period())),
        );
    }

    /** A module this installation does not carry has no topic. */
    public function testAModuleTheInstallationDoesNotHaveHasNoTopic(): void
    {
        $this->aCatalogue(['patrols' => 0]);

        self::assertSame(
            ['staffing', 'patrols'],
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
