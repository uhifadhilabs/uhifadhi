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
 * ONE HEADLINE FIGURE ON THE SETTINGS SECTION'S FIRST SCREEN.
 *
 * FOUR TO A ROW, and the row is assembled rather than authored: the section
 * publishes the first card itself — what this installation RUNS — and the
 * three beside it arrive from whoever owns the fact. Areas are the area
 * bundle's, people are the team's, what is kept is storage's. A host that
 * typed the row would have four numbers nobody owns and no way to widen it
 * when a fifth thing became worth counting.
 *
 * A FIGURE STATES ITS OWN ABSENCE rather than reading zero. `$value` is null
 * where the installation cannot answer yet — no storage configured, no
 * release feed to compare versions against — and the card draws an em dash
 * with the caption saying why. Zero is a measurement; null is the absence of
 * one, and a row that rendered them the same would report a quiet
 * installation and a broken one identically.
 *
 * THE CAPTION IS TWO FRAGMENTS, not a sentence, because the second one is
 * ink: `$caption` is what is ordinarily true ("2 current") and `$warning` is
 * the part that wants somebody's eye ("1 behind"). A source that folded them
 * into one string would have to decide the colour, which is the page's.
 */
final readonly class SettingsFigure
{
    /**
     * @param string      $key     stable, and what a test names this card by
     * @param string      $label   what the card is called, in the owner's own words
     * @param string|null $value   the figure as it is drawn, or null for "cannot answer yet"
     * @param string|null $unit    the small ink after the figure — "GB", "of 22"
     * @param string|null $caption the fragment below it, or the reason there is no figure
     * @param string|null $warning the part of that fragment that wants an eye
     * @param bool        $hot     whether this is the card the screen is about
     */
    public function __construct(
        public string $key,
        public string $label,
        public ?string $value,
        public ?string $unit = null,
        public ?string $caption = null,
        public ?string $warning = null,
        public bool $hot = false,
    ) {
        if ('' === trim($key)) {
            throw new \InvalidArgumentException('A settings figure is named by its key: it cannot be empty.');
        }

        if ('' === trim($label)) {
            throw new \InvalidArgumentException(\sprintf('The "%s" figure says nothing on the card: it cannot have an empty label.', $key));
        }
    }

    /** Whether this installation can answer at all — see the class docblock on null. */
    public function isMeasured(): bool
    {
        return null !== $this->value;
    }
}
