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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web;

use Uhifadhi\Contracts\ModuleProviderInterface;
use Uhifadhi\Contracts\ModuleProviderTrait;

/**
 * A MODULE BUNDLE, STOOD IN FOR.
 *
 * The seam's catalogue is the INTERSECTION of rows in the `module` table and
 * providers currently registered — a row whose bundle was uninstalled keeps its
 * data and leaves the catalogue. So a suite that only inserted rows would get an
 * empty catalogue and prove nothing. This is the other half: the registered
 * provider that makes a row real.
 *
 * IT IS NOT A REAL MODULE AND MUST NOT BECOME ONE. Depending on the patrol
 * module to test the area module's grid would put the fleet's dependency graph
 * in a loop; what this module owes is that it reads the ledger correctly, for
 * whatever is in it.
 */
final readonly class InstallableModule implements ModuleProviderInterface
{
    use ModuleProviderTrait;

    public function __construct(
        private string $slug,
        private string $name,
        private string $category,
        private string $status,
        /** Null is a module whose figures come from nowhere nameable. */
        private ?string $source,
        /** The route the tile links to — null is a module with no pages yet. */
        private ?string $entryRoute = null,
    ) {
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function dataSource(): ?string
    {
        return $this->source;
    }

    public function entryRoute(): ?string
    {
        return $this->entryRoute;
    }
}
