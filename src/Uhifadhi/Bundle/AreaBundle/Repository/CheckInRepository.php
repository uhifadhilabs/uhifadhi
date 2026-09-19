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
use Uhifadhi\Bundle\AreaBundle\Entity\CheckIn;
use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * @extends ServiceEntityRepository<CheckIn>
 */
class CheckInRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CheckIn::class);
    }

    /** One claim, by the identity the handset minted for it. */
    public function findByRef(AreaOfInterest $area, string $clientRef): ?CheckIn
    {
        return $this->findOneBy(['area' => $area, 'clientRef' => $clientRef]);
    }

    /**
     * THIS AREA'S CLAIMS FOR ONE DAY, with everything a reading needs
     * already loaded: the corrections that may have overtaken them and
     * the post each names.
     *
     * @return list<CheckIn>
     */
    public function findForDay(AreaOfInterest $area, \DateTimeImmutable $localDate): array
    {
        /** @var list<CheckIn> $rows */
        $rows = $this->createQueryBuilder('c')
            ->addSelect('corrections', 'station', 'status')
            ->leftJoin('c.corrections', 'corrections')
            ->leftJoin('c.station', 'station')
            ->leftJoin('c.status', 'status')
            ->andWhere('c.area = :area')
            ->andWhere('c.localDate = :day')
            ->setParameter('area', $area)
            ->setParameter('day', $localDate->setTime(0, 0))
            ->orderBy('c.occurredAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * EVERY WATCH IN THIS AREA NOBODY HAS CHECKED OUT OF — what "live"
     * is made of.
     *
     * OPEN MEANS NO CHECK-OUT, and that is all this can know. A watch
     * whose ROSTERED end has passed is closed too, but the rostered end
     * is the roster's to say and this bundle does not hold it; the
     * caller that has a roster drops those, exactly as the day read
     * does.
     *
     * NOT KEYED BY DAY, on purpose: a night watch that began yesterday
     * and has not been closed is somebody who is on duty now, and a
     * query filtered to today's date would lose them at midnight.
     *
     * @return list<CheckIn>
     */
    public function findOpenIn(AreaOfInterest $area): array
    {
        /** @var list<CheckIn> $rows */
        $rows = $this->createQueryBuilder('c')
            ->addSelect('corrections', 'station', 'status')
            ->leftJoin('c.corrections', 'corrections')
            ->leftJoin('c.station', 'station')
            ->leftJoin('c.status', 'status')
            ->andWhere('c.area = :area')
            ->andWhere('c.endedAt IS NULL')
            ->setParameter('area', $area)
            ->orderBy('c.occurredAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /** One person's claim for one day, where they made one. */
    public function findForPersonOnDay(AreaOfInterest $area, UserInterface $person, \DateTimeImmutable $localDate): ?CheckIn
    {
        return $this->findOneBy([
            'area' => $area,
            'person' => $person,
            'localDate' => $localDate->setTime(0, 0),
        ]);
    }
}
