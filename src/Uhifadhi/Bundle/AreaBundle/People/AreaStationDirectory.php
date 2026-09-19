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

namespace Uhifadhi\Bundle\AreaBundle\People;

use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\PostingRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\StationRepository;
use Uhifadhi\Contracts\Area\DirectoryArea;
use Uhifadhi\Contracts\Area\PostedStation;
use Uhifadhi\Contracts\Area\StationDirectoryInterface;
use Uhifadhi\Contracts\Area\StationPost;

/**
 * THE AREA'S ANSWER TO "WHO IS AT THIS POST?", across every area at once.
 *
 * IT IS A CONTRACT IMPLEMENTATION AND NOT A SERVICE, which is why it is named
 * for what it fulfils rather than what it does. Nothing in either bundle names
 * a class in the other, and this is the whole of the join.
 *
 * TWO QUERIES FOR THE WHOLE BOARD, NEVER ONE PER STATION. The stations come in
 * one read with their area and zone joined, the standing postings in another,
 * and the two are matched in memory — a board that asked each station for its
 * people would issue a query a row and still have to ask again for the empty
 * ones.
 */
final readonly class AreaStationDirectory implements StationDirectoryInterface
{
    public function __construct(
        private StationRepository $stations,
        private PostingRepository $postings,
        private AreaOfInterestRepository $areas,
    ) {
    }

    public function areas(): array
    {
        $areas = [];
        foreach ($this->areas->findAllOrdered() as $area) {
            $uuid = $area->getUuidString();
            if (null !== $uuid) {
                $areas[] = new DirectoryArea($uuid, (string) $area->getName());
            }
        }

        return $areas;
    }

    public function stations(): array
    {
        $byStation = [];
        foreach ($this->postings->findAllStanding() as $posting) {
            $station = $posting->getStation()?->getUuidString();
            $person = $posting->getPerson()?->getUuidString();
            $since = $posting->getSince();

            /*
             * A POSTING WITHOUT ITS STATION, ITS PERSON OR ITS DAY IS NOT A
             * LINE ON A BOARD. The model forbids all three — they are non-null
             * columns — and a reader that assumed so would be a reader that
             * fataled to prove it.
             */
            if (null === $station || null === $person || null === $since) {
                continue;
            }

            $byStation[$station][] = new StationPost($person, $since, $posting->isLeader());
        }

        $board = [];
        foreach ($this->stations->findAllOrdered() as $station) {
            $area = $station->getArea();
            $uuid = $station->getUuidString();
            if (null === $area || null === $uuid) {
                continue;
            }

            $board[] = new PostedStation(
                uuid: $uuid,
                name: (string) $station->getName(),
                code: $station->getCode(),
                areaUuid: (string) $area->getUuidString(),
                areaName: (string) $area->getName(),
                zoneName: $station->getZone()?->getName(),
                posts: $byStation[$uuid] ?? [],
            );
        }

        return $board;
    }
}
