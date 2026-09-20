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
 * ONE STEP OF SETTING AN INSTALLATION UP — AND WHERE THIS ONE HAS GOT TO.
 *
 * EVERGREEN, NOT A FIRST RUN, and that is the whole design of it (ruled
 * 2026-09-20). A checklist that is true only on the first day is a page
 * everybody stops opening in week two, and then the one place that says what
 * the platform gives you is a page nobody reads. So a step never says whether
 * the installation has BEGUN: it says WHERE IT IS — "4 registered", "18 of 22
 * posted", "1 of 4 areas running" — which is as worth reading in year three
 * as in week one, because the answer changes.
 *
 * DONE IS NOT FINISHED. A step is `done` when there is nothing outstanding
 * TODAY, and an installation that adds a fifth area is not done again until
 * its zones are imported. That is why `remaining` is a fragment rather than a
 * count: what is left is the step's own to say ("3 areas to go", "4 to post").
 *
 * WHOEVER OWNS THE STEP OWNS THE READING. Adding an area is the area
 * bundle's, composing a position is the team's, and a module that wants a
 * step of its own publishes one — which is what keeps the list from being a
 * hardcoded five that go stale the day the product grows a sixth.
 */
final readonly class SettingsStep
{
    /**
     * @param string      $key       stable, and what a test names this row by
     * @param string      $label     the step, as an instruction — "Import zones"
     * @param string      $gives     what having done it gives, in one fragment
     * @param string      $standing  where THIS installation is — never whether it has begun
     * @param string|null $url       where the standing can be seen, or null
     * @param bool        $done      whether nothing is outstanding today
     * @param string|null $remaining what is left, in the step's own words
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $gives,
        public string $standing,
        public ?string $url = null,
        public bool $done = false,
        public ?string $remaining = null,
    ) {
        if ('' === trim($key)) {
            throw new \InvalidArgumentException('A step is named by its key: it cannot be empty.');
        }

        if ('' === trim($label)) {
            throw new \InvalidArgumentException(\sprintf('The "%s" step says nothing in the list: it cannot have an empty label.', $key));
        }
    }
}
