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
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Exception\MissingScopeChangeReasonException;
use Uhifadhi\Bundle\TeamBundle\Exception\NameNotUniqueException;
use Uhifadhi\Contracts\Entity\AreaInterface;

/**
 * THE ORG CHART'S WRITES — every way a department is shaped.
 *
 * A DEPARTMENT CARRIES A SCOPE. It is either AREA-LEVEL, confined to one area,
 * or ORG-LEVEL, spanning every area, and the scope is derived from the nullable
 * area on the entity rather than stored twice. A name is unique WITHIN its
 * scope and nowhere else: two areas may each run an Anti-Poaching unit, and the
 * organisation-wide ones each stand alone.
 *
 * A DEPARTMENT GRANTS NOTHING DIRECTLY. Filing a position into one changes
 * where its work is read; confining one to an area changes where its people's
 * authority reaches; neither is itself a grant. Capability arrives through a
 * position's permissions.
 *
 * CHANGING A SCOPE IS AUDITED, BOTH DIRECTIONS, and the entity is what records
 * it: {@see Department::changeScopeTo()} is the one door, it refuses a change
 * with no reason, and it appends the transition. This only supplies who and why.
 *
 * A DEPARTMENT DEACTIVATES, IT NEVER DELETES. Winding one down flips a flag: the
 * register draws it greyed, the pickers drop it, its scope history and filed
 * positions are untouched, and reactivating is one call. How much is filed under
 * it INFORMS the sentence a screen prints and never guards the write.
 *
 * IT DECIDES NOTHING ABOUT WHO IS ASKING — that is an area-scope question about
 * the signed-in session ({@see \Uhifadhi\Bundle\TeamBundle\Security\AreaAuthority}),
 * settled by the screen before it calls in here.
 */
final readonly class DepartmentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * An area makes it area-level; its absence makes it organisation-wide.
     *
     * @throws NameNotUniqueException when that scope already runs a department of the name
     */
    public function create(string $name, ?AreaInterface $area): Department
    {
        $department = new Department()->setName($name);
        if (null !== $area) {
            $department->setArea($area);
        }

        $this->entityManager->persist($department);
        $this->flush($name);

        return $department;
    }

    /**
     * Every position it owns is named after it, so all of them read differently
     * afterwards.
     *
     * @throws NameNotUniqueException when that scope already runs a department of the name
     */
    public function rename(Department $department, string $name): void
    {
        $department->setName($name);

        $this->flush($name);
    }

    /**
     * CONFINE TO AN AREA, OR PROMOTE TO ORG-WIDE — the one act that moves the
     * authority of everyone filed under the department, so the reason is
     * required and the transition is recorded.
     *
     * @throws MissingScopeChangeReasonException when no reason was given
     * @throws NameNotUniqueException            when the destination area already runs a department of the name
     */
    public function changeScope(Department $department, ?AreaInterface $area, ?User $by, string $reason): void
    {
        $department->changeScopeTo($area, $by, $reason);

        $this->flush((string) $department->getName());
    }

    public function deactivate(Department $department): void
    {
        $department->deactivate();
        $this->entityManager->flush();
    }

    public function reactivate(Department $department): void
    {
        $department->reactivate();
        $this->entityManager->flush();
    }

    /**
     * The unique index within a scope is what actually refuses a repeated name.
     * Carried out of the storage layer so a caller catches a fact about the org
     * chart and words it with the scope in it — which it has to, because the
     * same name in another area is fine.
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
