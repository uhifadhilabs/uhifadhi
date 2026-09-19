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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Uhifadhi\Contracts\People\PersonDirectoryProviderInterface;
use Uhifadhi\Contracts\People\PersonName;

/**
 * THE INSTALLATION'S END OF THE DIRECTORY SEAM, played by the stand-in.
 *
 * Who there is to post somewhere is not this bundle's to know; whoever owns
 * people tags a provider, and in a running installation that is TeamBundle.
 * The kernel that renders these screens plays that end itself — exactly what
 * an installation whose people are its own entity does — so the chooser on
 * the stations section has somebody to offer.
 */
final readonly class HostDirectory implements PersonDirectoryProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function people(): array
    {
        $people = [];
        foreach ($this->entityManager->getRepository(HostUser::class)->findAll() as $person) {
            $uuid = $person->getUuidString();
            $name = $person->getFullName();

            if (null === $uuid || '' === trim($name)) {
                continue;
            }

            $people[] = new PersonName($uuid, $name);
        }

        usort($people, static fn (PersonName $a, PersonName $b): int => strcasecmp($a->name, $b->name));

        return $people;
    }
}
