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
use Uhifadhi\Bundle\TeamBundle\Exception\PositionHeldException;
use Uhifadhi\Bundle\TeamBundle\Exception\SeatsBelowHoldersException;
use Uhifadhi\Bundle\TeamBundle\Exception\UnknownGrantException;
use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;
use Uhifadhi\Contracts\Access\ScopeKind;

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
        private UserRepository $users,
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
     * WHAT A POSITION IS, IN ONE WRITE — the three facts the identity card
     * states, saved together because they are refused together.
     *
     * THE SEAT COUNT CANNOT FALL BELOW THE PEOPLE ALREADY IN IT. Choosing
     * which two of six holders lose their seat is not a decision a product
     * may make, so the write is refused and the floor is named.
     *
     * @param list<ScopeKind> $allowedKinds
     *
     * @throws NameNotUniqueException     when the organization already has the name
     * @throws SeatsBelowHoldersException when the count is below the holders
     * @throws \InvalidArgumentException  when the kinds are not a placement's kinds
     */
    public function setIdentity(Position $position, string $name, ?int $seatCount, array $allowedKinds): void
    {
        $holders = \count($this->users->findActiveHolders($position));
        if (null !== $seatCount && $seatCount < $holders) {
            throw new SeatsBelowHoldersException($position, $seatCount, $holders);
        }

        $position->setName($name)->setSeatCount($seatCount)->setAllowedKinds($allowedKinds);

        $this->flush($name);
    }

    /**
     * CLOSING A POSITION. We do not delete things: the row stays, everything
     * it granted keeps its history, and it can come back.
     *
     * REFUSED WHILE ANYBODY HOLDS IT, and the refusal names the count.
     *
     * @throws PositionHeldException when somebody still holds it
     */
    public function retire(Position $position, ?\DateTimeImmutable $now = null): void
    {
        $holders = \count($this->users->findActiveHolders($position));
        if ($holders > 0) {
            throw new PositionHeldException($position, $holders);
        }

        $position->retire($now ?? new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    /** Reopening a retired position; it is assignable again the moment the stamp is cleared. */
    public function reinstate(Position $position): void
    {
        $position->reinstate();

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
