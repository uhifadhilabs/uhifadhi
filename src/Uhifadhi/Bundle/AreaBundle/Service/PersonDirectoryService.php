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

namespace Uhifadhi\Bundle\AreaBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Uhifadhi\Contracts\Entity\UserInterface;
use Uhifadhi\Contracts\People\PersonDirectoryProviderInterface;
use Uhifadhi\Contracts\People\PersonName;

/**
 * WHO THERE IS TO POST SOMEWHERE, asked of whoever owns people.
 *
 * NOBODY IS NAMED HERE. The chooser on the stations section lists people, and
 * who the people are is not this bundle's to know; the team bundle tags a
 * provider and this reads the tag. An installation with none offers nobody,
 * and the row says so rather than drawing an empty chooser.
 *
 * THE PERSON COMES BACK AS THE PUBLISHED CONTRACT. A posting is written
 * against {@see UserInterface}, which Doctrine resolves to whatever class the
 * installation named — so the chooser hands over an identifier and this is
 * where it becomes the thing a posting can be written against. The directory
 * decides who may be offered; this refuses anybody it did not offer, so a
 * hand-typed identifier cannot post somebody the directory left out.
 */
final readonly class PersonDirectoryService
{
    /** @param iterable<PersonDirectoryProviderInterface> $providers */
    public function __construct(
        private iterable $providers,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * EVERYBODY ANY PROVIDER OFFERS, each of them once.
     *
     * @return list<PersonName>
     */
    public function people(): array
    {
        $people = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->people() as $person) {
                $people[$person->userUuid] ??= $person;
            }
        }

        return array_values($people);
    }

    /** Whether the directories offer this person at all. */
    public function offers(string $userUuid): bool
    {
        foreach ($this->people() as $person) {
            if ($person->userUuid === $userUuid) {
                return true;
            }
        }

        return false;
    }

    /** The person behind an identifier the chooser offered, or null. */
    public function person(string $userUuid): ?UserInterface
    {
        if ('' === trim($userUuid) || !$this->offers($userUuid)) {
            return null;
        }

        /** @var UserInterface|null $person */
        $person = $this->entityManager->getRepository(UserInterface::class)->findOneBy(['uuid' => $userUuid]);

        return $person;
    }
}
