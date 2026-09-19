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
 * AN AREA HAS SWITCHED A MODULE ON.
 *
 * WHY ANNOUNCE IT. Installing a module changes what an area can be asked
 * about: its tiles, its attention items, its layers and its performance
 * topic all appear at once. Most surfaces need nothing — they read the
 * ledger every time they draw — but anything that WRITES on the strength
 * of a module being present has to be told, and until now there was
 * nothing to be told by.
 *
 * THE FIRST LISTENER IS THE HISTORY. A module that arrives in September
 * can answer for July, but nothing asked it to; without a nudge its first
 * periods are holes, and a page would show a module with no past for a
 * year. The team bundle hears this and writes down the closed periods the
 * module can compute.
 *
 * IT CARRIES THE FACTS AND NOT THE ROW. The area as the published
 * contract and the module by its slug — the same two things every seam in
 * this platform passes — so a listener in another bundle needs nothing of
 * the registry's mapping to act on it.
 *
 * SWITCHING A PARKED MODULE BACK ON DISPATCHES THIS TOO. From the area's
 * point of view the module is present again, which is the fact listeners
 * care about; whether a row already existed is the registry's own
 * business.
 */
final readonly class ModuleInstalledEvent
{
    public function __construct(
        public AreaInterface $area,
        public string $slug,
    ) {
    }
}
