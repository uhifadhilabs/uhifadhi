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

namespace Uhifadhi\Bundle\AreaBundle\Model;

use Uhifadhi\Bundle\RegistryBundle\Entity\Module;

/**
 * ONE LINE OF THE MODULE SHOP — a module as the customize screen prints it.
 *
 * A FLAT ROW RATHER THAN THE ENTITY, so the template reads values instead of
 * walking a graph: `row.status` is the registry's word, not
 * `assignment.module.status.value`, and a template that cannot reach through an
 * association cannot trigger a lazy load in the middle of a render.
 *
 * IT CARRIES NO `active` FLAG, on purpose. Which side of the shop a row is on is
 * decided by the LIST it is in — active rows on the left, parked cards on the
 * right — and a flag as well would be the same fact stored twice, free to
 * disagree with the list holding it.
 */
final readonly class ModuleRow
{
    public function __construct(
        public string $slug,
        public string $name,
        /** The registry's own word — "live", "template" — which the chip is styled from. */
        public string $status,
        /** Where the module's figures come from, as a stamp. */
        public string $source,
        /** A pinned module is the area's hub: always on, never reordered, never parked. */
        public bool $pinned = false,
    ) {
    }

    public static function of(Module $module): self
    {
        return new self(
            slug: (string) $module->getSlug(),
            name: (string) $module->getName(),
            status: $module->getStatus()->value,
            source: $module->getDataSource(),
            pinned: $module->isPinned(),
        );
    }
}
