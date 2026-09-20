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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Integration\Brand;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\Fixtures\FaviconHostKernel;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\ShellKernelTestCase;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\TestKernel;

/**
 * THE ADDRESS EVERY BROWSER ASKS FOR, ANSWERED.
 *
 * THE DEFECT THIS PINS. Every page declares `<link rel="icon">` and every
 * browser asks for `/favicon.ico` regardless — before the first page, on a
 * redirect, on an error page, and whenever it has nothing cached. Nothing
 * answered it, so the request became an exception: on a staging
 * installation, the ONLY error group telemetry had ever captured was
 * `NotFoundHttpException … /favicon.ico`. A log whose single entry is noise
 * is a log nobody reads, and the next real error goes with it.
 *
 * THE SAME FILE THE HEAD LINKS, so the tab icon cannot drift from the
 * brandmark and there is one file to replace.
 */
final class FaviconAddressTest extends ShellKernelTestCase
{
    protected static function getKernelClass(): string
    {
        return FaviconHostKernel::class;
    }

    /** Answered, with bytes a browser can draw — not a redirect and not a 404. */
    public function testTheAddressAnswersWithAnImage(): void
    {
        $response = self::bootKernel()->handle(Request::create('/favicon.ico'), catch: false);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('image/svg+xml', $response->headers->get('Content-Type'));
        self::assertTrue($response->isCacheable(), 'The brandmark does not move; the answer says so.');
    }

    /** And the bytes are the mark itself, read off the file this package ships. */
    public function testItServesTheVeryFileTheDocumentLinks(): void
    {
        $response = self::bootKernel()->handle(Request::create('/favicon.ico'), catch: false);
        ob_start();
        $response->sendContent();
        $served = (string) ob_get_clean();

        self::assertStringContainsString('<svg', $served);
        self::assertSame(
            (string) file_get_contents(\dirname(__DIR__, 3).'/public/favicon.svg'),
            $served,
            'One mark, one file: a second copy is a second thing to keep in step.',
        );
    }

    /**
     * IT CLAIMS THE ADDRESS ONLY WHERE THE APPLICATION ASKED. The bare kernel
     * imports nothing, and `/favicon.ico` is its own again — which is how an
     * installation serving the icon from its web server says so.
     */
    public function testWithoutTheImportTheAddressIsTheApplicationsOwn(): void
    {
        self::ensureKernelShutdown();
        $bare = new TestKernel('test', true);
        $bare->boot();

        $response = $bare->handle(Request::create('/favicon.ico'), catch: true);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }
}
