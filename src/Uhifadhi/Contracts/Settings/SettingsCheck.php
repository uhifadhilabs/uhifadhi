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
 * ONE THING THAT WAS LOOKED AT, AND WHAT WAS FOUND.
 *
 * The installation screen's Health list is not a status page: it is the short
 * list of questions an operator would otherwise ask by hand — are the
 * migrations run, is the asset map compiled, are the storages answering, is
 * anything behind. Each one is owned by whoever can answer it, and the screen
 * counts the verdicts rather than grading them.
 *
 * THE DETAIL IS EVIDENCE, NOT ADVICE. "core + 3 modules · last ran 18 sep
 * 22:14" says what was measured and when, which is what makes a pass worth
 * printing at all; "you should run your migrations" would be the screen
 * telling somebody their job from a position of not knowing it.
 */
final readonly class SettingsCheck
{
    /**
     * @param string $key    stable, and what a test names this row by
     * @param string $title  the question as a statement — "Migrations are up to date"
     * @param string $detail what was measured, and when
     */
    public function __construct(
        public string $key,
        public CheckVerdict $verdict,
        public string $title,
        public string $detail = '',
    ) {
        if ('' === trim($key)) {
            throw new \InvalidArgumentException('A health check is named by its key: it cannot be empty.');
        }

        if ('' === trim($title)) {
            throw new \InvalidArgumentException(\sprintf('The "%s" check says nothing in the list: it cannot have an empty title.', $key));
        }
    }

    public function passed(): bool
    {
        return CheckVerdict::Pass === $this->verdict;
    }
}
