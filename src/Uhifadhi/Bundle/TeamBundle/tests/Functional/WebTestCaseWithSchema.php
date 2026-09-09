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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Area\HostArea;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\TestKernel;

/**
 * A browser and a real database, with the schema rebuilt per test, plus the
 * sample cast every screen suite needs.
 *
 * THE CAST IS INVENTED AND THE DOMAIN IS `example.test` — the domain the shipped
 * sign-in template's own placeholder uses, so it is provably nobody's. Three of
 * these people are load-bearing and appear in every suite that seeds them:
 * somebody with no position (the model's zero), a position nobody holds, and a
 * Staff member who administers the team because their position carries
 * team.manage.
 */
abstract class WebTestCaseWithSchema extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    /**
     * NAMED IN CODE, not by KERNEL_CLASS. One repository holds several
     * packages, so one env var could only ever name one of their kernels.
     */
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = self::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->em = $em;

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        // EVERY TEST STARTS WITH A FULL RATE-LIMIT BUDGET. The limiters count
        // into a FILESYSTEM pool, as a deployment's do, so a window outlives
        // the process that opened it: two runs inside one minute would share
        // one budget and the second would fail for reasons that have nothing to
        // do with the code under test — every request here comes from the same
        // address, and the address budget is twenty a minute. The pool is
        // CLEARED rather than swapped for an in-memory one, because the
        // throttling suite needs a count that survives the kernel reboot
        // between its own requests; an in-memory store would be wiped by each
        // and the sixth attempt would never be throttled at all.
        $pool = static::getContainer()->get('test_public.rate_limiter_pool');
        \assert($pool instanceof CacheItemPoolInterface);
        $pool->clear();
    }

    protected function tearDown(): void
    {
        $this->em->close();
        parent::tearDown();

        // The debug error handler is registered during the test and never
        // popped; PHPUnit flags that as risky. Pop whatever is left.
        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }

    protected function person(string $first, string $last, TeamRoleEnum $tier = TeamRoleEnum::Staff): User
    {
        $user = (new User())
            ->setEmail(strtolower($first[0].'.'.$last).'@example.test')
            ->setFirstName($first)->setLastName($last)->setPassword('x')
            ->setTeamRole($tier)->setVerified(true);
        $this->em->persist($user);

        return $user;
    }

    protected function department(string $name): Department
    {
        $department = (new Department())->setName($name);
        $this->em->persist($department);

        return $department;
    }

    /**
     * A host area, played by the integration fixture the kernel resolves
     * {@see \Uhifadhi\Contracts\Entity\AreaInterface} to — the area an
     * area-level department is confined to.
     */
    protected function area(string $name): HostArea
    {
        $area = (new HostArea())->setName($name);
        $this->em->persist($area);

        return $area;
    }

    /** A department confined to one area — area-level, the scope derived from it. */
    protected function areaDepartment(string $name, HostArea $area): Department
    {
        $department = (new Department())->setName($name)->setArea($area);
        $this->em->persist($department);

        return $department;
    }

    /** @param list<string> $permissions */
    protected function position(string $name, ?Department $department, array $permissions = []): Position
    {
        $position = (new Position())->setName($name)->setDepartment($department);
        $position->setPermissionValues(
            $permissions,
            [...array_map(static fn (PermissionEnum $p): string => $p->value, PermissionEnum::all()), 'surveys.record'],
        );
        $this->em->persist($position);

        return $position;
    }

    /** Somebody who can reach every gated screen, so a suite can get in. */
    protected function administrator(): User
    {
        $naomi = $this->person('Naomi', 'Kileo', TeamRoleEnum::SuperAdmin);
        $this->em->flush();
        $this->client->loginUser($naomi);

        return $naomi;
    }

    /** Pull one CSRF token out of a rendered page, so a POST test posts a real one. */
    protected function tokenFrom(string $url, string $selector = 'input[name="_token"]'): string
    {
        $crawler = $this->client->request('GET', $url);

        return (string) $crawler->filter($selector)->first()->attr('value');
    }
}
