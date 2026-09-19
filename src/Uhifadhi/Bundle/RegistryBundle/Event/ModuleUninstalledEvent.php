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

namespace Uhifadhi\Bundle\RegistryBundle\Event;

use Uhifadhi\Contracts\Entity\AreaInterface;

/**
 * AN AREA HAS PARKED A MODULE.
 *
 * THE COUNTERPART, AND IT DELETES NOTHING. Parking a module stops the
 * area counting it present; the row stays, and so does everything keyed
 * to it. A listener here is for what must STOP rather than for what must
 * be cleaned up — and nothing in the core takes the second reading: the
 * history a module left behind is what its past periods were, and
 * throwing it away when somebody parks the module for a month would make
 * the year unreadable when they switch it back on.
 *
 * IT IS DISPATCHED EVEN THOUGH NOTHING LISTENS YET, because the pair is
 * the contract: a bundle that acts on an install must be able to act on
 * the reverse, and an event that only exists in one direction teaches
 * module authors to leave the other half out.
 */
final readonly class ModuleUninstalledEvent
{
    public function __construct(
        public AreaInterface $area,
        public string $slug,
    ) {
    }
}
