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

namespace Uhifadhi\Bundle\AreaBundle\Service;

use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Entity\Zone;
use Uhifadhi\Bundle\AreaBundle\Model\PostingQuery;
use Uhifadhi\Bundle\AreaBundle\Model\PostingRow;
use Uhifadhi\Bundle\AreaBundle\Model\StationCard;
use Uhifadhi\Bundle\AreaBundle\Model\ZoneStaffing;
use Uhifadhi\Bundle\AreaBundle\Repository\PostingRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\StationRepository;

/**
 * WHAT A ZONE'S CARD KNOWS ABOUT THE POSTS ON ITS GROUND.
 *
 * A ZONE HAS NO PEOPLE OF ITS OWN and this service never pretends otherwise:
 * it counts the posts whose point falls inside the zone, and the people
 * standing at those posts. Nobody is ever posted to a zone, so nothing here
 * takes a person and a zone together.
 *
 * SHUT CARDS ARE COUNTED FOR THE WHOLE AREA AT ONCE and only the open one is
 * read in full. A configure page draws eleven zones and opens one, so eleven
 * boards would be ten boards nobody looked at — and the two numbers a shut
 * card states are a GROUP BY, not a board.
 *
 * THE FACETS SEAM IS ASKED ONCE FOR THE WHOLE ZONE, not once per post. The
 * board service takes the postings it is handed, so a zone with four posts
 * asks whoever owns people one question and splits the answer here.
 */
final readonly class ZoneStationService
{
    public function __construct(
        private StationRepository $stations,
        private PostingRepository $postings,
        private PostingBoardService $board,
    ) {
    }

    /** The two numbers every zone card of this area states shut. */
    public function staffing(AreaOfInterest $area): ZoneStaffing
    {
        return new ZoneStaffing(
            $this->stations->countPerZone($area),
            $this->postings->countStandingPerZone($area),
        );
    }

    /**
     * EVERY POST IN ONE ZONE, AS CARDS, in the order the register reads them.
     *
     * @return list<StationCard>
     */
    public function cardsFor(Zone $zone): array
    {
        $stations = $this->stations->findByZone($zone);
        if ([] === $stations) {
            return [];
        }

        // ONE BOARD FOR THE WHOLE ZONE, split by the post each row names.
        $byStation = [];
        foreach ($this->board->board($this->postings->findStandingByZone($zone), new PostingQuery())['rows'] as $row) {
            $byStation[$row->stationUuid][] = $row;
        }

        $cards = [];
        foreach ($stations as $station) {
            $uuid = (string) $station->getUuidString();
            /** @var list<PostingRow> $people */
            $people = $byStation[$uuid] ?? [];
            $leader = null;

            foreach ($people as $person) {
                if ($person->leader) {
                    $leader = $person->name;
                    break;
                }
            }

            $cards[] = new StationCard(
                uuid: $uuid,
                name: (string) $station->getName(),
                code: $station->getCode(),
                posted: \count($people),
                leaderName: $leader,
                faces: \array_slice($people, 0, StationCard::FACES),
            );
        }

        return $cards;
    }
}
