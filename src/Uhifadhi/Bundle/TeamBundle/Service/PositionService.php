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

namespace Uhifadhi\Bundle\TeamBundle\Service;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Exception\NameNotUniqueException;
use Uhifadhi\Bundle\TeamBundle\Exception\UnknownPermissionException;

/**
 * WHAT A POSITION IS, AND WHAT IT GRANTS — the only writes that shape either.
 *
 * A position is the only thing that grants a staff member any capability at
 * all, so the two facts about it are its NAME — unique across the whole
 * organization, because a position belongs to no department — and its GRANT.
 * Both are written here.
 *
 * THE GRANT IS VALIDATED AGAINST THE CATALOGUE, ALWAYS. The list of permissions
 * is not fixed — seven are this bundle's and the rest arrive and leave with the
 * modules that declare them — so what may be written is asked of the catalogue
 * rather than assumed. A value nothing provides is refused rather than stored,
 * and a value a position ALREADY holds that nothing provides any more is left
 * exactly where it is: pruned, not purged. Removing it on the module's way out
 * would silently rewrite what an administrator granted.
 *
 * IT DECIDES NOTHING ABOUT WHO IS ASKING. Whether the administrator on the
 * other end may confer this permission is an area-scope question about the
 * signed-in session
 * ({@see \Uhifadhi\Bundle\TeamBundle\Security\AreaAuthority}), settled by the
 * screen before it calls in here.
 */
final readonly class PositionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PermissionCatalogue $catalogue,
    ) {
    }

    /**
     * ONE FIELD, AND IT IS THE NAME — unique across the organization, because
     * a position belongs to nobody. There is one Sergeant, not one per
     * department, so a reader of a person's record never has to ask which.
     *
     * A NEW POSITION IS BORN EMPTY, and the day it was born is the day it
     * fell vacant: a position written in March and never filled has stood
     * empty since March, which is exactly what a director needs to see.
     *
     * @throws NameNotUniqueException when the organization already has the name
     */
    public function create(string $name): Position
    {
        $position = new Position()->setName($name)
            ->setVacantSince(new \DateTimeImmutable());

        $this->entityManager->persist($position);
        $this->flush($name);

        return $position;
    }

    /** @throws NameNotUniqueException when the organization already has the name */
    public function rename(Position $position, string $name): void
    {
        $position->setName($name);

        $this->flush($name);
    }

    /**
     * THE WHOLE GRANT, REPLACED. What is absent from the list is what was
     * revoked — the matrix posts only what is ticked — so this is a set, never
     * an addition.
     *
     * @param list<string> $values
     *
     * @throws UnknownPermissionException when a value no installed module provides is granted
     */
    public function setPermissions(Position $position, array $values): void
    {
        $position->setPermissionValues($values, $this->catalogue->values());

        $this->entityManager->flush();
    }

    /**
     * The unique index on the name is what actually refuses a repeated one.
     * Carried out of the storage layer here so a caller catches a fact about
     * the org chart rather than a driver exception.
     *
     * @throws NameNotUniqueException
     */
    private function flush(string $name): void
    {
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $clash) {
            throw new NameNotUniqueException($name, $clash);
        }
    }
}
