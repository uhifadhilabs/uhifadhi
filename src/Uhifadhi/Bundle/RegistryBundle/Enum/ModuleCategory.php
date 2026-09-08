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
 * The taxonomy a module is filed under in the catalogue.
 *
 * THE LAST THREE ARE READINGS OF THE AREA — what the ecosystem is doing, what
 * people are doing to it, and what lives in it. They were the whole taxonomy
 * when the product was an observatory, and they had no room at all for the
 * team's OWN work, which ended up filed under "pressure" — a word that reads as
 * human pressure ON the ecosystem, and so says the opposite of what a day's
 * fieldwork is.
 *
 * OPERATIONS is that fourth reading, and after the operational pivot it is the
 * one most modules belong to — so it leads.
 *
 * IT LIVES IN THE REGISTRY BECAUSE THE CATALOGUE COLUMN DOES. A category is not a
 * module's to define (a provider hands over a string, which the registry coerces)
 * and not the host's either (the host would be defining the enum its own
 * catalogue table stores). It is the vocabulary of the catalogue, and the
 * catalogue is here.
 */
enum ModuleCategory: string
{
    case Operations = 'operations';
    case Flux = 'flux';
    case Pressure = 'pressure';
    case Biodiversity = 'biodiversity';

    public function label(): string
    {
        return match ($this) {
            self::Operations => 'Operations',
            self::Flux => 'Flux',
            self::Pressure => 'Pressure',
            self::Biodiversity => 'Biodiversity',
        };
    }
}
