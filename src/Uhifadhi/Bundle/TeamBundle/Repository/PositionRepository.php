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

    /** The one position of this name in the organization, or null — names are unique org-wide. */
    public function findOneByName(string $name): ?Position
    {
        return $this->findOneBy(['name' => $name]);
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

    /**
     * THE POSITIONS SOMEBODY MAY BE GIVEN — every one that has not been
     * retired, by name.
     *
     * A RETIRED POSITION IS ABSENT FROM A PICKER AND PRESENT IN THE
     * REGISTER. It is not deleted, so the register draws it greyed and an
     * administrator can find the name and reinstate it; but offering it in
     * the list of things to hand somebody would be offering a thing that is
     * closed.
     *
     * @return list<Position>
     */
    public function findAssignable(): array
    {
        /** @var list<Position> $positions */
        $positions = $this->createQueryBuilder('p')
            ->andWhere('p.retiredAt IS NULL')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $positions;
    }
}
