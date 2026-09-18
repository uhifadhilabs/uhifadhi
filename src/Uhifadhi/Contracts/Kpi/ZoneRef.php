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

namespace Uhifadhi\Contracts\Kpi;

/**
 * THE ZONE A PROVIDER IS BEING ASKED ABOUT — its public identifier, the area it
 * subdivides, and what a caption calls it.
 *
 * NOT AN ENTITY, for the reason {@see DepartmentRef} is not one: a zone is the
 * area module's, and a module that type-hinted somebody's class would be a
 * module that cannot be installed without them. What a provider actually needs
 * is an identifier to match its own rows against, the area to narrow the search
 * to, and a name to put in a caption — which is all three of these.
 *
 * THE AREA RIDES ALONG DELIBERATELY. Every module that records anything records
 * it in an area, and almost none of them index by zone; handing the area over
 * lets a provider narrow to the rows it already has an index for and resolve
 * the zone spatially from there, instead of scanning.
 *
 * BOTH IDENTIFIERS ARE THE PUBLISHED UUID STRINGS, never database keys: a
 * module and the core do not share a database schema, and a key is the one
 * thing about a row that is nobody else's business.
 */
final readonly class ZoneRef
{
    public function __construct(
        public string $zoneUuid,
        public string $areaUuid,
        public string $name,
    ) {
        if ('' === trim($name)) {
            throw new \InvalidArgumentException('A zone ref must carry the name a caption prints.');
        }

        if ('' === trim($zoneUuid) || '' === trim($areaUuid)) {
            throw new \InvalidArgumentException('A zone ref names a zone inside an area: both identifiers are required.');
        }
    }
}
