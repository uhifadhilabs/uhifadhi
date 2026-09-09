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
use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Exception\NameNotUniqueException;
use Uhifadhi\Bundle\TeamBundle\Exception\UnknownPermissionException;

/**
 * WHAT A POSITION IS, AND WHAT IT GRANTS — the only writes that shape either.
 *
 * A position is the only thing that grants a staff member any capability at
 * all, so the two facts about it are its FILING (a name inside a department,
 * unique there and nowhere else) and its GRANT (a set of catalogue values).
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
 * other end may file under this department, or confer this permission, is an
 * area-scope question about the signed-in session
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
     * TWO FIELDS, AND THE DEPARTMENT IS THE FIRST OF THEM — the name is unique
     * inside that department and nowhere else, so *Ecology / Analyst* and
     * *Protection Service / Analyst* are two different jobs that share a word.
     *
     * A null department is a real state: a position created before anybody
     * decided which department owns it exists, and its holders read as
     * Unassigned on the roster.
     *
     * @throws NameNotUniqueException when that department already owns the name
     */
    public function create(string $name, ?Department $department): Position
    {
        $position = new Position()->setName($name)->setDepartment($department);

        $this->entityManager->persist($position);
        $this->flush($name);

        return $position;
    }

    /** @throws NameNotUniqueException when that department already owns the name */
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
     * FILE A POSITION, OR UNFILE IT. Moving one between departments changes
     * where its work is READ and nothing about what it grants; the empty
     * destination is a destination, because a position whose department was a
     * mistake has to be able to leave it.
     *
     * @throws NameNotUniqueException when the destination already owns the name
     */
    public function file(Position $position, ?Department $department): void
    {
        $position->setDepartment($department);

        $this->flush((string) $position->getName());
    }

    /**
     * The unique index inside a department is what actually refuses a repeated
     * name. Carried out of the storage layer here so a caller catches a fact
     * about the org chart and words it with the department in it — which it has
     * to, because the same word in another department is fine.
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
