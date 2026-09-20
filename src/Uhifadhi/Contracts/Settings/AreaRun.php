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
 * ONE AREA'S ROW OF THE WHAT-RUNS-WHERE MATRIX.
 *
 * AN AREA THAT RUNS NOTHING IS THE POINT OF THE TABLE. A registered area with
 * every module off reports nothing anywhere else in the product — it is
 * absent from every figure and every queue precisely because it has no module
 * to contribute one — so this row is the only place it can be seen at all.
 * That is why the row states its own emptiness rather than being filtered out.
 */
final readonly class AreaRun
{
    /**
     * @param string             $name    the area, as it is named
     * @param array<string,bool> $running module slug to whether it is switched on here
     * @param int|null           $zones   how many zones it has, or null where the
     *                                    installation does not divide areas at all
     */
    public function __construct(
        public string $name,
        public array $running = [],
        public ?int $zones = null,
    ) {
        if ('' === trim($name)) {
            throw new \InvalidArgumentException('An area row is named by its area: the name cannot be empty.');
        }
    }

    /** Whether this area runs any module at all — what "awaiting setup" means. */
    public function isLive(): bool
    {
        return \in_array(true, $this->running, true);
    }

    /** How many of the installation's modules are switched on here. */
    public function moduleCount(): int
    {
        return \count(array_filter($this->running));
    }
}
