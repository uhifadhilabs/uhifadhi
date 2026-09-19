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
use Uhifadhi\Bundle\AreaBundle\Entity\Posting;
use Uhifadhi\Bundle\AreaBundle\Entity\Station;
use Uhifadhi\Bundle\AreaBundle\Enum\PostingSource;
use Uhifadhi\Bundle\AreaBundle\Exception\PostingException;
use Uhifadhi\Bundle\AreaBundle\Repository\PostingRepository;
use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * THE ONLY SUPPORTED WAY SOMEBODY IS POSTED, UNPOSTED OR PUT IN CHARGE.
 *
 * ONE LEADER PER STATION, AND APPOINTING ONE IS NOT A REFUSAL. "She leads now"
 * is what somebody means, so the new leader stands up and the old one stands
 * down in ONE transaction — a page that made them do it in two steps would
 * have a moment with two leaders and a moment with none, and whichever of the
 * two failed would be the one nobody noticed.
 *
 * IT IS ENFORCED HERE AND NOT IN THE DATABASE, and the reason is worth
 * knowing: the constraint that would express it is a PARTIAL unique index
 * (`WHERE leader AND ended_at IS NULL`), the ORM mapping cannot declare one,
 * and an index written into a migration by hand is an index the next
 * `migrations:diff` removes. So the rule is a transaction and a test, and
 * tightening it is named in the bundle's design decisions.
 *
 * A POSTING ENDS, IT IS NOT DELETED — see {@see Posting}.
 */
final readonly class PostingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PostingRepository $postings,
        private StationEventService $events,
    ) {
    }

    /**
     * @param \DateTimeImmutable|null $since the day it starts; today where nobody said
     *
     * @throws PostingException when this person already stands at this station
     */
    public function post(
        Station $station,
        UserInterface $person,
        PostingSource $source,
        ?\DateTimeImmutable $since = null,
        ?string $actor = null,
    ): Posting {
        if (null !== $this->postings->findStandingFor($station, $person)) {
            throw PostingException::alreadyPosted($person->getFullName(), (string) $station->getName());
        }

        $posting = new Posting()
            ->setStation($station)
            ->setPerson($person)
            ->setSource($source)
            ->setSince($since ?? new \DateTimeImmutable('today'));

        $this->entityManager->persist($posting);
        $this->entityManager->flush();
        $this->events->posted($station, $person->getFullName(), $source, $actor);

        return $posting;
    }

    /**
     * THE POSTING STOPS TODAY, and the row stays. A leader who leaves leaves
     * the station without one rather than with a ghost, which falls out of the
     * standing set on its own — the lead is a property of a standing posting,
     * and this one is no longer standing.
     */
    public function end(Posting $posting, ?\DateTimeImmutable $on = null, ?string $actor = null): Posting
    {
        if (!$posting->isStanding()) {
            return $posting;
        }

        $posting->setEndedAt($on ?? new \DateTimeImmutable('today'));
        $this->entityManager->flush();

        $station = $posting->getStation();
        if (null !== $station) {
            $this->events->postingEnded($station, $posting->getPerson()?->getFullName() ?? 'Somebody', $actor);
        }

        return $posting;
    }

    /**
     * @throws PostingException when the posting has already ended
     */
    public function appointLeader(Posting $posting, ?string $actor = null): Posting
    {
        if (!$posting->isStanding()) {
            throw PostingException::alreadyEnded($posting->getPerson()?->getFullName() ?? 'That posting');
        }

        $station = $posting->getStation();
        if (null === $station) {
            throw new \LogicException('A posting always belongs to a station.');
        }

        $this->entityManager->wrapInTransaction(function () use ($station, $posting): void {
            foreach ($this->postings->findStandingByStation($station) as $standing) {
                $standing->setLeader($standing->getId() === $posting->getId());
            }
            $this->entityManager->flush();
        });

        $this->events->leaderAppointed($station, $posting->getPerson()?->getFullName() ?? 'Somebody', $actor);

        return $posting;
    }

    /** Nobody leads here any more, and nobody else is put in charge by it. */
    public function standDown(Posting $posting): Posting
    {
        $posting->setLeader(false);
        $this->entityManager->flush();

        return $posting;
    }

    /** @return list<Posting> */
    public function standingAt(Station $station): array
    {
        return $this->postings->findStandingByStation($station);
    }

    public function leaderAt(Station $station): ?Posting
    {
        return $this->postings->findLeaderAt($station);
    }
}
