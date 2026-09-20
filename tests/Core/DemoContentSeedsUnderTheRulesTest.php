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

namespace Uhifadhi\Core\Tests\Core;

use PHPUnit\Framework\Attributes\CoversNothing;
use Uhifadhi\Bundle\AreaBundle\Service\PostingService;
use Uhifadhi\Contracts\Devkit\ContentProviderInterface;

/**
 * THE SHIPPED DEMO CONTENT OBEYS THE PRODUCT'S OWN RULES.
 *
 * A seeder is the first user of every rule the services hold, and it is the
 * one user nobody watches: it runs in a fresh dev install, in CI, and in
 * every module's fleet leg. When "one posting a person" was ruled, the
 * core's own demo was posting the same ranger at two gates — and the first
 * thing that knew was somebody else's pipeline, five tests deep in another
 * repository.
 *
 * SEEDED THROUGH THE REAL SERVICES, never raw rows. A test that wrote
 * postings with the entity manager would pass while the shipped seeder broke
 * the rule, which is exactly the failure this exists to catch: it is the
 * SERVICES that hold the invariants, so the demo has to arrive through them.
 */
#[CoversNothing]
final class DemoContentSeedsUnderTheRulesTest extends MigrationsTestCase
{
    /**
     * ONE POSTING A PERSON, across every area the demo ships.
     *
     * `post()` refuses the second one, so a demo that broke the rule would
     * fail here as an exception rather than as an assertion — and either way
     * the message names the person and the post they already stand at.
     */
    public function testEveryPersonInTheDemoStandsAtOnePostAtMost(): void
    {
        $this->seedTheDemoOrganisation();

        $standing = array_map(
            static fn (mixed $id): string => (string) (\is_scalar($id) ? $id : ''),
            $this->connection->fetchFirstColumn('SELECT person_id FROM posting WHERE ended_at IS NULL ORDER BY id'),
        );

        self::assertNotSame([], $standing, 'the demo posts somebody, or this proves nothing');
        self::assertSame(
            array_values(array_unique($standing)),
            $standing,
            'Somebody in the shipped demo stands at two posts, which the product refuses.',
        );
    }

    /**
     * AND EVERY STAFFED POST HAS EXACTLY ONE LEADER — the other invariant the
     * posting service holds, checked over the same seeding for the same
     * reason.
     */
    public function testEveryStaffedPostInTheDemoHasOneLeader(): void
    {
        $this->seedTheDemoOrganisation();

        $rows = $this->connection->fetchAllAssociative(
            'SELECT station_id, COUNT(*) FILTER (WHERE leader) AS leaders'
            .' FROM posting WHERE ended_at IS NULL GROUP BY station_id',
        );

        self::assertNotSame([], $rows);
        foreach ($rows as $row) {
            $leaders = $row['leaders'];
            self::assertIsNumeric($leaders);
            self::assertSame(1, (int) $leaders, 'a staffed post has exactly one leader');
        }
    }

    /**
     * A POST PAST THE END OF THE ROSTER STANDS EMPTY rather than borrowing
     * somebody from an earlier gate. That is the honest reading of an
     * installation with more posts than people, and it is the state the
     * screens are built to draw.
     */
    public function testPostsPastTheEndOfTheRosterStandEmpty(): void
    {
        $this->seedTheDemoOrganisation();

        $people = $this->rowCount('SELECT COUNT(*) FROM team_user');
        $posted = $this->rowCount('SELECT COUNT(*) FROM posting WHERE ended_at IS NULL');

        self::assertGreaterThan(0, $people);
        self::assertLessThanOrEqual($people, $posted, 'the demo never posts more people than it has');
    }

    /** One count, as an int — the driver answers a string and phpstan is right to say so. */
    private function rowCount(string $sql): int
    {
        $count = $this->connection->fetchOne($sql);
        self::assertIsNumeric($count);

        return (int) $count;
    }

    /** The shipped demo, through the providers an installation's devkit would run. */
    private function seedTheDemoOrganisation(): void
    {
        // An installation's own first act: migrate, then seed. Seeding into a
        // database with no schema would fail for a reason that has nothing to
        // do with the rules being checked.
        $this->console('doctrine:migrations:migrate', ['--no-interaction' => true, 'version' => 'latest']);

        foreach ([
            'test_public.team.devkit.content',
            'test_public.area.devkit.areas',
            'test_public.area.devkit.zones',
            'test_public.area.devkit.stations',
        ] as $id) {
            $provider = static::getContainer()->get($id);
            self::assertInstanceOf(ContentProviderInterface::class, $provider);
            $provider->load();
        }

        // And the service that holds the rule is the one that wrote them.
        self::assertInstanceOf(PostingService::class, static::getContainer()->get('test_public.area.postings'));
    }
}
