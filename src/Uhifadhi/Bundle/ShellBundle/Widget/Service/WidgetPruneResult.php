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

namespace Uhifadhi\Bundle\ShellBundle\Widget\Service;

/**
 * What one prune removed, and from where.
 *
 * The surfaces are carried alongside the counts because whoever asked for the
 * prune has to be able to say which dashboards went — a number alone is not an
 * answer anybody can act on.
 */
final readonly class WidgetPruneResult
{
    /**
     * @param list<string> $surfaces
     */
    public function __construct(
        public array $surfaces,
        public int $preferences,
        public int $savedPresets,
    ) {
    }
}
