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

namespace Uhifadhi\Bundle\RegistryBundle\Enum;

/**
 * How far along a module is: {@see Live} — rendering real data — or
 * {@see Template} — scaffolded, with its data path still to come. It drives the
 * status chip a catalogue card shows, and nothing else: a template module is
 * installed, switchable and routable exactly like a live one.
 *
 * Coerced, never trusted (see {@see \Uhifadhi\Bundle\RegistryBundle\Service\ProviderCatalogueMapper}):
 * a provider hands over a string, and anything unrecognised is Live, because a
 * module that is installed is running.
 */
enum ModuleStatus: string
{
    case Live = 'live';
    case Template = 'template';

    public function label(): string
    {
        return match ($this) {
            self::Live => 'live',
            self::Template => 'template',
        };
    }
}
