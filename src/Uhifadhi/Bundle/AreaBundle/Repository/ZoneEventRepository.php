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
use Uhifadhi\Bundle\AreaBundle\Entity\ZoneEvent;

/**
 * The zone log, newest first and bounded.
 *
 * A HISTORY CARD IS NOT A LEDGER. The page shows the recent end of the log at a
 * fixed height, so the query is capped here rather than in the template: a card
 * whose height grows with the number of imports an area has had is a card that
 * eventually owns the page.
 *
 * @extends ServiceEntityRepository<ZoneEvent>
 */
class ZoneEventRepository extends ServiceEntityRepository
{
    /** What the card shows before it starts to own the column it sits in. */
    public const int RECENT = 8;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ZoneEvent::class);
    }

    /**
     * @return list<ZoneEvent>
     */
    public function findByArea(AreaOfInterest $area, int $limit = self::RECENT): array
    {
        /** @var list<ZoneEvent> $entries */
        $entries = $this->createQueryBuilder('e')
            ->where('e.area = :area')
            ->setParameter('area', $area)
            // Two events of one request share a second; the id breaks the tie so
            // the order a page shows is the order they happened in.
            ->orderBy('e.occurredAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $entries;
    }

    public function countByArea(AreaOfInterest $area): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.area = :area')
            ->setParameter('area', $area)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
