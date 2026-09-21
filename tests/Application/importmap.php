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

/*
 * THE THROWAWAY APPLICATION'S IMPORTMAP.
 *
 * The shell's document renders the importmap of whatever application it is
 * installed in, so a specification that renders a PAGE — rather than an API
 * document — needs an application that has one. It carries the entrypoint
 * every Flex-installed application has, `app`, and nothing else: what the core
 * contributes arrives through each bundle's own AssetMapper path, exactly as
 * it does in an installation.
 */

return [
    'app' => ['path' => './assets/app.js', 'entrypoint' => true],
];
