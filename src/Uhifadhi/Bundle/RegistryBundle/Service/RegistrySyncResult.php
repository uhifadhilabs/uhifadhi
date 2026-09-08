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

namespace Uhifadhi\Bundle\RegistryBundle\Service;

/**
 * What one reconciliation did.
 *
 * `skipped` is the fresh-install answer: the registry tables are not there yet,
 * so nothing was reconciled and nothing went wrong. It is a distinct fact from
 * "reconciled nothing", which is what an installation with no modules reports.
 */
final readonly class RegistrySyncResult
{
    public function __construct(
        public int $modules = 0,
        public int $areaAssignments = 0,
        public bool $skipped = false,
    ) {
    }

    public static function skipped(): self
    {
        return new self(skipped: true);
    }
}
