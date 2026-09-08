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

namespace Uhifadhi\Bundle\ShellBundle\Widget\Registry;

use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetCatalog;

/**
 * WHICH DASHBOARDS THIS INSTALLATION HAS, read live from the container.
 *
 * Live rather than stored, and that is the whole point: an installation's set of
 * dashboards is exactly the set of modules it has installed, so removing a
 * module removes its surface on the next request rather than on the next
 * deploy. There is no table of surfaces to keep in step, and no second place for
 * the two to disagree.
 *
 * A DUPLICATE SURFACE STRING IS A PROGRAMMING ERROR, and it throws here rather
 * than letting two modules quietly share one set of stored layouts. The string
 * is the key every preference row carries; two claimants would each read the
 * other's rows as their own.
 */
final class WidgetSurfaceRegistry
{
    /** @var array<string, WidgetCatalog>|null */
    private ?array $catalogs = null;

    /**
     * @param iterable<WidgetSurfaceInterface> $surfaces every service tagged
     *                                                   {@see WidgetSurfaceInterface::TAG}, in registration order
     */
    public function __construct(private readonly iterable $surfaces)
    {
    }

    /**
     * Every surface string this installation currently claims, in registration
     * order.
     *
     * @return list<string>
     */
    public function surfaces(): array
    {
        return array_keys($this->catalogs());
    }

    public function has(string $surface): bool
    {
        return isset($this->catalogs()[$surface]);
    }

    /**
     * Null rather than a throw: callers ask about surface strings that arrive
     * from a URL or from a stored row, and neither is trusted.
     */
    public function catalog(string $surface): ?WidgetCatalog
    {
        return $this->catalogs()[$surface] ?? null;
    }

    /**
     * @return array<string, WidgetCatalog>
     */
    private function catalogs(): array
    {
        if (null !== $this->catalogs) {
            return $this->catalogs;
        }

        $catalogs = [];
        foreach ($this->surfaces as $surface) {
            $catalog = $surface->catalog();
            if (isset($catalogs[$catalog->surface])) {
                throw new \LogicException(\sprintf('Two widget surfaces both claim "%s". A surface string keys every stored layout, so it belongs to exactly one module.', $catalog->surface));
            }
            $catalogs[$catalog->surface] = $catalog;
        }

        return $this->catalogs = $catalogs;
    }
}
