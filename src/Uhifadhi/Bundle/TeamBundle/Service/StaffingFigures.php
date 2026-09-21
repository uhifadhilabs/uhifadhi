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

use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;

/**
 * THE FIGURES EVERY DEPARTMENT HAS WHATEVER IT ATTACHES — seats and people.
 *
 * THE HOST'S OWN, AND THAT IS WHY THEY ARE HERE. A department's modules
 * publish what has happened; how many posts it holds and how many of them
 * somebody stands in is true of a department that attaches nothing at all,
 * so the host answers for it and every department is a row.
 *
 * ONE PLACE, BECAUSE THREE READ IT: the snapshot writes these down each
 * period, the performance page draws them as a matrix, and the band sums
 * them. Three counts of "filled" would be three answers.
 */
final readonly class StaffingFigures
{
    /** The published names, which are also the keys the history is written under. */
    public const string POSITIONS = 'staffing.positions';
    public const string FILLED = 'staffing.filled';
    public const string VACANT = 'staffing.vacant';
    public const string PEOPLE = 'staffing.people';

    public function __construct(
        private DepartmentMembership $membership,
        private UserRepository $users,
    ) {
    }

    /**
     * WHAT THIS DEPARTMENT'S STAFFING IS, RIGHT NOW.
     *
     * A POSITION IS FILLED WHEN SOMEBODY HOLDS IT, and PEOPLE counts the
     * people: a post two rangers share is one filled position and two
     * people, and a page that used one number for both would be wrong
     * about whichever it was not counting.
     *
     * @return array<string, float>
     */
    public function of(Department $department): array
    {
        $filled = 0;
        $people = 0;
        $seats = 0;

        // A DEPARTMENT'S POSITIONS ARE THE ONES ITS MEMBERS HOLD. A position
        // belongs to nobody, so the only honest way to ask which ones a
        // department has is to ask who is placed in it.
        foreach ($this->membership->positionsIn($department) as $position) {
            ++$seats;
            $holding = $this->users->countActiveHoldingAnyPosition([$position]);
            $people += $holding;
            if ($holding > 0) {
                ++$filled;
            }
        }

        return [
            self::POSITIONS => (float) $seats,
            self::FILLED => (float) $filled,
            self::VACANT => (float) ($seats - $filled),
            self::PEOPLE => (float) $people,
        ];
    }
}
