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

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Uid\Uuid;
use Uhifadhi\Bundle\ShellBundle\Tests\Widget\Integration\Fixtures\HostUser;
use Uhifadhi\Bundle\ShellBundle\Widget\Entity\WidgetPreference;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\Widget;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetCatalog;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetGroup;
use Uhifadhi\Bundle\ShellBundle\Widget\Repository\WidgetPreferenceRepository;
use Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetService;
use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * The stored half of the widget framework: one row per person per surface, and
 * the database — not the application — is what guarantees it.
 *
 * The nullable area is the whole difficulty. Postgres treats NULLs as DISTINCT
 * in a unique index, so a plain UNIQUE (surface, user_id, area_uuid) would
 * happily accept two org-wide rows for the same person on the same dashboard —
 * a second row that read() would never see and reset() would never delete. The
 * mapping therefore declares TWO partial unique indexes over the two disjoint
 * cases, and the tests below prove both halves.
 *
 * THE PEOPLE HERE ARE REAL ACCOUNTS, not invented ids. A layout points at
 * `Uhifadhi\Contracts\Entity\UserInterface`, which the test installation
 * resolves to TeamBundle's account class (see TestKernel) — so every
 * row below travels the resolution an installation configures, foreign key
 * included.
 */
final class WidgetPreferenceTest extends IntegrationTestCase
{
    /** @var array<string, UserInterface> */
    private array $people = [];

    /**
     * The same person on every call, created on the first. Named rather than
     * numbered, because "person A" and "person B" is what the assertions mean.
     */
    private function person(string $name): UserInterface
    {
        if (isset($this->people[$name])) {
            return $this->people[$name];
        }

        $user = new HostUser();
        $user->setEmail($name.'@example.org')->setFirstName(ucfirst($name))->setLastName('Mollel')->setPassword('x');
        $this->em->persist($user);
        $this->em->flush();

        return $this->people[$name] = $user;
    }

    /**
     * @return \Doctrine\ORM\Mapping\ClassMetadata<WidgetPreference>
     */
    private function metadata(): \Doctrine\ORM\Mapping\ClassMetadata
    {
        return $this->em->getClassMetadata(WidgetPreference::class);
    }

    private function repository(): WidgetPreferenceRepository
    {
        $repository = self::getContainer()->get(WidgetPreferenceRepository::class);
        \assert($repository instanceof WidgetPreferenceRepository);

        return $repository;
    }

    private static function catalog(): WidgetCatalog
    {
        return new WidgetCatalog('departments', [new WidgetGroup('shape', 'Shape', 'How the org is arranged.')], [
            new Widget('tree', 'Department tree', 'shape'),
            new Widget('vacancies', 'Vacant positions', 'shape', cols: 6, spans: [9, 6, 3]),
        ]);
    }

    /** The bundle's own service, held through the test-only public alias. */
    private function widgets(): WidgetService
    {
        return $this->service(WidgetService::class);
    }

    public function testTheTableCarriesBothPartialUniqueIndexes(): void
    {
        $sql = implode("\n", new SchemaTool($this->em)->getCreateSchemaSql([$this->metadata()]));

        // The area-scoped half…
        self::assertStringContainsString(
            'CREATE UNIQUE INDEX uniq_widget_pref_surface_user_area ON widget_preference (surface, user_id, area_uuid) WHERE (area_uuid IS NOT NULL)',
            $sql,
        );
        // …and the org-wide half, which the three-column index cannot police
        // because Postgres counts NULLs as distinct.
        self::assertStringContainsString(
            'CREATE UNIQUE INDEX uniq_widget_pref_surface_user_org ON widget_preference (surface, user_id) WHERE (area_uuid IS NULL)',
            $sql,
        );
    }

    public function testTwoOrgWideRowsForOnePersonAndSurfaceAreImpossible(): void
    {
        $this->em->persist(new WidgetPreference('departments', $this->person('asha'), null, ['order' => []]));
        $this->em->flush();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->em->persist(new WidgetPreference('departments', $this->person('asha'), null, ['order' => []]));
        $this->em->flush();
    }

    public function testTwoRowsForOnePersonSurfaceAndAreaAreImpossible(): void
    {
        $area = Uuid::v7();
        $this->em->persist(new WidgetPreference('patrols', $this->person('asha'), $area, ['order' => []]));
        $this->em->flush();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->em->persist(new WidgetPreference('patrols', $this->person('asha'), $area, ['order' => []]));
        $this->em->flush();
    }

