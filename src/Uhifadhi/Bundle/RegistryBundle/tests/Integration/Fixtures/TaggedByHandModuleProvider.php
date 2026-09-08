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

use Uhifadhi\Contracts\ModuleProviderInterface;
use Uhifadhi\Contracts\ModuleProviderTrait;

/**
 * A MODULE BUNDLE'S provider: not autoconfigured (a reusable bundle's services
 * never are), so its kernel writes the tag by hand — the other of the registry's
 * two entrances, and the one every installable module actually uses.
 */
final class TaggedByHandModuleProvider implements ModuleProviderInterface
{
    use ModuleProviderTrait;

    public function slug(): string
    {
        return 'ferries';
    }

    public function name(): string
    {
        return 'Ferries';
    }

    public function category(): string
    {
        return 'operations';
    }

    public function base(): bool
    {
        return true;
    }
}
