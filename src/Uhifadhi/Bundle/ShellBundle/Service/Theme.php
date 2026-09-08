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

/**
 * WHICH THEME THIS RESPONSE OPENS IN.
 *
 * Almost nothing, and deliberately so: the answer belongs to the browser, not
 * to the server. What a visitor chose is remembered in the browser, and what
 * their operating system prefers is a media query — a server that tried to
 * decide either would be guessing, and would guess wrong on the first frame.
 *
 * So this service carries exactly one fact the browser cannot know: what a
 * visitor who has NEVER chosen should get. The document ships that value into
 * a tiny inline script in the head, which resolves the theme before the first
 * paint. A controller that connects after the first frame is how a visitor who
 * chose dark gets shown a white page first.
 *
 * SYSTEM IS A REAL THIRD ANSWER, not a synonym for light: a visitor who has
 * told their operating system which they want has already answered.
 */
final class Theme
{
    /** The browser key the choice is kept under. Published because the toggle writes it. */
    public const string CHOICE_KEY = 'shell-theme';

    /** @param string $default light, dark, or system */
    public function __construct(private readonly string $default = 'light')
    {
    }

    public function default(): string
    {
        return $this->default;
    }
}
