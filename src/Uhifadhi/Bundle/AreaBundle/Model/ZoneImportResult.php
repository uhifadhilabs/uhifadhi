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
 * WHAT AN IMPORT DID, IN THE WORDS THE SUMMARY PRINTS.
 *
 * A file is accepted with things left out of it — the KML residue an export
 * carries, the merge fields a layer merge added — and an import that quietly
 * dropped them would leave somebody wondering whether a description or a colour
 * had been kept somewhere. So the summary states BOTH halves: the zones that
 * were made, and the properties that were read past. It also names the property
 * the names came out of, because a file with both `Name` and `layer` in it has
 * two plausible answers and only one was used.
 */
final readonly class ZoneImportResult
{
    /**
     * @param list<string> $zoneNames         the zones created, in file order
     * @param string       $nameProperty      which property supplied those names
     * @param list<string> $ignoredProperties every other property the file carried, sorted
     * @param string       $fileName          the name of the file that was read, and not kept
     */
    public function __construct(
        public array $zoneNames,
        public string $nameProperty,
        public array $ignoredProperties,
        public string $fileName,
    ) {
    }

    public function count(): int
    {
        return \count($this->zoneNames);
    }
}
