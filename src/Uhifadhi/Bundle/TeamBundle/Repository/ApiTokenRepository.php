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
use Uhifadhi\Bundle\TeamBundle\Entity\ApiToken;
use Uhifadhi\Bundle\TeamBundle\Entity\User;

/**
 * @extends ServiceEntityRepository<ApiToken>
 */
final class ApiTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiToken::class);
    }

    /**
     * The row behind a presented token, found by its hash.
     *
     * Whether it still authenticates is the ENTITY's question, not this one, so
     * a withdrawn or expired token is still FOUND — which is what lets a caller
     * decide how much of that to say out loud.
     */
    public function findOneByHash(string $tokenHash): ?ApiToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    /** The token this handset already has, if any — signing in again rotates it. */
    public function findOneByDevice(User $owner, string $deviceId): ?ApiToken
    {
        return $this->findOneBy(['owner' => $owner, 'deviceId' => $deviceId]);
    }
}
