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
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Entity\CheckInStatus;
use Uhifadhi\Bundle\AreaBundle\Repository\CheckInStatusRepository;

/**
 * THE WORDS AN AREA LETS A RANGER CHECK IN WITH.
 *
 * AN AREA THAT HAS CONFIGURED NOTHING STILL WORKS. The four the handset
 * already speaks are written on first use, so an installation that has
 * never opened the roster's settings has a working check-in — and an
 * organisation that wants "On escort" adds it beside them rather than
 * waiting for a release.
 *
 * SEEDED ONCE AND NEVER RE-SEEDED. An area that deactivated "Unfit for
 * duty" on purpose must not find it back tomorrow; the presence of ANY
 * status is what says this area has been here before.
 */
final readonly class CheckInStatusService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CheckInStatusRepository $statuses,
    ) {
    }

    /**
     * WHAT THIS AREA OFFERS, seeding the defaults the first time it is
     * asked.
     *
     * @return list<CheckInStatus>
     */
    public function offeredBy(AreaOfInterest $area): array
    {
        $offered = $this->statuses->activeFor($area);
        if ([] !== $offered) {
            return $offered;
        }

        // NOTHING AT ALL means this area has never been asked; an area
        // that switched every status off has rows and gets none of this.
        if ([] !== $this->statuses->findBy(['area' => $area])) {
            return [];
        }

        return $this->seed($area);
    }

    /**
     * THE FOUR AN AREA STARTS WITH, in the order the design offers them.
     *
     * @return list<CheckInStatus>
     */
    public function seed(AreaOfInterest $area): array
    {
        $seeded = [];
        foreach (CheckInStatus::DEFAULTS as $position => $default) {
            $status = new CheckInStatus()
                ->setArea($area)
                ->setKey($default['key'])
                ->setLabel($default['label'])
                ->setKind($default['kind'])
                ->setPosition($position);

            $this->entityManager->persist($status);
            $seeded[] = $status;
        }

        $this->entityManager->flush();

        return $seeded;
    }
}
