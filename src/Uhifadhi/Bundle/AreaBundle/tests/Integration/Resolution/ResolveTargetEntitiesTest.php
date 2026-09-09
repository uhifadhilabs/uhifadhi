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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Resolution;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\Fixtures\Resolution\HostArea;
use Uhifadhi\Bundle\AreaBundle\Tests\Integration\Fixtures\Resolution\ResolutionKernel;
use Uhifadhi\Bundle\RegistryBundle\Entity\AreaModule;
use Uhifadhi\Contracts\Entity\AreaInterface;

/**
 * WHOEVER KNOWS THE ANSWER STATES THE RESOLUTION — and for the area contract,
 * that is this bundle.
 *
 * The registry owns the record of which modules an area has switched on, and it
 * cannot name an area class: it holds the per-area table for installations whose
 * area model is their own. So it maps the association at
 * `Uhifadhi\Contracts\Entity\AreaInterface` and somebody has to close the loop. For
 * as long as this bundle is installed the answer is not in doubt — it is this
 * bundle's `AreaOfInterest` — so this bundle says so and an installation writes
 * nothing.
 *
 * IT IS NOT A HAND-STEP. A hand-step is for a decision only the installation can
 * make, and this is not one: the line says exactly one thing and has one right
 * value. Left to the installation its cost is real, because forgetting it fails
 * a long way from its cause — the container compiles, the kernel boots, and
 * `doctrine:migrations:diff` stops on "Class 'Uhifadhi\Contracts\Entity\AreaInterface'
 * does not exist" with nothing pointing back at the paragraph that was missed.
 *
 * THE ESCAPE HATCH IS THE CONFIGURATION RULE, not a switch this bundle invented:
 * prepended configuration LOSES to the application's. An installation whose
 * areas are its own entity names that class in its own config and its answer
 * wins, with nothing here to disable first. That property is precisely what
 * makes shipping a default safe rather than presumptuous, so it is tested rather
 * than assumed.
 */
final class ResolveTargetEntitiesTest extends TestCase
{
    /** @param array<class-string, class-string> $override */
    private function metadataFor(array $override, string $variant): EntityManagerInterface
    {
        $kernel = new ResolutionKernel($override, $variant);
        $kernel->boot();

        /** @var EntityManagerInterface $em */
        $em = $kernel->getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }

    /**
     * AN INSTALLATION THAT WRITES NOTHING GETS THE RIGHT ANSWER. This is the
     * whole ruling: the association the registry declared against its contract
     * resolves to this bundle's area, with no doctrine edit anywhere.
     */
    public function testTheContractsAreaResolvesToThisBundlesAreaWithNothingConfigured(): void
    {
        $em = $this->metadataFor([], 'plain');

        $association = $em->getClassMetadata(AreaModule::class)->getAssociationMapping('area');

        self::assertSame(AreaOfInterest::class, $association->targetEntity);
    }

    /**
     * And the metadata is COMPLETE, not merely named: the join column is built,
     * which is the part `migrations:diff` needs and the part that was failing.
     */
    public function testTheResolvedAssociationIsAWholeMapping(): void
    {
        $em = $this->metadataFor([], 'plain');

        $metadata = $em->getClassMetadata(AreaModule::class);

        self::assertSame(AreaOfInterest::class, $metadata->getAssociationTargetClass('area'));
        // The target's own metadata is reachable, which is what the schema tool
        // walks and what stopped before.
        self::assertSame('area_of_interest', $em->getClassMetadata(AreaOfInterest::class)->getTableName());
    }

    /**
     * THE ESCAPE HATCH. An installation with its own area class names it and
     * wins, because prepended configuration loses to the application's — which
     * is Symfony's rule and the reason shipping a default here is safe.
     */
    public function testAnInstallationsOwnResolutionWins(): void
    {
        $em = $this->metadataFor(
            [AreaInterface::class => HostArea::class],
            'override',
        );

        $association = $em->getClassMetadata(AreaModule::class)->getAssociationMapping('area');

        self::assertSame(HostArea::class, $association->targetEntity);
        self::assertNotSame(
            AreaOfInterest::class,
            $association->targetEntity,
            'The bundle must not overrule the installation.',
        );
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
