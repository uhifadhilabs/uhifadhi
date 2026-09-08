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
 * THE STAND-IN HOST'S IMPORTMAP — the one file an application owns that the
 * shell's document now reads. It has the entrypoint every Flex-installed
 * application has, `app`, and nothing else: the shell's claim is that its
 * document renders the importmap of whatever application it is installed in,
 * not that it knows what that application put in it.
 */

return [
    'app' => ['path' => './assets/app.js', 'entrypoint' => true],
];
