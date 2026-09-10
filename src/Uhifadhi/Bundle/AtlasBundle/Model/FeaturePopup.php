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
 * WHAT A FEATURE SAYS WHEN SOMEBODY CLICKS IT — as property names, never markup.
 *
 * A module states WHICH of a feature's own properties the popup reads; the
 * plate writes the markup and escapes the values. That split is the point: a
 * module that handed over a rendered string would be a module that could put an
 * element on somebody else's map, and a property carrying a stray angle bracket
 * would be a defect nobody sees until it is on a screen.
 *
 * The shape is the one every popup in the product has: a headline, some quiet
 * lines under it, and — where the feature has somewhere to go — one link.
 */
final readonly class FeaturePopup
{
    /**
     * @param string       $title     the property holding the headline
     * @param list<string> $lines     properties printed under it, in order, each on its own line
     * @param string|null  $href      the property holding the url the link points at
     * @param string|null  $linkLabel what the link says; the url itself when it is not stated
     */
    public function __construct(
        public string $title,
        public array $lines = [],
        public ?string $href = null,
        public ?string $linkLabel = null,
    ) {
        if ('' === trim($title)) {
            throw new LayerException('A popup names no title property: say which of a feature\'s properties the headline reads.');
        }

        if (null === $href && null !== $linkLabel) {
            throw new LayerException(\sprintf('The popup labelled "%s" names no href property: a link with nothing behind it goes nowhere.', $linkLabel));
        }
    }

    /** The two properties a popup nearly always has: what it is, and where it goes. */
    public static function of(string $title, string $href): self
    {
        return new self($title, href: $href);
    }

    /**
     * @return array{title: string, lines: list<string>, href: string|null, linkLabel: string|null}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'lines' => $this->lines,
            'href' => $this->href,
            'linkLabel' => $this->linkLabel,
        ];
    }
}