    public function testTheSameSurfaceInAnotherAreaForAnotherPersonOrAnotherSurfaceIsAllowed(): void
    {
        $area = Uuid::v7();
        $this->em->persist(new WidgetPreference('patrols', $this->person('asha'), $area));
        // Another area, another person, another surface — and the org-wide row of
        // the same surface, which is a different layout, not a duplicate.
        $this->em->persist(new WidgetPreference('patrols', $this->person('asha'), Uuid::v7()));
        $this->em->persist(new WidgetPreference('patrols', $this->person('joel'), $area));
        $this->em->persist(new WidgetPreference('incidents', $this->person('asha'), $area));
        $this->em->persist(new WidgetPreference('patrols', $this->person('asha'), null));
        $this->em->flush();

        self::assertCount(5, $this->repository()->findAll());
    }

    public function testAnOrgWideLayoutIsSavedResolvedAndResetWithoutAnArea(): void
    {
        $catalog = self::catalog();

        // No row yet: the surface's default design, which IS the catalogue's own
        // composition — in this model there is no layout that is not a preset.
        self::assertSame(
            ['tree', 'vacancies'],
            array_column($this->widgets()->resolve($catalog, $this->person('asha')), 'id'),
        );

        // Editing means editing a preset of your own, so composing one is the
        // step that makes a canvas editable at all.
        $this->onOwnPreset($catalog, $this->person('asha'), null, ['vacancies' => 6]);
        $this->widgets()->save($catalog, $this->person('asha'), [
            'order' => ['vacancies'],
            'widgets' => ['vacancies' => ['on' => true, 'cols' => 6], 'tree' => ['on' => false, 'cols' => 6]],
        ]);
        $this->em->clear();

        $resolved = $this->widgets()->resolve($catalog, $this->person('asha'));
        self::assertSame(['vacancies', 'tree'], array_column($resolved, 'id'), 'the composition first, then what it leaves off');
        self::assertTrue($resolved[0]['on']);
        self::assertFalse($resolved[1]['on']);
        self::assertSame(6, $resolved[0]['cols']);
        // Another person's dashboard is untouched by it.
        self::assertSame(['tree', 'vacancies'], array_column($this->widgets()->resolve($catalog, $this->person('joel')), 'id'));

        $this->widgets()->reset($catalog, $this->person('asha'));
        self::assertNull($this->repository()->findOneForUser('departments', $this->person('asha')));
        self::assertSame(['tree', 'vacancies'], array_column($this->widgets()->resolve($catalog, $this->person('asha')), 'id'));
    }

    public function testSavingTwiceUpdatesTheOnePersonsOneRow(): void
    {
        $catalog = self::catalog();

        $this->onOwnPreset($catalog, $this->person('asha'), null, ['vacancies' => 12, 'tree' => 12]);
        $this->widgets()->save($catalog, $this->person('asha'), ['order' => ['vacancies', 'tree'], 'widgets' => []]);
        $this->widgets()->save($catalog, $this->person('asha'), ['order' => ['tree', 'vacancies'], 'widgets' => []]);

        self::assertCount(1, $this->repository()->findAll());
        self::assertSame(['tree', 'vacancies'], array_column($this->widgets()->resolve($catalog, $this->person('asha')), 'id'));
    }

    public function testAreaScopedAndOrgWideLayoutsOfTheSameSurfaceDoNotSeeEachOther(): void
    {
        $catalog = self::catalog();
        $area = Uuid::v7();

        $this->onOwnPreset($catalog, $this->person('asha'), $area, ['vacancies' => 12, 'tree' => 12]);

        self::assertSame(['vacancies', 'tree'], array_column($this->widgets()->resolve($catalog, $this->person('asha'), $area), 'id'));
        // The org-wide layout of the same surface never learned of it.
        self::assertSame(['tree', 'vacancies'], array_column($this->widgets()->resolve($catalog, $this->person('asha')), 'id'));
    }

    public function testAnAnonymousRequestAlwaysGetsTheCatalogueDefaults(): void
    {
        $catalog = self::catalog();
        $this->onOwnPreset($catalog, $this->person('asha'), null, ['vacancies' => 12, 'tree' => 12]);

        self::assertSame(['tree', 'vacancies'], array_column($this->widgets()->resolve($catalog, null), 'id'));
    }

    /**
     * Put this person on a preset of their OWN, composed from the given layout.
     *
     * The model has no anonymous layout and built-ins are immutable, so this is
     * the step every edit begins with: there is nothing editable to save into
     * until one of your own presets is active.
     *
     * @param array<string, int> $layout widget id => span, in order
     */
    private function onOwnPreset(WidgetCatalog $catalog, UserInterface $user, ?Uuid $areaUuid, array $layout): void
    {
        $widgets = [];
        foreach ($layout as $id => $cols) {
            $widgets[$id] = ['on' => true, 'cols' => $cols];
        }

        $this->widgets()->saveCustomPreset($catalog, $user, $areaUuid, 'Mine', [
            'order' => array_keys($layout),
            'widgets' => $widgets,
        ]);
    }
}
