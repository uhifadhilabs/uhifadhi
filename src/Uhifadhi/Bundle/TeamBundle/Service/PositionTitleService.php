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

use Doctrine\ORM\EntityManagerInterface;
use Uhifadhi\Bundle\TeamBundle\Entity\PositionTitle;
use Uhifadhi\Bundle\TeamBundle\Exception\DuplicatePositionTitleException;
use Uhifadhi\Bundle\TeamBundle\Repository\PositionTitleRepository;

/**
 * THE ONE DOOR A POSITION TITLE IS WRITTEN THROUGH.
 *
 * A TITLE IS A WORD, AND THE RULES ABOUT IT ARE RULES ABOUT WORDS: it is
 * trimmed, it is not empty, and no two titles share one. They live here rather
 * than in the screen that happens to ask, so the day a second screen asks — an
 * importer, a console command, the devkit's demo content — it gets the same
 * answer instead of a second opinion.
 */
final readonly class PositionTitleService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PositionTitleRepository $titles,
    ) {
    }

    /**
     * @throws DuplicatePositionTitleException when the name is already a title
     */
    public function create(string $name, bool $leadsStation): PositionTitle
    {
        $name = trim($name);
        $this->refuseADuplicate($name, null);

        $title = new PositionTitle($name, $leadsStation);
        $this->entityManager->persist($title);
        $this->entityManager->flush();

        return $title;
    }

    /**
     * @throws DuplicatePositionTitleException when another title holds the name
     */
    public function rename(PositionTitle $title, string $name, bool $leadsStation): PositionTitle
    {
        $name = trim($name);
        $this->refuseADuplicate($name, $title);

        $title->setName($name)->setLeadsStation($leadsStation);
        $this->entityManager->flush();

        return $title;
    }

    private function refuseADuplicate(string $name, ?PositionTitle $itself): void
    {
        $clash = $this->titles->findOneByName($name);

        if (null !== $clash && $clash->getId() !== $itself?->getId()) {
            throw new DuplicatePositionTitleException($name);
        }
    }
}
