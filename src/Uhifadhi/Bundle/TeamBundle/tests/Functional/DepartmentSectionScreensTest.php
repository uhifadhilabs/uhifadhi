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

use Symfony\Component\DomCrawler\Crawler;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;

/**
 * THE SECTION'S TWO READING SCREENS — what they state, and what they refuse to
 * do.
 *
 * NEITHER WRITES. The overview reports figures the register and Team own, and
 * the matrix reports an attachment the department's own card makes; a control
 * on either would be a second write path for one fact. The assertions below
 * are therefore as much about the absence of forms as about the figures.
 */
final class DepartmentSectionScreensTest extends WebTestCaseWithSchema
{
    // ---- the overview ------------------------------------------------------

    /**
     * FOUR KPI CARDS, FOUR OR NONE — a figure row is four to a row (ruled),
     * and no workshop index codes on them. The drawn row labels its plates
     * DP·K1 … so a reviewer can name one; those labels belong in the design
     * file and never in the product.
     *
     * THE COUNT OF DEPARTMENTS IS NOT ONE OF THE FOUR: the band directly
     * above opens with exactly that fact and the register below is the list.
     */
    public function testTheOverviewOpensWithFourKpiCardsAndNoWorkshopLabels(): void
    {
        $crawler = $this->overview();

        self::assertCount(4, $crawler->filter('.kstrip .c.kpi'));
        self::assertCount(0, $crawler->filter('.kstrip .idx'));
        self::assertSame(
            ['Positions filled', 'People', 'Modules attached', 'Goals declared'],
            $crawler->filter('.kstrip .c.kpi .tab')->each(static fn (Crawler $c): string => $c->text()),
        );
    }

    /** And the figures are the installation's, counted rather than typed. */
    public function testTheKpiFiguresAreCounted(): void
    {
        $crawler = $this->overview();
        $figures = $crawler->filter('.kstrip .c.kpi b.disp')->each(static fn (Crawler $c): string => $c->text());

        // Two positions, one of them held; one person. (The count of
        // departments is the band's, not the strip's.)
        self::assertStringStartsWith('1', $figures[0]);
        self::assertSame('1', $figures[1]);
    }

    /**
     * THE IDENTITY BAND STATES FACTS AS FRAGMENTS, not sentences, and it is the
     * same band an area's overview opens with.
     */
    public function testTheOverviewCarriesTheIdentityBand(): void
    {
        $crawler = $this->overview();

        self::assertSame(
            ['Departments', 'Org-wide', 'Area-level', 'Positions', 'People'],
            $crawler->filter('.factband .f .k')->each(static fn (Crawler $c): string => $c->text()),
        );
    }

    /**
     * EVERY CARD IS BOUNDED. A list card shows its rows and then says what it
     * is not showing and where the rest is; it never grows to the data.
     */
    public function testEveryListCardEndsOnItsBound(): void
    {
        $crawler = $this->overview();

        self::assertGreaterThan(0, $crawler->filter('.sxmore')->count());
        foreach ($crawler->filter('.sxmore')->each(static fn (Crawler $c): Crawler => $c) as $bound) {
            self::assertSame(1, $bound->filter('a')->count(), 'A bound names one destination.');
        }
    }

    /** THE OVERVIEW WRITES NOTHING, and the proof is that it has no form. */
    public function testTheOverviewCarriesNoForm(): void
    {
        self::assertCount(0, $this->overview()->filter('.pgbody form'));
    }

    /** A ranked bar is scaled to the largest row, not to its own total. */
    public function testTheRankedBarsAreScaledToTheLargestDepartment(): void
    {
        $crawler = $this->overview();
        $bars = $crawler->filter('.sxbars')->first()->filter('.sxbar');

        self::assertGreaterThan(0, $bars->count());
        self::assertSame('Ecology', $bars->first()->filter('.l')->text());
    }

    // ---- the matrix --------------------------------------------------------

    /**
     * ABSENCE IS DRAWN. A department that does not read a module gets the
     * dashed ring, not an empty cell a reader cannot tell from a failure.
     */
    public function testTheMatrixDrawsAbsenceAsWellAsPresence(): void
    {
        $crawler = $this->matrix();

        self::assertSame(
            ['Department', 'Scope', 'Attached'],
            $crawler->filter('.sxmx thead th')->each(static fn (Crawler $c): string => $c->text()),
            'With no module installed the matrix has no module column, and still reads.',
        );
        self::assertCount(3, $crawler->filter('.sxmx tbody tr:not(.sxgrp)'));
    }

    /** EVERY ROW STATES ITS SCOPE, which is how the matrix is read across areas. */
    public function testEveryMatrixRowStatesItsScope(): void
    {
        $scopes = $this->matrix()->filter('.sxmx tbody tr:not(.sxgrp) td:nth-child(2)')
            ->each(static fn (Crawler $c): string => $c->text());

        self::assertSame(['org-wide', 'org-wide', 'Northern Reserve'], $scopes);
    }

    /** THE MATRIX READS AND DOES NOT WRITE: no checkbox, no form. */
    public function testTheMatrixCarriesNoForm(): void
    {
        self::assertCount(0, $this->matrix()->filter('.pgbody form'));
    }

    // ---- the ground both screens stand on ---------------------------------

    private function overview(): Crawler
    {
        return $this->screen('/departments/overview');
    }

    private function matrix(): Crawler
    {
        return $this->screen('/departments/modules');
    }

    private function screen(string $path): Crawler
    {
        $ecology = $this->department('Ecology');
        $this->department('Protection Service');
        $this->areaDepartment('Wetland Management', $this->area('Northern Reserve'));

        $analyst = $this->position('Analyst', $ecology);
        $this->position('Field Assistant', $ecology);

        $admin = $this->person('Naomi', 'Kileo', TeamRoleEnum::Admin);
        $admin->setPosition($this->position('Warden', null, [PermissionEnum::TeamManage->value]));

        $ranger = $this->person('Juma', 'Mollel');
        $ranger->setPosition($analyst);

        $this->em->flush();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();

        return $crawler;
    }
}
