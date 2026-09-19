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
use Uhifadhi\Bundle\AreaBundle\Entity\CheckIn;
use Uhifadhi\Bundle\AreaBundle\Entity\CheckInCorrection;

/**
 * @extends ServiceEntityRepository<CheckInCorrection>
 */
class CheckInCorrectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CheckInCorrection::class);
    }

    /** One appended claim, by the identity the handset minted for it. */
    public function findByRef(CheckIn $checkIn, string $clientRef): ?CheckInCorrection
    {
        return $this->findOneBy(['checkIn' => $checkIn, 'clientRef' => $clientRef]);
    }
}
