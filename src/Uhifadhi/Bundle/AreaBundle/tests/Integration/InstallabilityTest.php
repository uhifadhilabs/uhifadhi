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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * FROM `composer require` TO TABLES, WITH NO DOCTRINE EDIT — the claim this
 * module exists to make, pinned so the documentation cannot quietly become a
 * lie.
 *
 * The seam's own `InstallabilityTest` pins the other side of the same fact: the
 * seam alone boots and CANNOT be given a schema, because its per-area row has a
 * NOT NULL foreign key to an area it does not define. Until this module existed
 * an installation closed that by hand — a placeholder class it wrote itself and
 * a `resolve_target_entities` line it uncommented — and a bare installation that
 * had not done both reached `doctrine:migrations:diff` and stopped.
 *
 * These tests are that gap, closed. The kernel writes no mappings block and no
 * resolution; adding this bundle is the whole of it.
 */
final class InstallabilityTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * THE ONE THAT USED TO BE IMPOSSIBLE. Every table the installation needs is
     * in the create-schema SQL — the seam's two and this module's one — from a
     * kernel whose doctrine config names neither an area class nor a mapping.
     */
    public function testTheSchemaBuildsWithNoDoctrineConfigurationAtAll(): void
    {
        self::bootKernel();

        $em = $this->entityManager();
        $sql = implode("\n", new SchemaTool($em)->getCreateSchemaSql(
            $em->getMetadataFactory()->getAllMetadata(),
        ));

        self::assertStringContainsString('CREATE TABLE area_of_interest', $sql);
        self::assertStringContainsString('CREATE TABLE module', $sql);
        self::assertStringContainsString('CREATE TABLE area_module', $sql);
    }

    /**
     * THE BOUNDARY COLUMNS ARE NULLABLE, so an area can be created from its
     * identity alone and get its edge later. This module ships no migrations —
     * the installation owns its history — so this is the bundle's own proof that
     * the schema it emits is the widening: the entity declares `geom` and
     * `source` nullable, and the create-schema SQL an installation diffs against
     * carries them as DEFAULT NULL rather than NOT NULL. An installation that
     * already has areas widens with a plain `ALTER … DROP NOT NULL`, which is
     * safe because every existing row has a boundary.
     */
    public function testTheBoundaryColumnsAreNullableSoAnAreaNeedsNoEdgeAtBirth(): void
    {
        self::bootKernel();

        $em = $this->entityManager();
        $meta = $em->getClassMetadata(\Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest::class);

        self::assertTrue($meta->getFieldMapping('geom')->nullable, 'the boundary is optional at creation');
        self::assertTrue($meta->getFieldMapping('source')->nullable, 'provenance is a fact about a boundary that may not exist yet');

        // The area_of_interest CREATE statement alone — the zone table keeps its
        // geom NOT NULL, because a zone without a shape is not a zone.
        $statements = new SchemaTool($em)->getCreateSchemaSql($em->getMetadataFactory()->getAllMetadata());
        $create = array_values(array_filter(
            $statements,
            static fn (string $s): bool => str_contains($s, 'CREATE TABLE area_of_interest'),
        ))[0] ?? '';

        self::assertStringContainsString('geom geometry(MULTIPOLYGON,4326) DEFAULT NULL', $create);
        self::assertStringNotContainsString('geom geometry(MULTIPOLYGON,4326) NOT NULL', $create);
    }

    /**
     * And the per-area row really points AT an area — the foreign key exists,
     * which is the whole reason the seam refused to build a schema without one.
     */
    public function testThePerAreaLedgerIsWiredToTheArea(): void
    {
        self::bootKernel();

        $em = $this->entityManager();
        $sql = implode("\n", new SchemaTool($em)->getCreateSchemaSql(
            $em->getMetadataFactory()->getAllMetadata(),
        ));

        self::assertStringContainsString('REFERENCES area_of_interest (id)', $sql);
    }

    /**
     * THE SEED'S PRECONDITION, stated as its own assertion because the failure
     * is silent rather than loud: `seam:catalogue:seed` finds every area by
     * reading the resolved target class off the association, and treats an
     * unresolved interface as "no areas to backfill". An installation in that
     * state gets a catalogue and no per-area rows, with a success message.
     */
    public function testTheSeedCanDiscoverAreasBecauseTheInterfaceIsResolvedToAClass(): void
    {
        self::bootKernel();

        $target = $this->entityManager()
            ->getClassMetadata(\Uhifadhi\Bundle\RegistryBundle\Entity\AreaModule::class)
            ->getAssociationMapping('area')
            ->targetEntity;

        self::assertNotSame(\Uhifadhi\Contracts\Entity\AreaInterface::class, $target);
        self::assertTrue(class_exists($target));
    }

    /**
     * THIS MODULE SHIPS ENTITIES, NOT MIGRATIONS — the fleet rule. The tables
     * are the bundle's; the migration history belongs to the installation, and a
     * vendor replaying its own versions into it would fight every
     * `doctrine:migrations:diff` the installation ever runs.
     */
    public function testItShipsNoMigrationVersionsOfItsOwn(): void
    {
        self::assertSame([], glob(\dirname(__DIR__, 2).'/migrations/*.php') ?: []);
    }

    private function entityManager(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

    protected function tearDown(): void
    {
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
}
