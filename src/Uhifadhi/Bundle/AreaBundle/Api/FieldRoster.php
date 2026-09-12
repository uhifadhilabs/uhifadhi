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

namespace Uhifadhi\Bundle\AreaBundle\Api;

use Doctrine\ORM\EntityManagerInterface;
use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * THE PEOPLE A FIELD CLIENT MAY NAME ON A RECORD — the roster it caches at
 * sign-in so a picker works with no network.
 *
 * IT ASKS THE CONTRACT, NOT AN ACCOUNT CLASS. This bundle owns ground, not
 * people: it holds no user entity, requires no package that does, and must still
 * boot in an installation whose people are its own class. So the question is put
 * to {@see UserInterface} — the published promise about a person — and Doctrine
 * answers it with whichever entity the installation resolved that interface to.
 * The three things a roster entry needs are exactly three methods of that
 * promise, and nothing here reads a column name.
 *
 * ONE LIST, SHARED BY EVERY AREA. An installation is one authority — there is no
 * per-person area assignment to filter a roster on — so "the team" is the same
 * people whichever piece of ground somebody is standing on. Narrowing it later is
 * invisible to a client, because the shape does not change.
 *
 * ORDERED IN PHP, BY THE PRINTED NAME, and deliberately not in SQL: an ORDER BY
 * would have to name a persistence field, which is the account owner's to rename,
 * while the joining rule for a name is already published as a method. A roster is
 * a list of colleagues, so the cost is nothing and the coupling avoided is real.
 *
 * @see vendor/doctrine/orm/src/Tools/ResolveTargetEntityListener.php — `onClassMetadataNotFound()`, which is what makes an interface a repository's subject
 */
final readonly class FieldRoster
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * EVERYBODY, the deactivated included. A record written last season names
     * whoever wrote it, and a roster that dropped the people who have left would
     * make those records unreadable on a handset — the list is who may be NAMED,
     * not who may sign in.
     *
     * @return list<array{id: string, name: string}>
     */
    public function members(): array
    {
        /** @var list<UserInterface> $people */
        $people = $this->entityManager->getRepository(UserInterface::class)->findAll();

        usort($people, static fn (UserInterface $a, UserInterface $b): int => $a->getFullName() <=> $b->getFullName());

        return array_map(
            static fn (UserInterface $person): array => [
                /*
                 * The service number somebody knows themselves by, falling back
                 * to the sign-in address for staff who were never issued one.
                 * This is the SAME rule sign-in and `/api/me` state, and it has
                 * to be: whatever a roster calls somebody is what a record's team
                 * may name, and the two are read by one released client.
                 */
                'id' => $person->getRangerCode() ?? (string) $person->getEmail(),
                'name' => $person->getFullName(),
            ],
            $people,
        );
    }
}
