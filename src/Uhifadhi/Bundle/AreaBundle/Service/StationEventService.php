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

use Doctrine\ORM\EntityManagerInterface;
use Uhifadhi\Bundle\AreaBundle\Entity\Station;
use Uhifadhi\Bundle\AreaBundle\Entity\StationEvent;
use Uhifadhi\Bundle\AreaBundle\Enum\PostingSource;
use Uhifadhi\Bundle\AreaBundle\Enum\StationEventKind;

/**
 * A STATION'S LOG, WRITTEN — one line per event, in the product's own words.
 *
 * ONE PLACE COMPOSES THE SENTENCES, as for the zone log: the station page and
 * the configure section both change stations, and two screens wording the same
 * event differently is how a log stops being readable.
 *
 * THE GROUND WRITES HERE TOO. "Zone re-derived from the point" is not
 * somebody's doing — an import moved the ring under a post nobody touched —
 * and the station's own page is where that belongs.
 *
 * ONLY WHEN THE ANSWER CHANGED. A zone write re-asks the question for every
 * station in the area, and logging the unchanged ones would put a line on
 * every post in the area every time anybody edited a zone. The design draws an
 * "unchanged" line because a reader wanted reassurance after a big import;
 * flooding the log to give it is the more expensive mistake, so a line is
 * written where the zone actually moved and the residual is named in the
 * bundle's design decisions.
 */
final readonly class StationEventService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function recorded(Station $station, ?string $actor): void
    {
        $this->write($station, StationEventKind::Recorded, 'Station recorded', self::by($actor), $actor);
    }

    public function renamed(Station $station, string $from, string $to, ?string $actor): void
    {
        $this->write($station, StationEventKind::Renamed, \sprintf('“%s” renamed to “%s”', $from, $to), self::by($actor), $actor);
    }

    /**
     * THE MOVE, WITH ITS SIZE AND ITS DIRECTION. "The point changed" is a fact
     * nobody can check; "moved 340 m west" is one somebody can walk to.
     */
    public function pointMoved(Station $station, int $metres, string $heading, ?string $actor): void
    {
        $this->write(
            $station,
            StationEventKind::PointMoved,
            \sprintf('Point moved %s m %s', number_format($metres), $heading),
            self::by($actor),
            $actor,
        );
    }

    /**
     * THE GROUND MOVED UNDER IT. `$because` names what did it — an import, a
     * replaced ring, a removal — because "the zone changed" without that is a
     * line whose cause is on another page.
     */
    public function zoneDerived(Station $station, ?string $zoneName, string $because): void
    {
        $this->write(
            $station,
            StationEventKind::ZoneDerived,
            null === $zoneName ? 'Now in no zone' : \sprintf('Now in %s', $zoneName),
            $because,
        );
    }

    public function posted(Station $station, string $person, PostingSource $source, ?string $actor): void
    {
        $this->write($station, StationEventKind::Posted, \sprintf('%s posted here', $person), implode(' · ', array_filter([
            null === $actor ? null : 'by '.$actor,
            PostingSource::FromTheirPage === $source ? 'from their own page' : 'from this page',
        ])), $actor);
    }

    public function postingEnded(Station $station, string $person, ?string $actor): void
    {
        $this->write($station, StationEventKind::PostingEnded, \sprintf('%s’s posting ended', $person), self::by($actor), $actor);
    }

    public function leaderAppointed(Station $station, string $person, ?string $actor): void
    {
        $this->write($station, StationEventKind::LeaderAppointed, \sprintf('%s leads here', $person), self::by($actor), $actor);
    }

    public function deactivated(Station $station, ?string $actor): void
    {
        $this->write($station, StationEventKind::Deactivated, 'Station closed', implode(' · ', array_filter([
            self::by($actor),
            'nothing was deleted',
        ])), $actor);
    }

    public function reactivated(Station $station, ?string $actor): void
    {
        $this->write($station, StationEventKind::Reactivated, 'Station reopened', self::by($actor), $actor);
    }

    private static function by(?string $actor): ?string
    {
        return null === $actor ? null : 'by '.$actor;
    }

    private function write(Station $station, StationEventKind $kind, string $headline, ?string $detail, ?string $actor = null): void
    {
        $this->entityManager->persist(new StationEvent()
            ->setStation($station)
            ->setKind($kind)
            ->setHeadline($headline)
            ->setDetail(null === $detail || '' === $detail ? null : $detail)
            ->setActor($actor)
            ->setOccurredAt(new \DateTimeImmutable()));
        $this->entityManager->flush();
    }
}
