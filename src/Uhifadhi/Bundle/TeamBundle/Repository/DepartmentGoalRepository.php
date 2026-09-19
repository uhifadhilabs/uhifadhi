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
use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Entity\DepartmentGoal;

/**
 * @extends ServiceEntityRepository<DepartmentGoal>
 */
final class DepartmentGoalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DepartmentGoal::class);
    }

    /**
     * One department's goals, the soonest to close first — a list read by
     * what needs answering next.
     *
     * @return list<DepartmentGoal>
     */
    public function findForDepartment(Department $department): array
    {
        /** @var list<DepartmentGoal> $goals */
        $goals = $this->createQueryBuilder('g')
            ->andWhere('g.department = :department')
            ->setParameter('department', $department)
            ->orderBy('g.closesAt', 'ASC')
            ->addOrderBy('g.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $goals;
    }

    /**
     * EVERY GOAL IN THE ORGANISATION, for the surfaces that read across
     * departments — the performance page's rail and its "needs a decision".
     *
     * @return list<DepartmentGoal>
     */
    public function findAllOrdered(): array
    {
        /** @var list<DepartmentGoal> $goals */
        $goals = $this->createQueryBuilder('g')
            ->join('g.department', 'd')
            ->orderBy('d.name', 'ASC')
            ->addOrderBy('g.closesAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $goals;
    }
}
