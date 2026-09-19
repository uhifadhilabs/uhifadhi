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

namespace Uhifadhi\Contracts\Performance;

/**
 * ONE FIGURE, OVER EVERY PIECE OF GROUND IT IS TRUE OF.
 *
 * WHAT THE GROUND IS, IS STATED: a series is over AREAS or over the ZONES
 * of one area, and the two are drawn on different plates. A series that
 * did not say would leave the page matching uuids against two tables to
 * find out.
 *
 * THE UNIT AND THE POLARITY TRAVEL WITH IT for the reason they travel
 * with a matrix column: a plate hues a placing, and hue without polarity
 * is a plate claiming that more is better when the figure is incidents.
 */
final readonly class GeoSeries
{
    public const string OVER_AREAS = 'areas';
    public const string OVER_ZONES = 'zones';

    /**
     * @param list<GeoFigure> $figures
     * @param string          $over     {@see OVER_AREAS} or {@see OVER_ZONES}
     * @param string|null     $areaUuid the area whose zones these are, for a zone series
     */
    public function __construct(
        public string $key,
        public string $title,
        public array $figures,
        public string $over = self::OVER_AREAS,
        public ?string $areaUuid = null,
        public string $unit = '',
        public ColumnPolarity $polarity = ColumnPolarity::None,
        public string $caption = '',
    ) {
    }

    /** A series nobody published a figure in is not drawn at all. */
    public function isEmpty(): bool
    {
        foreach ($this->figures as $figure) {
            if (null !== $figure->value) {
                return false;
            }
        }

        return true;
    }
}
