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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Widget\Integration;

use Doctrine\ORM\Tools\SchemaTool;
use Uhifadhi\Bundle\ShellBundle\Tests\Widget\Integration\Fixtures\HostUser;
use Uhifadhi\Bundle\ShellBundle\Tests\Widget\Integration\Fixtures\SightingsSurface;
use Uhifadhi\Bundle\ShellBundle\Widget\Entity\WidgetCustomPreset;
use Uhifadhi\Bundle\ShellBundle\Widget\Entity\WidgetPreference;
use Uhifadhi\Bundle\ShellBundle\Widget\Registry\WidgetSurfaceRegistry;
use Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetEndpoint;
use Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetService;
use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * What an installation gets by registering the bundle: two prefixed tables it
 * wrote no mapping for, three services it wrote no wiring for, and a registry
 * holding exactly the surfaces its modules declared.
 */
final class BundleBootTest extends IntegrationTestCase
{
    public function testTheServicesTheBundleWiresForItself(): void
    {
        self::assertInstanceOf(WidgetSurfaceRegistry::class, $this->service(WidgetSurfaceRegistry::class));
        self::assertInstanceOf(WidgetService::class, $this->service(WidgetService::class));
        self::assertInstanceOf(WidgetEndpoint::class, $this->service(WidgetEndpoint::class));
    }

    /**
     * ZERO-CONFIG PERSISTENCE: the bundle maps its own entities, so an
     * installation writes no doctrine mappings block for widget_* tables.
     */
    public function testTheBundleMapsItsOwnEntitiesUnderPrefixedTables(): void
    {
        self::assertSame('widget_preference', $this->em->getClassMetadata(WidgetPreference::class)->getTableName());
        self::assertSame('widget_custom_preset', $this->em->getClassMetadata(WidgetCustomPreset::class)->getTableName());
    }

    /**
     * THE ONE LINE AN INSTALLATION WRITES, proved rather than described: the
     * association is declared against the contract, and the metadata a schema
     * is built from names the installation's own account class.
     */
    public function testTheUserContractIsResolvedToTheInstallationsAccountClass(): void
    {
        $association = $this->em->getClassMetadata(WidgetPreference::class)->getAssociationMapping('user');

        self::assertSame(HostUser::class, $association->targetEntity);
        self::assertNotSame(UserInterface::class, $association->targetEntity);
    }

    /**
     * The layout has to go when the account does — a saved dashboard has no
     * meaning without the person whose it was — and the guarantee is the
     * database's, so it holds for a DELETE written by hand.
     */
    public function testALayoutIsDeletedWithTheAccountItBelongsTo(): void
    {
        $sql = implode("\n", new SchemaTool($this->em)->getCreateSchemaSql($this->em->getMetadataFactory()->getAllMetadata()));

        self::assertStringContainsString('REFERENCES host_user (id) ON DELETE CASCADE', $sql);
    }

    public function testTheRegistryHoldsExactlyWhatTheModulesDeclared(): void
    {
        $registry = $this->service(WidgetSurfaceRegistry::class);

        self::assertSame([SightingsSurface::SURFACE], $registry->surfaces());
        self::assertSame(
            ['total', 'by-species', 'map'],
            $registry->catalog(SightingsSurface::SURFACE)?->ids(),
            'The catalogue is the declaring module’s, verbatim.',
        );
    }
}
