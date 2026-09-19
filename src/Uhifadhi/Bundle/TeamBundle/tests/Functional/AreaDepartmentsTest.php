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
use Symfony\Component\DomCrawler\Crawler;
use Uhifadhi\Bundle\TeamBundle\Controller\AreaDepartmentController;

/**
 * AN AREA'S DEPARTMENTS, READ.
 *
 * THE TAB IS THE REGISTER, NARROWED TO ONE AREA. Its cards are the
 * organisation register's cards — the same partial, not a second one that
 * would drift — and the only difference is which departments are on it and
 * in what order: the area's OWN first, then the org-wide ones it inherits,
 * under a heading that says so.
 *
 * IT ONLY READS. Adding, renaming and scoping are the configure section's,
 * which is where the header's Configure action goes; the one other action
 * is the way out to the organisation's whole register.
 *
 * THE ROUTE IS TEAM'S, and that is the reason there is no new seam here: a
 * department already knows which area it belongs to — `Department::$area`
 * is the published `AreaInterface` — so the bundle that owns departments
 * can answer "this area's" without the area bundle learning what a
 * department is.
 */
#[CoversClass(AreaDepartmentController::class)]
final class AreaDepartmentsTest extends WebTestCaseWithSchema
{
    public function testTheTabGroupsTheAreasOwnFirstThenTheOrgWideOnesItInherits(): void
    {
        $crawler = $this->tab();

        $headings = $crawler->filter('.deptgroup .gh')->each(static fn (Crawler $c): string => $c->text());

        self::assertSame(['Northern Reserve’s own', 'Org-wide · read this area too'], $headings);
    }

    /** Only this area's own and the org-wide ones — never another area's. */
    public function testAnotherAreasDepartmentIsNotOnThisAreasTab(): void
    {
        $crawler = $this->tab();

        self::assertSame(
            ['Wetland Management', 'Ecology'],
            $crawler->filter('.dcard .ov-nm')->each(static fn (Crawler $c): string => $c->text()),
        );
    }

    /** The cards are the register's own, down to the labelled disclosure. */
    public function testTheCardsAreTheRegistersCards(): void
    {
        $crawler = $this->tab();
        $card = $crawler->filter('.dcard')->first();

        self::assertCount(1, $card->filter('.dc-act .ovx.xdisc'));
        self::assertCount(1, $card->filter('.dc-act .ov-open'));
        self::assertSame('Northern Reserve', $card->filter('.ov-sc')->text());
    }

    /** The band says how many read this area, and how they divide. */
    public function testTheBandCountsWhatReadsThisArea(): void
    {
        $band = $this->tab()->filter('.factband')->text();

        self::assertStringContainsString('Departments', $band);
        self::assertStringContainsString('1 of this area, 1 org-wide', $band);
    }

    /**
     * THE TAB WRITES NOTHING. Every control that changes a department is on
     * the configure section or the organisation's register, and the tab
     * points at both: the header's way out to the whole register, and the
     * band's way in to this area's section. (The Configure action itself is
     * the frame's, written by the area's own configure page.).
     */
    public function testTheTabOffersNoWriteAndPointsAtTheTwoPlacesThatDo(): void
    {
        $crawler = $this->tab();

        self::assertCount(0, $crawler->filter('.dcard form'));
        self::assertSame('/departments', $crawler->filter('.pgact a.cta')->attr('href'));
        self::assertStringEndsWith('/departments/settings', (string) $crawler->filter('.factband a.more')->attr('href'));
    }

    private function tab(): Crawler
    {
        $this->administrator();
        $north = $this->area('Northern Reserve');
        $south = $this->area('Southern Reserve');

        $this->areaDepartment('Wetland Management', $north);
        $this->areaDepartment('Coastal Watch', $south);
        $this->department('Ecology');
        $this->em->flush();

        return $this->client->request('GET', '/areas/'.$north->getUuidString().'/departments');
    }
}
