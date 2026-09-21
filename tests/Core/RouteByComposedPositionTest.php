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

namespace Uhifadhi\Core\Tests\Core;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\RegistryBundle\Entity\Module;
use Uhifadhi\Bundle\TeamBundle\Access\ConcernCatalogue;
use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Entity\Placement;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Core\Tests\Application\Kernel;

/**
 * EVERY ROUTE, AGAINST POSITIONS COMPOSED FROM THE DECLARATIONS.
 *
 * The third of the four proofs, and the one the other three cannot stand in
 * for. A route can name a pair, a door can name the same pair, and every
 * pair can be declared — and the route can still be gated on the WRONG one.
 * Only asking it twice, as somebody holding exactly that pair and as
 * somebody holding every other pair there is, tells those apart.
 *
 * THE POSITIONS ARE COMPOSED, NOT PERSONAS. There is no built-in Ranger or
 * Warden whose powers the code assumes; for each route the suite writes two
 * positions out of the catalogue itself. A persona would only ever prove
 * what somebody once believed about it.
 *
 * WHAT COUNTS AS OPEN. A gate refuses with 403, so anything that is not a
 * 403 is the gate letting the request past — a 404 for a fixture that does
 * not exist, a redirect, a 200. This suite is about the GATE and deliberately
 * not about what the page then does; that is every other test here.
 *
 * GET ROUTES ONLY, and it is a real limit rather than an oversight: a POST
 * needs a CSRF token and a body the gate never sees, so driving one would be
 * testing the form. The writes have their own suites, and the router walk
 * ({@see EveryRouteNamesItsPairTest}) covers them for what this one is about.
 *
 * ONE SCHEMA, MANY ROUTES. Each check is a loop rather than a data-provider
 * row because the fixture is the same one every time and rebuilding the
 * database per route would cost minutes to learn nothing extra. The failure
 * message names every route that broke, which is what a data provider would
 * have bought.
 */
