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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Integration\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\RouterInterface;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\Fixtures\ImportingHostKernel;
use Uhifadhi\Bundle\ShellBundle\Tests\Integration\TestKernel;

/**
 * CONSENT: THE SHELL CLAIMS NO URL THE APPLICATION HAS NOT ASKED FOR.
 *
 * The shell ships one route — the welcome page's — as an importable RESOURCE.
 * Nothing in the bundle loads it. An application gets `/` by
 * writing one line in its own `config/routes/shell.yaml`, and gets it back by
 * deleting that line. That is the boundary, and it is the whole boundary: it is
 * not "the shell has no routes" (it has one) and not "the shell has no
 * controllers" (it has one) — it is that neither is reachable until an
 * application says so, in a file it owns, in one line it can read.
 *
 * The pair of kernels below IS the assertion. {@see TestKernel} is a host that
 * has not written the line; {@see ImportingHostKernel} is the same host that
 * has. One 404s at `/`; the other serves the welcome page. Nothing else differs.
 */
final class RouteResourceTest extends TestCase
{
    /**
     * THE RESOURCE EXISTS WHERE THE PUBLISHED CONSTANT SAYS IT DOES.
     *
     * The path is written in four places outside this repository — the recipe,
     * the skeleton, the documentation and every installation's routes file — so it is
     * published as a constant here for the same reason the stylesheet's path
     * is: a path typed twice is a path that eventually differs.
     */
    public function testTheBundleShipsTheRouteAsAnImportableResource(): void
    {
        self::assertSame('@ShellBundle/config/routes/welcome.php', ShellBundle::ROUTES);

        self::assertFileExists(
            \dirname(__DIR__, 3).'/config/routes/welcome.php',
            'The shell ships its route as a file an application imports, not as a route it registers.',
        );
    }

    /**
     * IT IS PHP, NOT YAML, and that is a dependency decision rather than a
     * taste: the bundle does not require symfony/yaml (config/services.php says
     * why for the container, and a routing file is the same argument), so a
     * resource written in YAML would be a resource that only loads on hosts
     * that happen to have the component. PHP loads with symfony/routing alone.
     */
    public function testTheResourceIsPhpSoThatNoHostIsMadeToInstallYaml(): void
    {
        $composer = json_decode((string) file_get_contents(\dirname(__DIR__, 3).'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        $require = $composer['require'] ?? [];
        self::assertIsArray($require);

        self::assertArrayHasKey('symfony/routing', $require, 'The bundle ships a routing resource; it says so in its requirements.');
        self::assertArrayNotHasKey('symfony/yaml', $require);
        self::assertStringEndsWith('.php', ShellBundle::ROUTES);
    }

    /**
     * A HOST THAT HAS NOT IMPORTED IT HAS NO SUCH ROUTE. This is the half of
     * the boundary that a "the shell owns no routes" rule used to cover, and it
     * is the half that actually matters: registering the bundle does not put
     * anything at any address.
     */
    public function testRegisteringTheBundleClaimsNothing(): void
    {
        $router = $this->routerOf(TestKernel::class);

        self::assertNull(
            $router->getRouteCollection()->get('welcome'),
            'The bundle registered a route without being asked. Routes load only through the application\'s import.',
        );
        self::assertCount(0, $router->getRouteCollection(), 'A host with only the shell installed has no routes at all.');
    }

    /**
     * AND A HOST THAT HAS IMPORTED IT HAS EXACTLY ONE: `welcome`, at `/`.
     *
     * The name is asserted because an application is told it may replace this
     * page by pointing `/` elsewhere, and `debug:router` is where it looks to
     * see what it is replacing.
     */
    public function testImportingItYieldsTheWelcomeRouteAtTheRoot(): void
    {
        $collection = $this->routerOf(ImportingHostKernel::class)->getRouteCollection();
        $welcome = $collection->get('welcome');

        self::assertNotNull($welcome, 'The imported resource defines the welcome route.');
        self::assertSame('/', $welcome->getPath());
        self::assertCount(1, $collection, 'The resource defines one route and no more: the shell asks for one address.');
    }

    /**
     * THE ROUND TRIP, both ways, through a real kernel: 404 without the line,
     * 200 with it. A route collection can be right while the controller behind
     * it is unreachable — a private service, a class the resolver refuses — so
     * the specification handles a request rather than reading a collection.
     */
    public function testTheImportingHostServesTheWelcomePageAndTheOtherDoesNot(): void
    {
        $response = $this->get(ImportingHostKernel::class, '/');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('A fresh uhifadhi installation', (string) $response->getContent());

        $this->expectException(NotFoundHttpException::class);
        $this->get(TestKernel::class, '/');
    }

    /**
     * NOTHING IN THE BUNDLE'S OWN CODE LOADS THE RESOURCE. The resource is
     * inert until imported, and the way it stays inert is that no extension,
     * no prepend and no compiler pass here ever names it.
     *
     * @param non-empty-string $needle
     */
    #[DataProvider('waysToLoadRoutesBehindTheApplicationsBack')]
    public function testTheBundleNeverLoadsItsOwnRoutes(string $needle): void
    {
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(\dirname(__DIR__, 3), \FilesystemIterator::SKIP_DOTS),
        );

        $root = \dirname(__DIR__, 3);

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }
            $relative = substr($file->getPathname(), \strlen($root) + 1);
            // The suite is not shipped, and it names every one of these on purpose.
            if (str_starts_with($relative, 'tests/')) {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            // The published constant is the path's one definition; it is the
            // string an APPLICATION imports, not a load.
            $code = str_replace("'@ShellBundle/config/routes/welcome.php'", '', $code);
            // The shell GENERATES urls — the brand tile links home — so it holds a
            // reference to the router service. That is consuming routing, not
            // loading routes, and it is the one spelling this needle collides with.
            $code = str_replace("service('router')", '', $code);
            if (str_contains($code, $needle)) {
                $offenders[] = $file->getFilename();
            }
        }

        self::assertSame([], $offenders, \sprintf(
            'The shell reached for "%s". Its routes load through the application\'s import and no other way.',
            $needle,
        ));
    }

    /**
     * @return \Generator<string, array{non-empty-string}>
     */
    public static function waysToLoadRoutesBehindTheApplicationsBack(): \Generator
    {
        // Prepending the host's router configuration — the one prepend that
        // would make the import unnecessary, and therefore the one that would
        // take the choice away. The needle is the CONFIG KEY, quoted: the shell
        // legitimately reads the router service (the brand tile's home link
        // asks it whether a route exists), and that is a read, not a claim.
        yield 'prepended router config' => ["'router'"];
        // Contributing routes through a loader service instead of a file.
        yield 'route loader' => ['RouteLoaderInterface'];
        yield 'routing.loader tag' => ['routing.loader'];
        // Routes declared where no application can see them.
        yield 'route attribute' => ['Routing\\Attribute\\Route'];
    }

    /**
     * @param class-string<Kernel> $kernelClass
     */
    private function routerOf(string $kernelClass): RouterInterface
    {
        $kernel = $this->boot($kernelClass);
        $router = $kernel->getContainer()->get('router');
        \assert($router instanceof RouterInterface);

        return $router;
    }

    /**
     * @param class-string<Kernel> $kernelClass
     */
    private function get(string $kernelClass, string $path): Response
    {
        return $this->boot($kernelClass)->handle(Request::create($path), catch: false);
    }

    /**
     * @param class-string<Kernel> $kernelClass
     */
    private function boot(string $kernelClass): Kernel
    {
        $kernel = new $kernelClass('test', true);
        $kernel->boot();

        return $kernel;
    }
}
