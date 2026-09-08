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

namespace Uhifadhi\Bundle\ShellBundle\Widget\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;
use Uhifadhi\Bundle\ShellBundle\Widget\Entity\WidgetPreference;
use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * @extends ServiceEntityRepository<WidgetPreference>
 */
final class WidgetPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WidgetPreference::class);
    }

    /**
     * At most one row exists per (surface, user, area) — the table's two partial
     * unique indexes. A null area is the org-wide layout and matches IS NULL,
     * which findOneBy renders for us.
     */
    public function findOneForUser(string $surface, UserInterface $user, ?Uuid $areaUuid = null): ?WidgetPreference
    {
        return $this->findOneBy(['surface' => $surface, 'user' => $user, 'areaUuid' => $areaUuid]);
    }

    /**
     * EVERY SURFACE THIS INSTALLATION HAS ROWS FOR, whether or not anything
     * still claims it. The set the registry's answer is subtracted from, and
     * the only reason it exists.
     *
     * @return list<string>
     */
    public function storedSurfaces(): array
    {
        /** @var list<string> $surfaces */
        $surfaces = $this->createQueryBuilder('r')
            ->select('DISTINCT r.surface')
            ->orderBy('r.surface', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return $surfaces;
    }

    /**
     * Throw away everything stored for these surfaces, and say how many rows
     * went. A DQL delete rather than a load-and-remove loop: an installation
     * that has been running for years may hold a great many of these, and
     * hydrating them to delete them would be reading them for no reason.
     *
     * An empty list deletes nothing and asks the database nothing — `IN ()` is
     * not valid SQL, and "prune the empty set" is a perfectly ordinary call.
     *
     * @param list<string> $surfaces
     */
    public function deleteForSurfaces(array $surfaces): int
    {
        if ([] === $surfaces) {
            return 0;
        }

        $deleted = $this->createQueryBuilder('r')
            ->delete()
            ->where('r.surface IN (:surfaces)')
            ->setParameter('surfaces', $surfaces)
            ->getQuery()
            ->execute();

        // A DQL DELETE answers with the affected-row count; the signature is
        // mixed because the same method serves SELECTs.
        return \is_int($deleted) ? $deleted : 0;
    }
}