#[CoversNothing]
final class RouteByComposedPositionTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->catchExceptions(true);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->em = $em;

        $this->em->getConnection()->executeStatement('CREATE EXTENSION IF NOT EXISTS postgis');

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        new SchemaTool($this->em)->dropSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->em->close();
        parent::tearDown();

        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }

    public function testAPositionHoldingExactlyARoutesPairsOpensThatRoute(): void
    {
        $area = $this->area('Ngorongoro');
        $refused = [];

        foreach ($this->gatedRoutes() as $name => [$path, $pairs]) {
            $this->signIn($this->composed($pairs));
            $this->client->request('GET', str_replace('{uuid}', (string) $area->getUuidString(), $path));

            if (403 === $this->client->getResponse()->getStatusCode()) {
                $refused[] = $name.' (holds '.implode(' + ', $pairs).')';
            }
        }

        sort($refused);

        self::assertSame([], $refused, \sprintf(
            "These routes refuse a position holding exactly the pairs they name [%s].\n".
            'The route is gated on something other than what it says it enforces.',
            implode(', ', $refused),
        ));
    }

    public function testAPositionHoldingEveryOtherPairIsRefused(): void
    {
        $area = $this->area('Ngorongoro');
        $everything = $this->catalogue()->pairs();
        $opened = [];

        foreach ($this->gatedRoutes() as $name => [$path, $pairs]) {
            $others = array_values(array_filter(
                $everything,
                static fn (string $pair): bool => !\in_array($pair, $pairs, true),
            ));

            $this->signIn($this->composed($others));
            $this->client->request('GET', str_replace('{uuid}', (string) $area->getUuidString(), $path));

            if (403 !== $this->client->getResponse()->getStatusCode()) {
                $opened[] = $name.' (needs '.implode(' + ', $pairs).')';
            }
        }

        sort($opened);

        self::assertSame([], $opened, \sprintf(
            "These routes open for a position holding every declared pair EXCEPT the ones they name [%s].\n".
            'Either the gate is missing, or it names a pair that somebody who should not reach this page holds.',
            implode(', ', $opened),
        ));
    }

    /**
     * THE FIRST DIMENSION FAILING ALONE: the right position, the wrong
     * ground. The worked example in the ruling, and the shortest way to see
     * the model work.
     *
     * IT IS ASKED OF THE CHECKER WITH THE AREA IN HAND, which is where the
     * model is answered. A route cannot stand in for it today, because no
     * route passes its area to its gate — see
     * {@see testTheAreaScopedRoutesThatDoNotPassTheirAreaToTheGate}, which
     * holds that gap as a named list rather than leaving it to be discovered.
     */
    public function testTheRightPositionInTheWrongAreaIsRefused(): void
    {
        $here = $this->area('Ngorongoro');
        $elsewhere = $this->area('Mbozi');

        $this->signIn($this->composed(['areas.read'], new Placement()->inAreas([$elsewhere])->acrossAllDepartments()));

        self::assertFalse(
            $this->checker()->isGranted('areas.read', $here),
            'Placed at Mbozi and holding areas.read, this person reads Ngorongoro. The second question — does the placement cover the area — is not being asked.',
        );
    }

    /** And it is granted where they ARE placed, so the refusal above is about the ground and nothing else. */
    public function testTheSamePositionIsGrantedInTheAreaItIsPlacedAt(): void
    {
        $here = $this->area('Ngorongoro');

        $this->signIn($this->composed(['areas.read'], new Placement()->inAreas([$here])->acrossAllDepartments()));

        self::assertTrue($this->checker()->isGranted('areas.read', $here));
    }

    /**
     * THE ROUTES THAT ASK THEIR PAIR WITHOUT THE GROUND IT IS ABOUT.
     *
     * `#[IsGranted('areas.read')]` with no `subject:` asks the voter with a
     * NULL subject, and a null subject means "no area in context" — which any
     * placement that reaches some ground at all satisfies. So on these routes
     * the second question is asked and always answered yes: somebody placed
     * only at Mbozi opens Ngorongoro's page.
     *
     * IT IS A GAP AND NOT A DESIGN. Nothing is leaking that was not leaking
     * before — the previous model behaved the same way, for the same reason —
     * but the ruled model says the check uses the position and the placement
     * TOGETHER, and on these routes it uses only the position. Closing it
     * means each of these passing its area: `#[IsGranted('areas.read',
     * subject: 'area')]` on a controller whose signature takes the resolved
     * `AreaInterface $area` rather than a bare `string $uuid`.
     *
     * THE LIST IS HERE SO IT SHRINKS. A new area-scoped route that forgets
     * the subject fails this test rather than joining the gap silently, and
     * every line removed from the list is the gap closing.
     *
     * @return list<string>
     */
    private static function knownToAskWithoutTheirArea(): array
    {
        return [
            'area_departments',
            'area_departments_configure',
            'area_edit',
            'area_module_customize',
            'area_modules',
            'area_settings',
            'area_show',
            'area_stations',
            'area_stations_configure',
            'area_zones',
            'area_zones_configure',
            'area_zones_export',
            'team_department_show',
        ];
    }

    public function testTheAreaScopedRoutesThatDoNotPassTheirAreaToTheGate(): void
    {
        $known = self::knownToAskWithoutTheirArea();
        $here = $this->area('Ngorongoro');
        $this->area('Mbozi');

        $hereUuid = (string) $here->getUuidString();
        $unlisted = [];
        $closed = [];

        foreach ($this->gatedRoutes() as $name => [$path, $pairs]) {
            if (!str_contains($path, '{uuid}')) {
                continue;
            }

            // Re-read the ground each time: the sign-in below reboots the
            // kernel and detaches whatever the last one held.
            $placedAt = $this->area('Mbozi');
            $this->signIn($this->composed($pairs, new Placement()->inAreas([$placedAt])->acrossAllDepartments()));
            $this->client->request('GET', str_replace('{uuid}', $hereUuid, $path));

            $refused = 403 === $this->client->getResponse()->getStatusCode();

            if (!$refused && !\in_array($name, $known, true)) {
                $unlisted[] = $name;
            }

            if ($refused && \in_array($name, $known, true)) {
                $closed[] = $name;
            }
        }

        sort($unlisted);
        sort($closed);

        self::assertSame([], $unlisted, \sprintf(
            "These area-scoped routes ask their pair without the area it is about, and are not on the known list [%s].\n".
            'Pass the area: `#[IsGranted(\'<pair>\', subject: \'area\')]`, with the controller taking the resolved area rather than a bare uuid.',
            implode(', ', $unlisted),
        ));

        self::assertSame([], $closed, \sprintf(
            'These routes now DO pass their area and are still on the known-gap list [%s] — take them off it.',
            implode(', ', $closed),
        ));
    }

    /**
     * THE SECOND DIMENSION FAILING ALONE: the right position, the right area,
     * the wrong department. Amina's row in the ruling — her ground is the
     * whole organization and her departments are two, so a third department's
     * module is not hers to read anywhere.
     *
     * IT IS ASKED OF THE CHECKER RATHER THAN THROUGH A ROUTE, because which
     * route a module's concern gates is that module's business; the core
     * declares no concern belonging to a module, so there is no core route
     * for this to be a request against. The module conformance base
     * ({@see \Uhifadhi\Bundle\TeamBundle\Test\AccessConformanceTestCase}) is
     * where a module holds its own routes to it.
     */
    public function testTheRightPositionInTheRightAreaButTheWrongDepartmentIsRefused(): void
    {
        $pair = $this->aModulePair();
        if (null === $pair) {
            self::markTestSkipped('This installation declares no module-owned concern, so the department dimension has nothing to be about.');
        }

        $module = (string) $this->catalogue()->moduleOf(explode('.', $pair)[0]);
        $area = $this->area('Ngorongoro');
        $runs = $this->department('Ecology', $module);
        $doesNot = $this->department('ICT', null);

        self::assertGreaterThan(0, $runs->getModules()->count(), 'the fixture department has to run the module, or the third question has nothing to refuse for.');

        $person = $this->composed([$pair], new Placement()->acrossTheOrganization()->inDepartments([$doesNot]));
        $this->signIn($person);

        self::assertFalse(
            $this->checker()->isGranted($pair, $area),
            \sprintf('Placed only in ICT, this person holds %s. The third question — does the placement cover the department — is not being asked.', $pair),
        );

        $inIt = $this->composed([$pair], new Placement()->acrossTheOrganization()->inDepartments([$runs]));
        $this->signIn($inIt);

        self::assertTrue(
            $this->checker()->isGranted($pair, $area),
            'and somebody placed in the department that runs it does hold it, so the refusal above is about the department and nothing else.',
        );
    }

    // --- the fixtures -----------------------------------------------------

    /**
     * A position granting exactly these pairs, held by somebody placed — by
     * default across the whole organization and every department, so that a
     * refusal can only have come from the position.
     *
     * @param list<string> $pairs
     */
    private function composed(array $pairs, ?Placement $placement = null): User
    {
        $placement ??= new Placement()->acrossTheOrganization()->acrossAllDepartments();

        $position = new Position()
            ->setName('Composed '.substr(md5(implode('|', $pairs).spl_object_hash($placement)), 0, 10))
            ->setGrantValues($pairs, $this->catalogue()->pairs());
        $this->em->persist($position);
        $this->em->persist($placement);

        $person = new User()
            ->setEmail(bin2hex(random_bytes(8)).'@example.test')
            ->setFirstName('Composed')->setLastName('Holder')
            ->setPassword('x')
            ->setTeamRole(TeamRoleEnum::Staff)
            ->setVerified(true)
            ->setPosition($position)
            ->setPlacement($placement);
        $this->em->persist($person);
        $this->em->flush();

        return $person;
    }

    private function signIn(User $person): void
    {
        $this->client->loginUser($person);
    }

    /**
     * The area of that name, made if it is not there yet.
     *
     * IT IS LOOKED UP EVERY TIME ON PURPOSE. Signing somebody in reboots the
     * client's kernel, which detaches whatever the previous entity manager
     * held — so an area captured before a loop is a DETACHED object by the
     * second iteration, and persisting a placement that points at one makes
     * Doctrine believe it has found a new area. Asking for it by name each
     * time costs one query and removes the whole class of failure.
     */
    private function area(string $name): AreaOfInterest
    {
        $found = $this->em->getRepository(AreaOfInterest::class)->findOneBy(['name' => $name]);
        if ($found instanceof AreaOfInterest) {
            return $found;
        }

        $area = new AreaOfInterest()->setName($name);
        $this->em->persist($area);
        $this->em->flush();

        return $area;
    }

    private function department(string $name, ?string $moduleSlug): Department
    {
        $department = new Department()->setName($name);
        $this->em->persist($department);

        if (null !== $moduleSlug) {
            $module = $this->em->getRepository(Module::class)->findOneBy(['slug' => $moduleSlug]);
            if ($module instanceof Module) {
                $department->attachModule($module);
            }
        }

        $this->em->flush();

        return $department;
    }

    private function catalogue(): ConcernCatalogue
    {
        $catalogue = static::getContainer()->get('test_public.'.ConcernCatalogue::class);
        self::assertInstanceOf(ConcernCatalogue::class, $catalogue);

        return $catalogue;
    }

    private function checker(): AuthorizationCheckerInterface
    {
        $checker = static::getContainer()->get('security.authorization_checker');
        self::assertInstanceOf(AuthorizationCheckerInterface::class, $checker);

        return $checker;
    }

    /** The first declared pair whose concern belongs to a module, or null. */
    private function aModulePair(): ?string
    {
        foreach ($this->catalogue()->pairs() as $pair) {
            if (null !== $this->catalogue()->moduleOf(explode('.', $pair)[0])) {
                return $pair;
            }
        }

        return null;
    }

    /**
     * The gated GET routes, with the pairs each one names.
     *
     * A ROUTE TAKING A PARAMETER THIS SUITE CANNOT INVENT IS LEFT OUT — a 404
     * for a fixture that does not exist would answer before the gate did, and
     * would read as the gate letting the request past. `{uuid}` is the
     * exception, because an area is a fixture this suite does make.
     *
     * @return array<string, array{string, list<string>}>
     */
    private function gatedRoutes(): array
    {
        $router = static::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $table = [];
        foreach ($router->getRouteCollection()->all() as $name => $route) {
            $methods = $route->getMethods();
            if ([] !== $methods && !\in_array('GET', $methods, true)) {
                continue;
            }

            $pairs = GateReader::pairsOn($route);
            if ([] === $pairs) {
                continue;
            }

            $path = $route->getPath();
            if (1 === preg_match('/\{(?!uuid\})\w+\}/', $path)) {
                continue;
            }

            $table[$name] = [$path, $pairs];
        }

        /*
         * A PATH TWO ROUTES CLAIM IS ONE THIS SUITE CANNOT ASK ABOUT. The
         * router answers with whichever matched first, so a request would
         * exercise one route and be reported against the other — and the
         * report would be wrong in the direction that matters, saying a gate
         * is missing when it is simply not the route that answered. `/` is
         * the live case: the installation mounts its own welcome page there
         * and the organization dashboard claims it too.
         */
        $claims = [];
        foreach ($router->getRouteCollection()->all() as $route) {
            $claims[$route->getPath()] = ($claims[$route->getPath()] ?? 0) + 1;
        }

        return array_filter($table, static fn (array $row): bool => 1 === ($claims[$row[0]] ?? 0));
    }
}
