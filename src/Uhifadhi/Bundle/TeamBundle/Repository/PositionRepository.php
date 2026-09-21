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
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Entity\User;

/**
 * @extends ServiceEntityRepository<Position>
 */
final class PositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Position::class);
    }

    public function findOneByUuid(Uuid $uuid): ?Position
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * How many positions have at least one holder.
     *
     * The complement is the interesting number and it is a real state, not a
     * gap: a position created before its first person exists on purpose, and
     * "8 of 9 held" is the strip saying so.
     */
    public function countHeld(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.id)')
            ->innerJoin(User::class, 'u', Join::WITH, 'u.position = p')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * EVERY POSITION, BY NAME - the list a picker draws, and a flat one.
     *
     * It used to be grouped under department names, and it was grouped
     * because it had to be: two of an installation's positions could both be
     * called "Analyst" and a flat list would offer the same word twice with
     * no way to tell which was which. A POSITION'S NAME IS UNIQUE ACROSS THE
     * ORGANIZATION NOW, so there is one Analyst, the word disambiguates
     * itself, and the optgroup would be decoration.
     *
     * @return list<Position>
     */
    public function findAllOrdered(): array
    {
        /** @var list<Position> $positions */
        $positions = $this->createQueryBuilder('p')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $positions;
    }
}
