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
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;

/**
 * SINCE WHEN A POST HAS STOOD EMPTY, kept true as people come and go.
 *
 * THE DAY CANNOT BE RECOVERED AFTERWARDS. "Ranger, unfilled since 15 May"
 * is not a query anybody can write in September: the position knows who
 * holds it now, and nothing anywhere remembers when the last person
 * stopped. So it is written at the moment it becomes true, by whoever
 * changes who holds what.
 *
 * ONE PLACE, CALLED FROM EVERY DOOR. Seating somebody, unseating them,
 * deactivating them and bringing them back all change whether a post is
 * held; four copies of this rule would be four answers, and the one that
 * disagreed would be the one nobody noticed.
 *
 * NULL IS TWO THINGS AND THE POST SAYS WHICH. A held post has no vacancy
 * date because it is not vacant; an empty post with no date is one that
 * was already empty before the day was recorded, and every surface reads
 * that as "unknown" rather than starting the clock at the upgrade.
 */
final readonly class PositionVacancy
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $users,
    ) {
    }

    /**
     * BRING THE DATE INTO LINE WITH WHO HOLDS THE POST, and flush nothing:
     * the caller is in the middle of its own write and owns the flush.
     */
    public function refresh(?Position $position, ?\DateTimeImmutable $now = null): void
    {
        if (null === $position) {
            return;
        }

        if ($this->users->countActiveHoldingAnyPosition([$position]) > 0) {
            $position->setVacantSince(null);

            return;
        }

        // ALREADY DATED IS LEFT ALONE: a post that has been empty since May
        // is not newly empty because somebody edited the roster today.
        if (null === $position->getVacantSince()) {
            $position->setVacantSince($now ?? new \DateTimeImmutable());
        }
    }

    /**
     * The same, for a post whose holders have just changed, saved.
     *
     * NO POST, NOTHING TO SAVE. Somebody seated nowhere leaves no post
     * emptier than it was, so this is not a write at all.
     */
    public function refreshAndFlush(?Position $position, ?\DateTimeImmutable $now = null): void
    {
        if (null === $position) {
            return;
        }

        $this->refresh($position, $now);
        $this->entityManager->flush();
    }

    /**
     * HOW MANY DAYS IT HAS STOOD EMPTY — null where the post is held, and
     * null where nobody wrote the day down. The two read differently on a
     * page and neither is a number.
     */
    public function daysVacant(Position $position, ?\DateTimeImmutable $now = null): ?int
    {
        $since = $position->getVacantSince();
        if (null === $since) {
            return null;
        }

        return (int) $since->diff($now ?? new \DateTimeImmutable())->days;
    }

    /**
     * HOW MANY OF THESE POSTS HAVE STOOD EMPTY LONGER THAN THE
     * INSTALLATION ALLOWS. A post whose day nobody wrote down is not
     * counted: it may well be the oldest vacancy there is, and counting
     * it would be a guess either way.
     *
     * @param list<Position> $positions
     */
    public function overThreshold(array $positions, int $days, ?\DateTimeImmutable $now = null): int
    {
        $over = 0;
        foreach ($positions as $position) {
            $stood = $this->daysVacant($position, $now);
            if (null !== $stood && $stood >= $days) {
                ++$over;
            }
        }

        return $over;
    }
}
