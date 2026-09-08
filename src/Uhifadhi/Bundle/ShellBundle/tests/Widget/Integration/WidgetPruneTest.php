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

use Uhifadhi\Bundle\ShellBundle\Tests\Widget\Integration\Fixtures\HostUser;
use Uhifadhi\Bundle\ShellBundle\Tests\Widget\Integration\Fixtures\SightingsSurface;
use Uhifadhi\Bundle\ShellBundle\Widget\Entity\WidgetCustomPreset;
use Uhifadhi\Bundle\ShellBundle\Widget\Entity\WidgetPreference;
use Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetPruneService;
use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * PRUNE, NOT PURGE. Removing a module leaves its stored layouts behind on
 * purpose: rows are keyed by a surface string, an orphaned one is inert, and
 * reinstalling the module gives everybody their dashboard back. Deliberate
 * cleanup is this service, and nothing calls it on its own.
 *
 * These were the `widget:prune` command's specifications. The command is gone —
 * the core ships none — and every promise it made is made here instead: the
 * list is available WITHOUT deleting anything, so the prompt that drives this
 * can show it and stop; and deleting is one call nothing but that prompt makes.
 */
final class WidgetPruneTest extends IntegrationTestCase
{
    private function pruner(): WidgetPruneService
    {
        return $this->service(WidgetPruneService::class);
    }

    /**
     * @param non-empty-string $email
     */
    private function person(string $email): UserInterface
    {
        $user = new HostUser();
        $user->setEmail($email)->setFirstName('Asha')->setLastName('Mollel')->setPassword('x');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * The seeded state every case below reasons about: one layout on a surface a
     * module still claims, and one on a surface nothing claims any more.
     */
    private function seedOneClaimedAndOneOrphan(): UserInterface
    {
        $user = $this->person('asha@example.org');

        $this->em->persist(new WidgetPreference(SightingsSurface::SURFACE, $user, null, ['order' => ['total']]));
        $this->em->persist(new WidgetPreference('retired', $user, null, ['order' => ['whatever']]));
        $this->em->persist(new WidgetCustomPreset(SightingsSurface::SURFACE, $user, null, 'Morning check', ['total' => 6]));
        $this->em->persist(new WidgetCustomPreset('retired', $user, null, 'Board meeting', ['whatever' => 12]));
        $this->em->flush();
        $this->em->clear();

        return $user;
    }

    public function testItRemovesOnlyTheLayoutsOfSurfacesNothingClaims(): void
    {
        $this->seedOneClaimedAndOneOrphan();

        $result = $this->pruner()->prune();

        self::assertSame(['retired'], $result->surfaces);
        self::assertSame(1, $result->preferences);
        self::assertSame(1, $result->savedPresets);

        $surfaces = $this->em->getConnection()->fetchFirstColumn('SELECT DISTINCT surface FROM widget_preference');
        self::assertSame([SightingsSurface::SURFACE], $surfaces);

        $presetSurfaces = $this->em->getConnection()->fetchFirstColumn('SELECT DISTINCT surface FROM widget_custom_preset');
        self::assertSame([SightingsSurface::SURFACE], $presetSurfaces, 'A saved preset is a layout too, and orphans the same way.');
    }

    /**
     * THE LIST WITHOUT THE DELETION. This is what makes the decision a person's:
     * whoever asks can see exactly what would go, and then not ask again.
     */
    public function testItSaysWhatIsOrphanedWithoutRemovingAnything(): void
    {
        $this->seedOneClaimedAndOneOrphan();

        self::assertSame(['retired'], $this->pruner()->orphanedSurfaces());

        $surfaces = $this->em->getConnection()->fetchFirstColumn('SELECT DISTINCT surface FROM widget_preference ORDER BY surface');
        self::assertSame(['retired', SightingsSurface::SURFACE], $surfaces);
    }

    /**
     * A CLAIMED SURFACE IS NOBODY'S BUSINESS HERE. It is never reported and
     * never touched, however many layouts it holds.
     */
    public function testAClaimedSurfaceIsNeverReported(): void
    {
        $this->seedOneClaimedAndOneOrphan();

        self::assertNotContains(SightingsSurface::SURFACE, $this->pruner()->orphanedSurfaces());
    }

    public function testAnInstallationWithNothingToPruneRemovesNothing(): void
    {
        $user = $this->person('joel@example.org');
        $this->em->persist(new WidgetPreference(SightingsSurface::SURFACE, $user, null, ['order' => ['total']]));
        $this->em->flush();
        $this->em->clear();

        $result = $this->pruner()->prune();

        self::assertSame([], $result->surfaces);
        self::assertSame(0, $result->preferences);
        self::assertSame(0, $result->savedPresets);

        $rows = $this->em->getConnection()->fetchFirstColumn('SELECT surface FROM widget_preference');
        self::assertSame([SightingsSurface::SURFACE], $rows, 'The claimed surface keeps its layout.');
    }
}
