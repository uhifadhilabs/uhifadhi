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

namespace Uhifadhi\Bundle\TeamBundle\People;

use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;
use Uhifadhi\Contracts\People\PersonDirectoryProviderInterface;
use Uhifadhi\Contracts\People\PersonName;

/**
 * TEAM'S ANSWER TO "WHO IS THERE TO PICK?".
 *
 * IN NAME ORDER, because that is the order somebody looks down a list of
 * people in. The position rides along so two people of the same name can be
 * told apart in a chooser that has room for one line each.
 *
 * ACCOUNTS THAT ARE NOT ACTIVE ARE NOT OFFERED. Somebody who has left should
 * not appear in a list of people to post somewhere; the postings they already
 * hold are history and are read from the posting, not from here.
 */
final readonly class TeamPersonDirectory implements PersonDirectoryProviderInterface
{
    public function __construct(
        private UserRepository $users,
    ) {
    }

    public function people(): array
    {
        $people = [];
        foreach ($this->users->findAllByName() as $user) {
            $uuid = $user->getUuidString();
            $name = $user->getFullName();

            if (null === $uuid || '' === $name || !$user->isActive()) {
                continue;
            }

            $people[] = new PersonName($uuid, $name, $user->getPosition()?->getName());
        }

        return $people;
    }
}
