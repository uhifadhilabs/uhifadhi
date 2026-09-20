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

namespace Uhifadhi\Bundle\ShellBundle\Controller;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/favicon.ico`, ANSWERED.
 *
 * WHY A ROUTE EXISTS FOR A FILE THE DOCUMENT ALREADY LINKS. Every page in the
 * product declares `<link rel="icon">` and every browser asks for
 * `/favicon.ico` anyway — before the first page, on a redirect, on an error
 * page, and whenever it has nothing cached. Nothing answered, so an
 * installation's error log filled with one exception: on staging, the only
 * error group telemetry had ever captured was `NotFoundHttpException …
 * /favicon.ico`. A log whose single entry is noise is a log nobody reads.
 *
 * THE SAME FILE, NOT A SECOND MARK. It serves the very asset the head links,
 * so the tab icon cannot drift from the brandmark — and there is one file to
 * replace when an installation wants its own.
 *
 * SVG AT AN `.ico` ADDRESS, DELIBERATELY. The extension is a convention from
 * a format nobody has shipped in years; what decides how a browser reads the
 * bytes is the content type, and one SVG is every size in both palettes. A
 * second, rasterised copy would be a second thing to keep in step.
 *
 * NOT A REDIRECT TO THE DIGESTED ASSET. A redirect is a second round trip for
 * every browser that asks, and the ones that ask are exactly the ones that
 * have nothing cached.
 *
 * A PRESENTATION CONTROLLER, the only kind this bundle has: it reads a file
 * this package ships and nothing else. Reachable only through the
 * application's import, like every other address here.
 */
final readonly class FaviconController
{
    /** Named so `debug:router` says what answers the address every browser asks for. */
    public const string ROUTE = 'favicon';

    /** Long, because the bytes are the brandmark and the brandmark does not move. */
    private const int CACHE_SECONDS = 604800;

    public function __construct(private string $file)
    {
    }

    public function __invoke(): Response
    {
        $response = new BinaryFileResponse($this->file, headers: ['Content-Type' => 'image/svg+xml']);
        $response->setPublic();
        $response->setMaxAge(self::CACHE_SECONDS);

        return $response;
    }
}
