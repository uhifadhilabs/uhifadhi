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

use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Model\MemberEvent;
use Uhifadhi\Contracts\People\PersonPosting;

/**
 * WHAT THIS INSTALLATION CAN TRUTHFULLY SAY HAPPENED TO ONE PERSON.
 *
 * DERIVED, NOT LOGGED. There is no audit trail in this release, so the history
 * is read off the stored facts that carry a date: when the account was made,
 * when the invitation went out and who sent it, when each posting began, and
 * when the account was switched off. A card that invented the rest would be a
 * card nobody could act on.
 *
 * WHAT IS THEREFORE ABSENT, and will arrive with the audit trail rather than
 * with a guess: the position changes, the tier changes and the field edits.
 * They have no stored date, and a line without one cannot be placed in a list
 * ordered by when.
 *
 * NEWEST FIRST, AND BOUNDED. The card shows the latest few and says how many
 * there are; it never grows to the data and never scrolls inside itself.
 */
final readonly class MemberHistory
{
    /**
     * EVERY LINE THE MODEL CAN DATE, newest first.
     *
     * @param list<PersonPosting> $postings where this person works, from the seam
     *
     * @return list<MemberEvent>
     */
    public function of(User $person, array $postings = []): array
    {
        $events = [];

        foreach ($postings as $posting) {
            $events[] = new MemberEvent(
                title: \sprintf('Posted to %s', $posting->stationName),
                note: implode(' · ', array_filter([
                    $posting->areaName,
                    $posting->leader ? 'leads there' : null,
                ])),
                when: $posting->since,
            );
        }

        $invited = $person->getInvitedAt();
        if (null !== $invited) {
            $events[] = new MemberEvent(
                'Invited',
                null === $person->getInvitedBy() ? 'by an administrator' : \sprintf('by %s', $person->getInvitedBy()->getFullName()),
                $invited,
            );
        }

        $disabled = $person->getDisabledAt();
        if (null !== $disabled) {
            $events[] = new MemberEvent('Deactivated', 'record kept · work keeps its author', $disabled);
        }

        $created = $person->getCreatedAt();
        if (null !== $created) {
            $events[] = new MemberEvent(
                'Account created',
                /*
                 * A NULL `invitedAt` IS MEANINGFUL: it says the account was
                 * made with a password handed over rather than by invitation,
                 * which are the two ways in and the reason both are drawn.
                 */
                null === $invited ? 'with a password, handed over' : 'by invitation',
                $created,
            );
        }

        usort($events, static fn (MemberEvent $a, MemberEvent $b): int => $b->when <=> $a->when);

        return $events;
    }
}
