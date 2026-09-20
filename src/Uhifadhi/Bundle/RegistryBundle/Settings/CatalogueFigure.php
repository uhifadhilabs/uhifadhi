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

namespace Uhifadhi\Bundle\RegistryBundle\Settings;

use Uhifadhi\Bundle\RegistryBundle\Service\ModuleCatalogue;
use Uhifadhi\Contracts\Settings\SettingsFigure;
use Uhifadhi\Contracts\Settings\SettingsFigureSourceInterface;

/**
 * HOW MANY MODULES THIS INSTALLATION RUNS — the catalogue's answer, because
 * the catalogue is the only thing that has one.
 *
 * THE SETTINGS SECTION ASKS AND DOES NOT COUNT. It can read the vendor
 * directory and see packages; what a MODULE is — a thing that registered,
 * declared a slug and can be switched on in an area — is this runtime's
 * definition and nobody else's. A section that counted `uhifadhi/*-module`
 * directories would be counting a naming convention.
 *
 * IT IS THE FIRST CARD OF THE ROW, and it says so with `hot`: the section is
 * about what this installation IS, and what it runs is the first thing about
 * it. The ordering number below puts it there; the flag is what draws it as
 * the card the screen is about.
 *
 * NOTHING IS RENDERED HERE, which is the boundary this bundle keeps: a value
 * object with a label and a number is data, and the screen that draws it is
 * somebody else's.
 *
 * WHETHER ANY OF THEM IS BEHIND IS NOT ASKED, because nothing in an
 * installation can answer it: comparing an installed version against a
 * released one needs a release feed, and subscribing to one is not something
 * a runtime does on a page render. The caption says what it can count.
 */
final readonly class CatalogueFigure implements SettingsFigureSourceInterface
{
    /** The first card of the row. */
    public const int POSITION = 10;

    public function __construct(private ModuleCatalogue $catalogue)
    {
    }

    public function position(): int
    {
        return self::POSITION;
    }

    public function settingsFigures(): iterable
    {
        $count = $this->catalogue->count();

        yield new SettingsFigure(
            'modules',
            'Modules installed',
            (string) $count,
            caption: 0 === $count
                ? 'nothing has registered with the catalogue yet'
                : \sprintf('%d in the catalogue', $count),
            hot: true,
        );
    }
}
