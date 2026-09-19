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

use Uhifadhi\Bundle\AreaBundle\Entity\CheckIn;
use Uhifadhi\Bundle\AreaBundle\Enum\CheckInStatusKind;
use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\CheckInRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\PersonPositionRepository;
use Uhifadhi\Contracts\Area\DayState;
use Uhifadhi\Contracts\Area\PersonDay;
use Uhifadhi\Contracts\Area\PersonWatch;
use Uhifadhi\Contracts\Area\PresenceProviderInterface;
use Uhifadhi\Contracts\Area\UnverifiedReason;
use Uhifadhi\Contracts\Roster\WatchProviderInterface;

/**
 * HOW A DAY READS — derived here, on every read, and stored nowhere.
 *
 * THE CLAIM AND THE PROOF ARE TWO DIFFERENT RECORDS and this is the
 * only place they meet. A check-in says "at post"; the pings say where
 * the phone was; the post says what inside means. `verified` is the
 * answer to a question asked at the moment of asking — so a catchment
 * corrected next month re-derives every day that used it, which is
 * precisely what a stored verdict could not do.
 *
 * UNVERIFIED IS NEVER AN ACCUSATION. It has three causes and the
 * reading says which: no position arrived, the post has no ring, or the
 * position fell outside it. Only the third is about where somebody
 * stood, and even that is a fact about a fix rather than about a
 * person.
 *
 * THE WATCH CLOSES ITSELF ON READ. A ranger who never checked out is
 * not an open watch forever: the rostered end closes it and the day is
 * marked `not_checked_out`. Derived, like everything else here — a
 * state that needed a nightly job to be true would be wrong every night
 * the job did not run, and right again the morning somebody noticed.
 *
 * THE LAST CORRECTION OF THE DAY IS WHAT THE DAY WAS. A ranger who
 * checked in at post and left on an escort at 07:40 had both; the day's
 * own reading is where they ended, and the morning is still on the
 * record for anybody who asks for 06:30.
 */
