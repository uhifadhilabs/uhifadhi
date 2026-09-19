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

namespace Uhifadhi\Bundle\AreaBundle\Model;

/**
 * WHAT THE READER ASKED THE STATION REGISTER FOR, read off the query string.
 *
 * THE ZONE IS A FILTER AND NOT A GROUPING. Grouping the register by zone
 * would make the zone the only way in, and a name or a code is how an
 * operator actually arrives at a post. UNZONED is one of the zone's values
 * rather than a state outside the filter: a post on ground no zone covers is
 * ordinary, and looking for it is a thing people do.
 *
 * ACTIVE DEFAULTS TO THE ACTIVE ONES. A deactivated post stays in the
 * register, reachable, reactivatable — behind this filter rather than gone,
 * because a post that closed for a season is not a post that never existed.
 *
 * CHANGING A FILTER GOES BACK TO THE FIRST PAGE. Page four of a list that
 * just became six rows long is an empty screen nobody asked for.
 */
final readonly class StationQuery
{
    public const string ZONE = 'zone';
    public const string ACTIVE = 'active';
    public const string POSTED = 'posted';
    public const string SEARCH = 'q';
    public const string SORT = 'sort';
    public const string PAGE = 'page';

    /** The ground no zone covers, as a value the zone filter can carry. */
    public const string UNZONED = 'unzoned';

    public const string YES = 'yes';
    public const string NO = 'no';
    public const array ANSWERS = [self::YES, self::NO];

    public const string BY_NAME = 'name';
    public const string BY_POSTED = 'posted';
    public const string BY_ZONE = 'zone';
    public const array SORTS = [self::BY_NAME, self::BY_POSTED, self::BY_ZONE];

    public function __construct(
        public ?string $zone = null,
        public ?string $active = self::YES,
        public ?string $posted = null,
        public string $search = '',
        public string $sort = self::BY_NAME,
        public int $page = 1,
    ) {
    }

    /** Whether anything but the resting state is asked for — what the empty state reads. */
    public function isFiltered(): bool
    {
        return null !== $this->zone
            || self::YES !== $this->active
            || null !== $this->posted
            || '' !== $this->search;
    }

    /**
     * The same question with one answer changed, as a query string — how every
     * option in every dropdown is a link rather than a script.
     *
     * @return array<string, string>
     */
    public function with(string $key, ?string $value): array
    {
        $params = array_filter([
            self::ZONE => $this->zone,
            // THE DEFAULT IS NOT IN THE ADDRESS, so a resting register has a
            // clean link; asking for all of them is what the parameter says.
            self::ACTIVE => self::YES === $this->active ? null : ($this->active ?? 'all'),
            self::POSTED => $this->posted,
            self::SEARCH => '' === $this->search ? null : $this->search,
            self::SORT => self::BY_NAME === $this->sort ? null : $this->sort,
            // CHANGING A FILTER GOES BACK TO PAGE ONE: the page is never
            // carried over, and a link to a page sets it itself.
            self::PAGE => null,
        ], static fn (?string $v): bool => null !== $v);

        if (null === $value) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }

        return $params;
    }
}
