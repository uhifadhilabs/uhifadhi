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
use Symfony\Component\Uid\Uuid;
use Uhifadhi\Bundle\TeamBundle\Entity\PositionTitle;

/**
 * @extends ServiceEntityRepository<PositionTitle>
 */
class PositionTitleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PositionTitle::class);
    }

    public function findOneByUuid(Uuid $uuid): ?PositionTitle
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    public function findOneByName(string $name): ?PositionTitle
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * BY NAME, because a vocabulary list is looked up rather than ranked: a
     * reader scanning for "Ranger" wants it where the alphabet puts it.
     *
     * @return list<PositionTitle>
     */
    public function findAllOrdered(): array
    {
        /** @var list<PositionTitle> $titles */
        $titles = $this->createQueryBuilder('t')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $titles;
    }
}
