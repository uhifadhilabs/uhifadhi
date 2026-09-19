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

namespace Uhifadhi\Bundle\ShellBundle\Service;

use Uhifadhi\Bundle\ShellBundle\Contract\StylesheetSourceInterface;

/**
 * THE SHEETS EVERY PAGE LINKS BECAUSE A COMPONENT MIGHT BE DRAWN ON IT.
 *
 * Collected in the head, before any of the body is rendered, because
 * that is the only place a stylesheet may be linked — which is the whole
 * reason a component cannot bring its own.
 *
 * ONCE EACH, IN THE ORDER THE TAG DECLARED. Two packages naming one
 * sheet is not an error and is not two links; the priority on the tag
 * decides which package's rules load first, and a package that cares
 * says so rather than hoping the container built it early.
 */
final readonly class Stylesheets
{
    /** @param iterable<StylesheetSourceInterface> $sources the tagged contributors, in priority order */
    public function __construct(private iterable $sources)
    {
    }

    /** @return list<string> */
    public function all(): array
    {
        $sheets = [];
        foreach ($this->sources as $source) {
            foreach ($source->stylesheets() as $sheet) {
                if ('' !== $sheet && !\in_array($sheet, $sheets, true)) {
                    $sheets[] = $sheet;
                }
            }
        }

        return $sheets;
    }
}
