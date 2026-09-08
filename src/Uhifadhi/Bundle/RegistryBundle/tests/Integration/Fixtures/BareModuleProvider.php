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

namespace Uhifadhi\Bundle\RegistryBundle\Tests\Integration\Fixtures;

use Uhifadhi\ModuleContracts\ModuleProviderInterface;
use Uhifadhi\ModuleContracts\ModuleProviderTrait;

/**
 * THE SMALLEST HONEST MODULE: slug, name, category, and the trait for
 * everything else. This is what the guide tells a third party to write, so it
 * is what the registry has to carry — a stand-in for a module bundle, deliberately
 * fictional ("sightings") so nothing here names a real one.
 */
final class BareModuleProvider implements ModuleProviderInterface
{
    use ModuleProviderTrait;

    public function slug(): string
    {
        return 'sightings';
    }

    public function name(): string
    {
        return 'Sightings';
    }

    public function category(): string
    {
        return 'biodiversity';
    }
}
