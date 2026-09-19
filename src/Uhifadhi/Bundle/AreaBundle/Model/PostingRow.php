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

use Uhifadhi\Bundle\AreaBundle\Enum\PostingSource;

/**
 * ONE LINE OF A POSTINGS BOARD — everything the design prints on it, resolved
 * before the template sees it.
 *
 * THE NAME AND THE INITIALS COME FROM THE USER CONTRACT; the role and the
 * department come from a seam whoever owns people answers, and both are null
 * where nobody answered. A board with no such provider draws its lines
 * without those two columns rather than with two empty ones.
 *
 * THE POST IS ON THE ROW because one board is sometimes read grouped: a zone
 * asks for everybody working on its ground in one question, and then has to
 * know which post each of them stands at. A row that did not carry it would
 * make that a query per station.
 *
 * THE SOURCE IS A FACT ABOUT THE ROW, not about the person: which door the
 * posting came in by. Two people reading one posting from two places should
 * be able to tell.
 */
final readonly class PostingRow
{
    public function __construct(
        public string $postingUuid,
        public string $personUuid,
        public string $stationUuid,
        public string $name,
        public string $initials,
        public ?string $role,
        public ?string $department,
        public PostingSource $source,
        public \DateTimeImmutable $since,
        public bool $leader,
    ) {
    }
}
