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
use Uhifadhi\Bundle\TeamBundle\Entity\DepartmentKind;

/**
 * @extends ServiceEntityRepository<DepartmentKind>
 */
class DepartmentKindRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DepartmentKind::class);
    }

    public function findOneByUuid(Uuid $uuid): ?DepartmentKind
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    public function findOneByName(string $name): ?DepartmentKind
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * BY NAME, because a vocabulary list is looked up rather than ranked: a
     * reader scanning for "Support" wants it where the alphabet puts it.
     *
     * @return list<DepartmentKind>
     */
    public function findAllOrdered(): array
    {
        /** @var list<DepartmentKind> $kinds */
        $kinds = $this->createQueryBuilder('k')
            ->orderBy('k.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $kinds;
    }
}
