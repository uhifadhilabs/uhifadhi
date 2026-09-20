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

namespace Uhifadhi\Contracts\Settings;

/**
 * ONE MODULE, AS A COLUMN OF THE WHAT-RUNS-WHERE MATRIX.
 *
 * The matrix is read down as much as across: a column with one "on" in it
 * says a module was installed and then used in one place, which is a
 * different fact from a module nobody switched on anywhere.
 */
final readonly class ModuleColumn
{
    /**
     * @param string      $slug        the module's own slug, as it registered it
     * @param string      $label       its name, in its own words
     * @param string|null $package     the composer package it ships in, where that is known
     * @param string|null $version     the version this installation runs, where that is known
     * @param string|null $description what the module says it is about, in its own words
     */
    public function __construct(
        public string $slug,
        public string $label,
        public ?string $package = null,
        public ?string $version = null,
        public ?string $description = null,
    ) {
        if ('' === trim($slug)) {
            throw new \InvalidArgumentException('A module column is keyed by its slug: it cannot be empty.');
        }
    }
}
