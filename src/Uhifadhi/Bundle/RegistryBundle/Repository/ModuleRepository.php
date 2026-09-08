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

namespace Uhifadhi\Bundle\RegistryBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Uhifadhi\Bundle\RegistryBundle\Entity\Module;

/**
 * The catalogue table's query surface.
 *
 * Note what it is NOT: the catalogue itself. Rows outlive the bundles that
 * wrote them — removing a module leaves its row and the areas' data
 * behind on purpose — so "every row in this table" and "every module this
 * deployment has" are different questions.
 * {@see \Uhifadhi\Bundle\RegistryBundle\Service\ModuleCatalogue} answers the second one and
 * is what a surface should read.
 *
 * NOT FINAL. This bundle is installed by other packages, and a Doctrine
 * repository is the documented place to add a query — or to stand in for one
 * in somebody else's test. Sealing it would make the registry's query surface the
 * only one its consumers may ever have.
 *
 * @extends ServiceEntityRepository<Module>
 */
class ModuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Module::class);
    }

    /**
     * Every row, in catalogue order.
     *
     * @return list<Module>
     */
    public function catalogue(): array
    {
        /** @var list<Module> $result */
        $result = $this->createQueryBuilder('m')
            ->orderBy('m.position', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findBySlug(string $slug): ?Module
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
