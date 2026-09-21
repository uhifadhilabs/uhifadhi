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

use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Model\HolderRow;
use Uhifadhi\Bundle\TeamBundle\Model\MemberEvent;

/**
 * WHAT THIS INSTALLATION CAN TRUTHFULLY SAY HAPPENED TO A POSITION, derived
 * from the stored facts that carry a date — the same discipline as
 * {@see MemberHistory} and for the same reason.
 *
 * WHAT IS NOT HERE. Every grant that was ticked and unticked, every rename
 * and every change of the seat count: real events with no stored date. They
 * belong to the planned audit trail, and a line that cannot be placed in a
 * list ordered by when is not put in one with a guess.
 *
 * NEWEST FIRST, AND BOUNDED BY THE CALLER. The card shows the latest few and
 * says how many there are.
 */
final readonly class PositionHistory
{
    /**
     * @param list<HolderRow> $holders everybody standing in it, with the day they were given it
     *
     * @return list<MemberEvent>
     */
    public function of(Position $position, array $holders = []): array
    {
        $events = [];

        foreach ($holders as $holder) {
            if (null === $holder->since) {
                continue;
            }

            $events[] = new MemberEvent(
                \sprintf('%s given this position', $holder->name),
                $holder->where,
                $holder->since,
            );
        }

        $retired = $position->getRetiredAt();
        if (null !== $retired) {
            $events[] = new MemberEvent('Retired', 'record kept · reversible', $retired);
        }

        $created = $position->getCreatedAt();
        if (null !== $created) {
            $events[] = new MemberEvent(
                'Position created',
                'empty from the day it was written',
                $created,
            );
        }

        usort($events, static fn (MemberEvent $a, MemberEvent $b): int => $b->when <=> $a->when);

        return $events;
    }
}
