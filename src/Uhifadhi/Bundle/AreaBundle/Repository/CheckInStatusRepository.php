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
use Uhifadhi\Bundle\AreaBundle\Entity\CheckInStatus;

/**
 * @extends ServiceEntityRepository<CheckInStatus>
 */
class CheckInStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CheckInStatus::class);
    }

    /**
     * WHAT THIS AREA OFFERS, in its own order — the list the handset is
     * given and the list a claim is checked against.
     *
     * @return list<CheckInStatus>
     */
    public function activeFor(AreaOfInterest $area): array
    {
        /** @var list<CheckInStatus> $rows */
        $rows = $this->createQueryBuilder('s')
            ->andWhere('s.area = :area')
            ->andWhere('s.active = true')
            ->setParameter('area', $area)
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /** One of them by the stable word the handset sends. */
    public function findByKey(AreaOfInterest $area, string $key): ?CheckInStatus
    {
        return $this->findOneBy(['area' => $area, 'key' => $key]);
    }

    /**
     * WHEN THIS AREA'S WORDS LAST CHANGED, so a handset can tell whether
     * the list it holds is the list the area publishes.
     */
    public function lastChangedFor(AreaOfInterest $area): ?\DateTimeImmutable
    {
        $latest = null;
        foreach ($this->findBy(['area' => $area]) as $status) {
            $at = $status->getUpdatedAt() ?? $status->getCreatedAt();
            if (null !== $at && (null === $latest || $at > $latest)) {
                $latest = $at;
            }
        }

        return $latest;
    }
}
