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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Entity\Zone;

/**
 * A page, a real database, and a viewer holding exactly the permissions the test
 * names. Boots {@see WebKernel} rather than the bare kernel, because a screen
 * needs twig to render in and a firewall to be refused by.
 */
abstract class WebTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;

    /** Everything the platform's catalogue holds for an area — the ordinary admin. */
    protected const array ALL_AREA_PERMISSIONS = ['area.view', 'area.create', 'area.edit', 'area.delete'];

    /** @param list<string> $grants */
    protected function boot(array $grants = self::ALL_AREA_PERMISSIONS): void
    {
        self::ensureKernelShutdown();
        $kernel = new WebKernel($grants);
        $kernel->boot();
        self::$kernel = $kernel;
        self::$booted = true;
        $this->browser = null;

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->em = $em;

        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // THE IDENTITY MAP IS EMPTIED WITH THE TABLES. Booting the kernel warms
        // the cache, and the registry reconciles itself there — reading rows
        // through this very entity manager. Those managed objects outlive the
        // schema this method has just dropped and recreated, so the first area
        // the test persists takes an id the map already holds and Doctrine
        // refuses it. Clearing after a truncate is the documented answer, and
        // the collision message names it.
        $this->em->clear();
    }

    /**
     * A SIGNED-IN VIEWER. The sidebar asks "is anybody looking?" before it asks
     * what they may see — a page can render outside any firewall (an error page,
     * a console-rendered template) and the authorization checker THROWS there
     * rather than answering false. So a test about what a viewer sees has to put
     * somebody behind the glass first.
     */
    protected function signIn(): void
    {
        /** @var TokenStorageInterface $tokens */
        $tokens = static::getContainer()->get('security.token_storage');
        $tokens->setToken(new UsernamePasswordToken(
            new InMemoryUser('ranger', null, ['ROLE_USER']),
            'main',
            ['ROLE_USER'],
        ));
    }

    private ?KernelBrowser $browser = null;

    /**
     * ONE CLIENT PER TEST. `test.client` is defined shared:false, so asking the
     * container twice hands back two browsers and the second has never made the
     * request whose response the assertion wants.
     */
    protected function browser(): KernelBrowser
    {
        if (null === $this->browser) {
            /** @var KernelBrowser $client */
            $client = static::getContainer()->get('test.client');
            $this->browser = $client;
        }

        return $this->browser;
    }

    protected function tearDown(): void
    {
        if (isset($this->em)) {
            // THE GEOMETRY TABLES DO NOT OUTLIVE THIS SUITE. One database
            // carries every package's suite, and a kernel without the PostGIS
            // bundle in it cannot introspect a `geometry` column — so a table
            // left behind here breaks a sibling's suite the moment somebody
            // runs it on its own.
            new SchemaTool($this->em)->dropSchema($this->em->getMetadataFactory()->getAllMetadata());

            $this->em->close();
        }
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

    protected const string A_BOUNDARY = '{"type":"MultiPolygon","coordinates":[[[[-30.0,-3.6],[-29.0,-3.6],[-29.0,-2.8],[-30.0,-2.8],[-30.0,-3.6]]]]}';
    protected const string A_WEST_HALF = '{"type":"MultiPolygon","coordinates":[[[[-30.0,-3.6],[-29.5,-3.6],[-29.5,-2.8],[-30.0,-2.8],[-30.0,-3.6]]]]}';

    protected function anArea(string $name = 'Northern Conservation Reserve', string $source = 'WDPA'): AreaOfInterest
    {
        $area = new AreaOfInterest()->setName($name)->setGeom(self::A_BOUNDARY)->setSource($source);
        $this->em->persist($area);
        $this->em->flush();

        return $area;
    }

    protected function aZone(AreaOfInterest $area, string $name = 'West', string $geom = self::A_WEST_HALF): Zone
    {
        $zone = new Zone()->setArea($area)->setName($name)->setGeom($geom);
        $this->em->persist($zone);
        $this->em->flush();

        return $zone;
    }
}
