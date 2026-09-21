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

use PHPUnit\Framework\Attributes\CoversClass;
use Uhifadhi\Bundle\TeamBundle\Shell\DepartmentAreaNavChildren;
use Uhifadhi\Contracts\Shell\AreaNavChild;

/**
 * THE DEPARTMENTS UNDER AN AREA IN THE SIDEBAR.
 *
 * The area's Departments tab has no picker column, so this is how one is
 * chosen: a rung points at the tab with that card FOCUSED, the one value the
 * page marks, so the lit row and the marked card cannot disagree.
 *
 * ORG-WIDE FIRST, THEN THE AREA'S OWN — the tree is read from the
 * organization inwards, and the tab's page from this place outwards.
 */
#[CoversClass(DepartmentAreaNavChildren::class)]
final class AreaDepartmentNavTest extends WebTestCaseWithSchema
{
    public function testTheRungsAreTheOrgWideOnesThenThisAreasOwn(): void
    {
        $north = $this->aCast();

        self::assertSame(
            ['Ecology', 'Wetland Management'],
            array_map(static fn (AreaNavChild $c): string => $c->label, $this->children($north)),
        );
    }

    /** Each rung points at the tab, focused on its own card. */
    public function testARungPointsAtTheTabWithItsOwnCardFocused(): void
    {
        $north = $this->aCast();
        $rung = $this->children($north)[0];

        self::assertStringContainsString('/areas/'.$north->getUuidString().'/departments?focus=', $rung->url);
        self::assertStringContainsString('#d-', $rung->url);
    }

    /** Another area's department is not under this area. */
    public function testAnotherAreasDepartmentIsNotUnderThisArea(): void
    {
        $north = $this->aCast();

        $labels = array_map(static fn (AreaNavChild $c): string => $c->label, $this->children($north));

        self::assertNotContains('Coastal Watch', $labels);
    }

    /** The rungs hang under the area's Departments screen and no other. */
    public function testTheyHangUnderTheDepartmentsScreen(): void
    {
        self::assertSame('area_departments', $this->contributor()->screenRoute());
    }

    /** @return list<AreaNavChild> */
    private function children(\Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Area\HostArea $area): array
    {
        $this->client->request('GET', '/areas/'.$area->getUuidString().'/departments');

        return $this->contributor()->childrenFor((string) $area->getUuidString(), (string) $area->getName());
    }

    private function contributor(): DepartmentAreaNavChildren
    {
        $contributor = static::getContainer()->get('test_public.'.DepartmentAreaNavChildren::class);
        self::assertInstanceOf(DepartmentAreaNavChildren::class, $contributor);

        return $contributor;
    }

    private function aCast(): \Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Area\HostArea
    {
        $this->administrator();
        $north = $this->area('Northern Reserve');
        $south = $this->area('Southern Reserve');

        $this->areaDepartment('Wetland Management', $north);
        $this->areaDepartment('Coastal Watch', $south);
        $this->department('Ecology');
        $this->em->flush();

        return $north;
    }
}
