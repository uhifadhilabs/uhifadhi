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

namespace Uhifadhi\Bundle\AreaBundle\Devkit;

use Doctrine\ORM\EntityManagerInterface;
use Uhifadhi\Bundle\AreaBundle\Entity\Station;
use Uhifadhi\Bundle\AreaBundle\Enum\PostingSource;
use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\StationRepository;
use Uhifadhi\Bundle\AreaBundle\Service\PostingService;
use Uhifadhi\Bundle\AreaBundle\Service\StationService;
use Uhifadhi\Contracts\Devkit\ContentProviderInterface;
use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * THE POSTS AND THE PEOPLE WORKING OUT OF THEM — eight stations in every demo
 * area, and the roster spread across them with somebody in charge at each.
 *
 * ONE SLICE, NOT TWO. A station and its staffing are separable in the database
 * and inseparable on the screen: every page that draws a post draws who stands
 * there, and an empty staffing list means something — see below — only when the
 * posts around it are staffed. Seeding them together is what makes the emptiness
 * legible.
 *
 * IT SEEDS THE THREE STATES THE SCREENS HAVE TO DRAW, deliberately:
 *
 *   · a post the zoning scheme does not reach, so its zone is blank because the
 *     ground is, not because the derivation failed;
 *   · a post nobody works out of, which is a staffing gap rather than an error;
 *   · everywhere else, exactly one leader — the invariant
 *     {@see PostingService::appointLeader()} holds, seen from the outside.
 *
 * THE ZONE IS NEVER SET HERE. A station's zone is derived from where it stands,
 * and a seeder that assigned one would be asserting the answer instead of
 * producing it — which is precisely the bug such a seeder would hide. This runs
 * after the zones for the same reason a real post is placed after the scheme is
 * imported.
 *
 * THE ROSTER IS READ THROUGH THE CONTRACT. This bundle knows about people only
 * as {@see UserInterface}, the association a posting is mapped at; who those
 * people are, and which module put them there, is not its business.
 *
 * IT SEEDS ONCE, PER AREA. An area that already has posts is left as it is.
 */
final readonly class StationContentProvider implements ContentProviderInterface
{
    /** The post left unstaffed, by its place in {@see DemoArea::stations()}. */
    private const int UNSTAFFED = 6;

    public function __construct(
        private AreaOfInterestRepository $register,
        private StationRepository $posts,
        private StationService $stations,
        private PostingService $postings,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function key(): string
    {
        return 'station';
    }

    public function label(): string
    {
        return 'Stations';
    }

    public function description(): string
    {
        return 'Posts standing in the demo areas, and the people working out of them.';
    }

    public function dependsOn(): array
    {
        return ['area', 'team', 'zone'];
    }

    public function load(): void
    {
        $roster = $this->roster();

        foreach (DemoArea::all() as $demo) {
            $area = $this->register->findOneBy(['name' => $demo->name]);

            if (null === $area || $this->posts->countByArea($area) > 0) {
                continue;
            }

            foreach ($demo->stations() as $index => $post) {
                [$lon, $lat] = $demo->pointOf($post);

                $this->stations->add(
                    $area,
                    $post->name,
                    $lon,
                    $lat,
                    $post->code,
                    elevationM: $post->elevationM,
                    locality: $post->locality,
                );
                $station = $this->posts->findOneBy(['area' => $area, 'name' => $post->name]);
                if (null === $station) {
                    continue;
                }

                if (self::UNSTAFFED === $index) {
                    continue;
                }

                $this->staff($station, $index, $roster);
            }
        }
    }

    /**
     * SOMEBODY IN CHARGE AND, WHERE THE ROSTER IS BIG ENOUGH, SOMEBODY WITH
     * THEM — walked round the roster so the demo has people standing at more
     * than one post, which is the ordinary case and the one a person's own page
     * is built for.
     *
     * @param list<UserInterface> $roster
     */
    private function staff(Station $station, int $index, array $roster): void
    {
        $people = \count($roster);
        if (0 === $people) {
            return;
        }

        $leader = $this->postings->post($station, $roster[$index % $people], PostingSource::WrittenHere);
        $this->postings->appointLeader($leader);

        if ($people > 1) {
            $this->postings->post($station, $roster[($index + 1) % $people], PostingSource::WrittenHere);
        }
    }

    /**
     * EVERYBODY A POSTING COULD NAME. An installation seeded without a roster
     * still gets its posts; they simply stand empty until somebody is hired.
     *
     * @return list<UserInterface>
     */
    private function roster(): array
    {
        return $this->entityManager->getRepository(UserInterface::class)->findBy([], ['id' => 'ASC']);
    }
}
