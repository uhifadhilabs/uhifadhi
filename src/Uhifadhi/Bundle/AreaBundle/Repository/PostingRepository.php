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

namespace Uhifadhi\Bundle\AreaBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Entity\Posting;
use Uhifadhi\Bundle\AreaBundle\Entity\Station;
use Uhifadhi\Bundle\AreaBundle\Entity\Zone;
use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * Posting lookups, and every one of them means STANDING unless it says
 * otherwise.
 *
 * ENDED POSTINGS ARE THE PAST, not deleted rows: they are what gives last
 * year's patrol a crew, so they stay in the table and out of every question a
 * page asks about who works somewhere today. A finder that silently included
 * them would put people who left on the staffing list.
 *
 * @extends ServiceEntityRepository<Posting>
 */
class PostingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Posting::class);
    }

    /**
     * @return list<Posting>
     */
    public function findStandingByStation(Station $station): array
    {
        /** @var list<Posting> $postings */
        $postings = $this->standing()
            ->andWhere('p.station = :station')
            ->setParameter('station', $station)
            ->getQuery()
            ->getResult();

        return $postings;
    }

    /**
     * Everybody working out of any station in the area — what the zones tab's
     * people list reads.
     *
     * @return list<Posting>
     */
    public function findStandingByArea(AreaOfInterest $area): array
    {
        /** @var list<Posting> $postings */
        $postings = $this->standing()
            ->join('p.station', 's')
            ->andWhere('s.area = :area')
            ->setParameter('area', $area)
            ->getQuery()
            ->getResult();

        return $postings;
    }

    /**
     * Everybody working out of a station inside this zone. A zone has no
     * people of its own; it has the ground its stations stand on.
     *
     * @return list<Posting>
     */
    public function findStandingByZone(Zone $zone): array
    {
        /** @var list<Posting> $postings */
        $postings = $this->standing()
            ->join('p.station', 's')
            ->andWhere('s.zone = :zone')
            ->setParameter('zone', $zone)
            ->getQuery()
            ->getResult();

        return $postings;
    }

    /** Where this person works today — what their own page asks through the seam. */
    /** @return list<Posting> */
    public function findStandingByPerson(UserInterface $person): array
    {
        /** @var list<Posting> $postings */
        $postings = $this->standing()
            ->andWhere('p.person = :person')
            ->setParameter('person', $person)
            ->getQuery()
            ->getResult();

        return $postings;
    }

    /**
     * EVERYBODY'S STANDING POSTINGS IN ONE QUERY, keyed by nothing — the
     * caller groups them. A person's page is drawn a page of people at a
     * time, so asking per person would be a query per row of a register.
     *
     * @param list<string> $userUuids
     *
     * @return list<Posting>
     */
    public function findStandingByPersonUuids(array $userUuids): array
    {
        if ([] === $userUuids) {
            return [];
        }

        /** @var list<Posting> $postings */
        $postings = $this->standing()
            ->join('p.person', 'u')
            ->andWhere('u.uuid IN (:uuids)')
            ->setParameter('uuids', $userUuids)
            ->getQuery()
            ->getResult();

        return $postings;
    }

    public function findStandingFor(Station $station, UserInterface $person): ?Posting
    {
        /** @var Posting|null $posting */
        $posting = $this->standing()
            ->andWhere('p.station = :station')->setParameter('station', $station)
            ->andWhere('p.person = :person')->setParameter('person', $person)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $posting;
    }

    public function findLeaderAt(Station $station): ?Posting
    {
        /** @var Posting|null $posting */
        $posting = $this->standing()
            ->andWhere('p.station = :station')->setParameter('station', $station)
            ->andWhere('p.leader = true')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $posting;
    }

    public function countStandingByStation(Station $station): int
    {
        return (int) $this->standing()
            ->select('COUNT(p.id)')
            ->andWhere('p.station = :station')
            ->setParameter('station', $station)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * THE STANDING SET, and the ordering every surface reads them in: the
     * leader first, because the design puts the lead at the top of the board,
     * then by how long they have been there.
     */
    private function standing(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->where('p.endedAt IS NULL')
            ->orderBy('p.leader', 'DESC')
            ->addOrderBy('p.since', 'ASC')
            ->addOrderBy('p.id', 'ASC');
    }
}
