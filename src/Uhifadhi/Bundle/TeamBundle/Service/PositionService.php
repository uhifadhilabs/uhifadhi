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
use Uhifadhi\Bundle\TeamBundle\Access\ConcernCatalogue;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Exception\NameNotUniqueException;
use Uhifadhi\Bundle\TeamBundle\Exception\UnknownGrantException;

/**
 * WHAT A POSITION IS, AND WHAT IT GRANTS — the only writes that shape either.
 *
 * A position is the only thing that grants a staff member any capability at
 * all, so the two facts about it are its NAME — unique across the whole
 * organization, because a position belongs to no department — and its GRANT.
 * Both are written here.
 *
 * A GRANT IS A (CONCERN, VERB) PAIR. It used to be a flat permission value
 * validated against the flat permission catalogue; the ruled model replaced
 * that with `<concern>.<verb>` pairs declared by whoever enforces them, so
 * this writes {@see Position::setGrantValues()} against
 * {@see ConcernCatalogue::pairs()} and the flat write is gone from here. The
 * flat catalogue is still shipped for the gates that have not moved yet, and
 * nothing on this class reads it.
 *
 * THE GRANT IS VALIDATED AGAINST THE CATALOGUE, ALWAYS. What there is to have
 * a permission about is not fixed — the team's four concerns are this
 * bundle's and the rest arrive and leave with the modules that declare them —
 * so what may be written is asked of the catalogue rather than assumed. A
 * pair nothing declares is refused rather than stored, and a pair a position
 * ALREADY holds that nothing declares any more is left exactly where it is:
 * pruned, not purged. Removing it on the module's way out would silently
 * rewrite what an administrator granted.
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
        private ConcernCatalogue $catalogue,
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
     * @param list<string> $pairs each written `<concern>.<verb>`
     *
     * @throws UnknownGrantException when a pair nothing installed declares is granted
     */
    public function setGrants(Position $position, array $pairs): void
    {
        $position->setGrantValues($pairs, $this->catalogue->pairs());

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
