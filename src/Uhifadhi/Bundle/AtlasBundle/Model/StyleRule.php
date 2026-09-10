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

namespace Uhifadhi\Bundle\AtlasBundle\Model;

use Uhifadhi\Bundle\AtlasBundle\Exception\LayerException;

/**
 * A CONDITION ON A FEATURE'S OWN PROPERTIES, AND THE STYLE IT EARNS.
 *
 *     StyleRule::when('status', 'closed')->fill(false)
 *     StyleRule::when('severity', ['high', 'critical'])->dashArray('4 3')
 *
 * This is the whole of what replaced a module's map controller. A module used
 * to hand Leaflet a `style(feature)` function and, with it, an opinion about
 * imagery, chrome and fullscreen; what it actually needed to say was "a closed
 * case is hollow" — which is a property name, a value and a style, and travels
 * as JSON.
 *
 * THE RULES APPLY IN ORDER, each merged over what came before, so two rules
 * that touch the same property resolve the way the module wrote them rather
 * than the way a browser happened to iterate.
 *
 * A rule that names no property, or no value, can never match and would be a
 * layer silently drawn wrong; both are refused where the module wrote them.
 */
final readonly class StyleRule
{
    /**
     * @param string                      $property the feature property the condition reads
     * @param list<bool|float|int|string> $values   the values that satisfy it — any one is a match
     * @param LayerStyle                  $style    what a matching feature is drawn with
     */
    private function __construct(
        public string $property,
        public array $values,
        public LayerStyle $style,
    ) {
    }

    /**
     * @param bool|float|int|string|list<bool|float|int|string> $values one value, or any of several
     */
    public static function when(string $property, bool|float|int|string|array $values): self
    {
        if ('' === trim($property)) {
            throw new LayerException('A style rule names no property: say which of a feature\'s properties the condition reads.');
        }

        $list = \is_array($values) ? $values : [$values];
        if ([] === $list) {
            throw new LayerException(\sprintf('The style rule on "%s" names no value: a rule that matches nothing draws nothing.', $property));
        }

        return new self($property, $list, new LayerStyle());
    }

    public function color(string $color): self
    {
        return $this->wearing($this->style->color($color));
    }

    public function weight(float $weight): self
    {
        return $this->wearing($this->style->weight($weight));
    }

    public function opacity(float $opacity): self
    {
        return $this->wearing($this->style->opacity($opacity));
    }

    public function fill(bool $fill): self
    {
        return $this->wearing($this->style->fill($fill));
    }

    public function fillColor(string $fillColor): self
    {
        return $this->wearing($this->style->fillColor($fillColor));
    }

    public function fillOpacity(float $fillOpacity): self
    {
        return $this->wearing($this->style->fillOpacity($fillOpacity));
    }

    public function dashArray(string $dashArray): self
    {
        return $this->wearing($this->style->dashArray($dashArray));
    }

    public function radius(float $radius): self
    {
        return $this->wearing($this->style->radius($radius));
    }

    public function zIndex(int $zIndex): self
    {
        return $this->wearing($this->style->zIndex($zIndex));
    }

    /** A style stated in one breath, for a rule that changes several things at once. */
    public function wearing(LayerStyle $style): self
    {
        return new self($this->property, $this->values, $style);
    }

    /**
     * @return array{property: string, values: list<bool|float|int|string>, style: array<string, bool|float|int|string>}
     */
    public function toArray(): array
    {
        return [
            'property' => $this->property,
            'values' => $this->values,
            'style' => $this->style->toArray(),
        ];
    }
}
