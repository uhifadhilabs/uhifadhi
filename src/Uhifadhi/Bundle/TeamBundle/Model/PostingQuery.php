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

namespace Uhifadhi\Bundle\TeamBundle\Model;

use Symfony\Component\HttpFoundation\Request;

/**
 * WHAT THE READER ASKED THE POSTINGS BOARD FOR, read off the query string.
 *
 * FOUR GROUPED DROPDOWNS AND THE SEARCH — area, zone, rank, whether the
 * station has anybody — which is the house filter bar and not this page's own
 * idea of one.
 *
 * SERVER-SIDE, AND THE ADDRESS IS THE STATE. A filtered board is a link
 * somebody can send and a page a browser can go back to, and it works with no
 * script — which a list filtered in the browser is not.
 */
final readonly class PostingQuery
{
    public const string AREA = 'area';
    public const string ZONE = 'zone';
    public const string RANK = 'rank';
    public const string POSTED = 'posted';
    public const string SEARCH = 'q';

    /**
     * GROUND THAT BELONGS TO NO ZONE IS A PLACE, so it is an ANSWER and not an
     * empty one: zones are presence-driven and a station may stand outside
     * every one of them. An empty value would read as "all zones", which is
     * the opposite of what the reader picked.
     */
    public const string NO_ZONE = 'no zone';

    /** The station filter's two answers: it has people, or it has nobody. */
    public const string STAFFED = 'yes';
    public const string EMPTY = 'no';

    public function __construct(
        public ?string $area = null,
        public ?string $zone = null,
        public ?string $rank = null,
        public ?string $posted = null,
        public string $search = '',
    ) {
    }

    public static function from(Request $request): self
    {
        $posted = trim($request->query->getString(self::POSTED));

        return new self(
            area: self::clean($request->query->getString(self::AREA)),
            zone: self::clean($request->query->getString(self::ZONE)),
            rank: self::clean($request->query->getString(self::RANK)),
            // AN ANSWER THIS BOARD DOES NOT HAVE FILTERS NOTHING, rather than
            // emptying it: a stale link should show the board.
            posted: \in_array($posted, [self::STAFFED, self::EMPTY], true) ? $posted : null,
            search: trim($request->query->getString(self::SEARCH)),
        );
    }

    public function isFiltered(): bool
    {
        return null !== $this->area || null !== $this->zone || null !== $this->rank
            || null !== $this->posted || '' !== $this->search;
    }

    /**
     * The same question with one answer changed — how every option is a link
     * rather than a script.
     *
     * @return array<string, string>
     */
    public function with(string $key, ?string $value): array
    {
        $params = array_filter([
            self::AREA => $this->area,
            self::ZONE => $this->zone,
            self::RANK => $this->rank,
            self::POSTED => $this->posted,
            self::SEARCH => '' === $this->search ? null : $this->search,
        ], static fn (?string $v): bool => null !== $v);

        if (null === $value) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }

        return $params;
    }

    private static function clean(string $value): ?string
    {
        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
