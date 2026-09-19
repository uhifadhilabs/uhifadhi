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

use Uhifadhi\Contracts\Kpi\DepartmentKpi;

/**
 * ONE MODULE'S FIGURE FOR A WHOLE SET OF ZONES, as a card on the zones tab.
 *
 * A COUNT ADDS UP AND A SHARE DOES NOT. Eleven zones' patrols are the area's
 * patrols; eleven zones' "62 % covered" is not the area's coverage, it is
 * eleven percentages — so a total is summed and a share is averaged over the
 * zones that reported one, and the card says which it did. Adding percentages
 * is the kind of number a page states confidently and nobody can reproduce.
 */
final readonly class ZoneFigureCard
{
    public function __construct(
        public string $label,
        public float $value,
        public string $unit,
        public string $module,
        public ?string $route,
        public string $caption,
    ) {
    }

    /** As a plate prints it: thousands separated, shares and counts whole. */
    public function display(): string
    {
        return number_format($this->value, 0, '.', ',');
    }

    /**
     * @param list<DepartmentKpi> $figures every zone's reading of one module's one figure
     */
    public static function of(array $figures, string $caption, ?string $route): ?self
    {
        if ([] === $figures) {
            return null;
        }

        $first = $figures[0];
        $readings = array_values(array_filter(array_map(
            static fn (DepartmentKpi $figure): ?float => $figure->value,
            $figures,
        ), static fn (?float $value): bool => null !== $value));

        if ([] === $readings) {
            return null;
        }

        $share = $first->isShare();

        return new self(
            label: $first->label,
            value: $share ? array_sum($readings) / \count($readings) : array_sum($readings),
            unit: $first->unit,
            module: $first->moduleName,
            route: $route,
            caption: $share
                ? \sprintf('average across %d %s', \count($readings), 1 === \count($readings) ? 'zone' : 'zones')
                : $caption,
        );
    }
}
