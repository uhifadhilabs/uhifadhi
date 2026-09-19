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

namespace Uhifadhi\Bundle\TeamBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Uhifadhi\Bundle\TeamBundle\Entity\InstallationPeriodFigure;

/**
 * @extends ServiceEntityRepository<InstallationPeriodFigure>
 */
class InstallationPeriodFigureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InstallationPeriodFigure::class);
    }

    public function findOne(string $periodKey, string $figureKey): ?InstallationPeriodFigure
    {
        return $this->findOneBy(['periodKey' => $periodKey, 'figureKey' => $figureKey]);
    }

    /**
     * EVERY FIGURE OF ONE PERIOD IN ONE READ. The overview compares five
     * figures against the same closed period; asking per figure would be five
     * queries for one row apiece.
     *
     * @return array<string, float|null> keyed by figure
     */
    public function of(string $periodKey): array
    {
        /** @var list<array{figureKey: string, value: float|null}> $rows */
        $rows = $this->createQueryBuilder('f')
            ->select('f.figureKey AS figureKey, f.value AS value')
            ->andWhere('f.periodKey = :period')
            ->setParameter('period', $periodKey)
            ->getQuery()
            ->getArrayResult();

        $written = [];
        foreach ($rows as $row) {
            $written[$row['figureKey']] = $row['value'];
        }

        return $written;
    }
}
