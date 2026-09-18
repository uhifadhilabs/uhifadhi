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
 * WHAT A CONFIRMED IMPORT DID, IN THE WORDS THE SUMMARY PRINTS.
 *
 * BOTH HALVES ARE STATED. An import adds and never overwrites, so a run that
 * left two features out is an ordinary run and not a failure — but a person who
 * uploaded eleven and got nine has to be told which two and why, or they will
 * upload the file again.
 *
 * WHAT ARRIVED IS NOT ALWAYS WHAT WAS PREVIEWED. The plan is made in one
 * request and confirmed in the next, and the area can change in between, so the
 * confirm re-checks every feature and reports what it actually found.
 *
 * It also names the property the names came out of and everything the file
 * carried that was read past, because a file with both `Name` and `layer` in it
 * has two plausible answers and only one was used.
 */
final readonly class ZoneImportResult
{
    /**
     * @param list<string>          $added             the zones created, by name, in file order
     * @param array<string, string> $skipped           the features left out, name to reason
     * @param string                $nameProperty      which property supplied the names
     * @param list<string>          $ignoredProperties every other property the file carried, sorted
     * @param string                $fileName          the name of the file that was read, and not kept
     */
    public function __construct(
        public array $added,
        public array $skipped,
        public string $nameProperty,
        public array $ignoredProperties,
        public string $fileName,
    ) {
    }

    public function count(): int
    {
        return \count($this->added);
    }

    public function skippedCount(): int
    {
        return \count($this->skipped);
    }
}
