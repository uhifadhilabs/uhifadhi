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
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;

/**
 * Registering the bundle is the whole installation: the container compiles, the
 * entity is mapped without an installation writing a doctrine mappings block,
 * and the repository is a service.
 */
final class BundleBootTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testTheContainerCompilesAndTheEntityManagerIsThere(): void
    {
        self::bootKernel();

        self::assertInstanceOf(EntityManagerInterface::class, $this->em());
    }

    /**
     * ZERO-CONFIG PERSISTENCE. The kernel writes no `doctrine.orm.mappings` for
     * this bundle (see TestKernel), so if this passes the bundle mapped itself.
     */
    public function testTheBundleMapsItsOwnEntity(): void
    {
        self::bootKernel();

        $metadata = $this->em()->getClassMetadata(AreaOfInterest::class);

        self::assertSame('area_of_interest', $metadata->getTableName());
        self::assertSame('multipolygon', $metadata->getTypeOfField('geom'));
    }

    public function testTheRepositoryIsAService(): void
    {
        self::bootKernel();

        self::assertInstanceOf(
            AreaOfInterestRepository::class,
            self::getContainer()->get('test_public.area.repository'),
        );
    }

    /**
     * AND DOCTRINE HANDS OUT THAT REPOSITORY, not a generic one — which is what
     * `#[ORM\Entity(repositoryClass: …)]` plus the `doctrine.repository_service`
     * tag buy, and what silently degrades to an EntityRepository if either is
     * missing.
     */
    public function testDoctrineResolvesTheEntitysOwnRepository(): void
    {
        self::bootKernel();

        self::assertInstanceOf(
            AreaOfInterestRepository::class,
            $this->em()->getRepository(AreaOfInterest::class),
        );
    }

    private function em(): EntityManagerInterface
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
