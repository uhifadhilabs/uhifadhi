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

namespace Uhifadhi\Bundle\ShellBundle\Model;

/**
 * ONE PART OF THE CORE — a package the core is made of, named and described by
 * its own manifest.
 *
 * NO VERSION, and the absence is the point. A part is not a separate install:
 * it rides with the core, at the core's version, and a version printed beside
 * each one would invite somebody to update a part on its own. The row above
 * them carries the version they all share, and says so once.
 *
 * The description is the part's own `description` field rather than a sentence
 * this bundle keeps about it. A list of hand-written notes is a second place to
 * maintain, and the first thing to go stale.
 */
final class CorePart
{
    /**
     * @param string $name        the composer name this part would answer to on its own
     * @param string $description the one line its own manifest describes it with
     */
    public function __construct(
        public string $name,
        public string $description,
    ) {
    }
}