final readonly class PresenceService implements PresenceProviderInterface
{
    public function __construct(
        private AreaOfInterestRepository $areas,
        private CheckInRepository $checkIns,
        private PersonPositionRepository $positions,
        /** @var iterable<WatchProviderInterface> */
        private iterable $rosters = [],
    ) {
    }

    public function dayIn(string $areaUuid, string $localDate): array
    {
        $area = $this->areas->findOneBy(['uuid' => $areaUuid]);
        if (null === $area) {
            return [];
        }

        $day = new \DateTimeImmutable($localDate);

        /*
         * ONE PERSON, ONE DAY, HOWEVER MANY WATCHES. Ruled 2026-09-21: a
         * day holds any number of check-in/check-out pairs. Reading each
         * claim as its own "day" would hand a caller two entries for one
         * person on one date, and whichever it kept first would be the
         * only one it ever saw.
         *
         * Keyed while folding and re-indexed on the way out, so the list
         * is a list and the order is the order people first reported.
         */
        $days = [];
        foreach ($this->checkIns->findForDay($area, $day) as $checkIn) {
            $person = $checkIn->getPerson();
            $uuid = (string) $person?->getUuidString();

            $days[$uuid] ??= [
                'name' => $person?->getFullName() ?? '',
                'watches' => [],
            ];
            $days[$uuid]['watches'][] = $this->watch($checkIn, $areaUuid, $localDate);
        }

        $read = [];
        foreach ($days as $uuid => $held) {
            /** @var list<PersonWatch> $watches */
            $watches = $held['watches'];
            $read[] = self::fold($uuid, (string) $held['name'], $localDate, $watches);
        }

        return $read;
    }

    /**
     * THE DAY, OUT OF ITS WATCHES.
     *
     * The day READS as its last watch — what somebody is doing now, or
     * finished the day doing, is what a board is asking when it colours
     * a name. The totals are the whole day's, and the first claim is the
     * first claim.
     *
     * @param list<PersonWatch> $watches
     */
    private static function fold(string $personUuid, string $personName, string $localDate, array $watches): PersonDay
    {
        $pings = 0;
        $lastPingAt = null;
        foreach ($watches as $watch) {
            $pings += $watch->pings;
            if (null !== $watch->lastPingAt && (null === $lastPingAt || $watch->lastPingAt > $lastPingAt)) {
                $lastPingAt = $watch->lastPingAt;
            }
        }

        // A DAY WITH NO WATCH IN IT IS NOT BUILT — this is only ever called
        // with the claims somebody made — so the last one is the day's
        // reading and there is always one.
        $last = [] === $watches ? null : $watches[\count($watches) - 1];

        return new PersonDay(
            personUuid: $personUuid,
            personName: $personName,
            localDate: $localDate,
            state: null === $last ? DayState::NotWorking : $last->state,
            watches: $watches,
            occurredAt: ([] === $watches ? null : $watches[0]->occurredAt),
            lastPingAt: $lastPingAt,
            pings: $pings,
        );
    }

    public function dayFor(string $areaUuid, string $personUuid, string $localDate): ?PersonDay
    {
        foreach ($this->dayIn($areaUuid, $localDate) as $person) {
            if ($person->personUuid === $personUuid) {
                return $person;
            }
        }

        return null;
    }

    /** One claim, read against its own proof — one watch of somebody's day. */
    private function watch(CheckIn $checkIn, string $areaUuid, string $localDate): PersonWatch
    {
        // WHAT THE DAY ENDED AS. A correction is a second claim from its
        // own moment, and the day's reading is the last of them.
        $end = $checkIn->getEndedAt() ?? new \DateTimeImmutable();
        $state = $checkIn->stateAt($end);

        $status = $state['status'];
        $station = $state['station'];
        $kind = $status?->getKind() ?? CheckInStatusKind::AtPost;

        $distance = null;
        $reason = null;
        $day = match ($kind) {
            CheckInStatusKind::WorkingElsewhere => DayState::WorkingElsewhere,
            CheckInStatusKind::NotWorking => DayState::NotWorking,
            CheckInStatusKind::Special => DayState::Special,
            CheckInStatusKind::AtPost => DayState::AtPostUnverified,
        };

        if (CheckInStatusKind::AtPost === $kind) {
            $point = $station?->getPoint();
            $catchment = $station?->getCatchmentM();
            $nearest = null === $point ? null : $this->positions->nearestTo($checkIn, $point);

            if (null === $nearest) {
                // NO POSITION AT ALL — the never-block rule's own state:
                // the claim stands and nothing bears it out yet.
                $reason = UnverifiedReason::NoFix;
            } elseif (null === $catchment) {
                // A POST WITH NO RING HAS NO INSIDE. Not the ranger's doing
                // and never drawn as such.
                $distance = $nearest['metres'];
                $reason = UnverifiedReason::NoRing;
            } elseif ($nearest['metres'] <= $catchment) {
                $distance = $nearest['metres'];
                $day = DayState::AtPostVerified;
            } else {
                $distance = $nearest['metres'];
                $reason = UnverifiedReason::OutsideRing;
            }
        }

        $tally = $this->positions->tallyFor($checkIn);

        return new PersonWatch(
            clientRef: $checkIn->getClientRef(),
            state: $day,
            statusKey: $status?->getKey(),
            statusLabel: $status?->getLabel(),
            stationUuid: $station?->getUuidString(),
            stationName: $station?->getName(),
            unverifiedReason: $reason,
            distanceM: $distance,
            occurredAt: $checkIn->getOccurredAt(),
            endedAt: $checkIn->getEndedAt(),
            notCheckedOut: $this->notCheckedOut($checkIn, $areaUuid, $localDate),
            lastPingAt: $tally['last'],
            pings: $tally['pings'],
            handoverNote: $checkIn->getHandoverNote(),
            note: $state['note'],
        );
    }

    /**
     * A WATCH THAT ENDED AND NOBODY CLOSED.
     *
     * The rostered end is the roster's to say. An installation with no
     * roster module has no rostered end, so an open watch is simply
     * still open — the phone may yet check out, and marking it closed
     * on a guess would be the server inventing a time somebody stopped
     * work.
     */
    private function notCheckedOut(CheckIn $checkIn, string $areaUuid, string $localDate): bool
    {
        if (null !== $checkIn->getEndedAt()) {
            return false;
        }

        $person = $checkIn->getPerson()?->getUuidString();
        if (null === $person) {
            return false;
        }

        $now = new \DateTimeImmutable();
        foreach ($this->rosters as $roster) {
            foreach ($roster->watchesFor($areaUuid, $person, $localDate, $localDate) as $watch) {
                if ($watch->endsAt < $now) {
                    return true;
                }
            }
        }

        return false;
    }
}
