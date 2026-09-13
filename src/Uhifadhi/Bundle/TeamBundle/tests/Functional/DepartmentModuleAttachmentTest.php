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
use Symfony\Component\DomCrawler\Form;
use Uhifadhi\Bundle\RegistryBundle\Entity\Module;
use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;

/**
 * ATTACHING A MODULE TO A DEPARTMENT — the lens control, and what reading it
 * changes.
 *
 * THE ATTACHMENT IS THE WHOLE OF THE LENS. A department computes no figures of
 * its own: the modules it attaches are what leads its overview, and every number
 * on its performance tab is one of those modules' KPIs. So this suite asks three
 * things of one act — the Settings control offers every module the installation
 * has and lights the attached ones, the overview and the performance strip read
 * that set, and the write is gated exactly as every other department write is.
 *
 * IT GRANTS NOTHING AND HIDES NOTHING. An attachment re-orders a page and
 * nothing else: no permission moves, no row becomes unreachable. That is stated
 * in the control's own copy, and asserted here, because it is the one thing a
 * reader could reasonably fear about a screen that looks like a permission
 * matrix.
 */
final class DepartmentModuleAttachmentTest extends WebTestCaseWithSchema
{
    // ---- the control lists what the installation has -----------------------

    /**
     * EVERY INSTALLED MODULE IS OFFERED. The control reads the registry's
     * catalogue — the intersection of the rows in the table and the providers
     * this container has — so a module nobody installed is not on the page and a
     * module that is installed cannot be missing from it.
     */
    public function testTheControlOffersEveryInstalledModule(): void
    {
        $crawler = $this->lens($this->seed());

        self::assertSame(
            ['Roster', 'Surveys'],
            $this->sorted($crawler->filter('[data-dept-attach] button')->each(static fn (Crawler $c): string => trim($c->text()))),
        );
    }

    /** NOTHING ATTACHED reads as the drawn empty line, not as an absent card. */
    public function testADepartmentWithNothingAttachedSaysSo(): void
    {
        $crawler = $this->lens($this->seed());

        self::assertStringContainsString('Nothing attached.', $crawler->filter('[data-dept-attached]')->text());
    }

    /** An ATTACHED module is a row of its own, with the detach control on it. */
    public function testAnAttachedModuleIsARowWithItsDetachControl(): void
    {
        $department = $this->seed();
        $this->attach($department, 'surveys');

        $crawler = $this->lens($department);

        $row = $crawler->filter('[data-dept-attached] .setrow');
        self::assertCount(1, $row);
        self::assertSame('Surveys', $row->filter('.sn b')->text());
        self::assertStringContainsString('Detach', $row->filter('.sx')->text());

        // And it is no longer one of the modules left to attach.
        self::assertSame(
            ['Roster'],
            $crawler->filter('[data-dept-attach] button')->each(static fn (Crawler $c): string => trim($c->text())),
        );
    }

    /**
     * THE COPY IS THE MODEL, VERBATIM. A control that looks like a permission
     * matrix has to say that it is not one.
     */
    public function testTheControlSaysWhatAnAttachmentDoesAndDoesNot(): void
    {
        $crawler = $this->lens($this->seed());

        $copy = $crawler->filter('[data-tab-panel="settings"]')->text();
        self::assertStringContainsString('it grants nothing and hides nothing', $copy);
        self::assertStringContainsString('lead', $copy);
    }

    // ---- the write ---------------------------------------------------------

    /** ATTACHING through the drawn control files the module under the department. */
    public function testAttachingAModuleFilesItUnderTheDepartment(): void
    {
        $department = $this->seed();

        $crawler = $this->lens($department);
        $this->client->submit($this->attachForm($crawler, 'Surveys'));

        self::assertResponseRedirects('/departments/'.$department->getUuidString());

        $this->em->clear();
        self::assertSame(['surveys'], $this->attachedSlugs($department));
    }

    /** AND DETACHING takes it back off, leaving the department where it started. */
    public function testDetachingAModuleTakesItBackOff(): void
    {
        $department = $this->seed();
        $this->attach($department, 'surveys');

        $crawler = $this->lens($department);
        $this->client->submit($crawler->filter('[data-dept-attached] .setrow')->selectButton('Detach')->form());

        self::assertResponseRedirects('/departments/'.$department->getUuidString());

        $this->em->clear();
        self::assertSame([], $this->attachedSlugs($department));
    }

    /** THE FLASH SAYS WHICH WAY IT WENT, and that nothing was granted. */
    public function testTheFlashNamesTheModuleAndStatesThatNothingWasGranted(): void
    {
        $department = $this->seed();

        $crawler = $this->lens($department);
        $this->client->submit($this->attachForm($crawler, 'Surveys'));
        $crawler = $this->client->followRedirect();

        $flash = $crawler->filter('[data-shell-flash]')->text();
        self::assertStringContainsString('Surveys', $flash);
        self::assertStringContainsStringIgnoringCase('grants nobody anything', $flash);
    }

    /** A SLUG THE INSTALLATION DOES NOT HAVE is not a module to attach. */
    public function testASlugTheInstallationDoesNotHaveIsRefused(): void
    {
        $department = $this->seed();
        $token = $this->tokenFrom('/departments/'.$department->getUuidString());

        $this->client->request('POST', '/departments/'.$department->getUuidString().'/modules/nowhere/toggle', ['_token' => $token]);

        self::assertResponseStatusCodeSame(404);
    }

    // ---- what reading the attached set changes -----------------------------

    /** THE OVERVIEW LEADS WITH THE ATTACHED MODULES, one card each. */
    public function testTheOverviewLeadsWithTheAttachedModules(): void
    {
        $department = $this->seed();
        $this->attach($department, 'surveys');

        $crawler = $this->lens($department);

        $cards = $crawler->filter('[data-tab-panel="overview"] [data-dept-modules] .c');
        self::assertCount(1, $cards);
        self::assertStringContainsString('Surveys', $cards->filter('.tab')->text());
    }

    /** With nothing attached it draws the empty state and invents no card. */
    public function testTheOverviewOfAnUnattachedDepartmentDrawsTheEmptyState(): void
    {
        $crawler = $this->lens($this->seed());

        self::assertStringContainsString(
            'No modules attached yet',
            $crawler->filter('[data-tab-panel="overview"] [data-dept-modules]')->text(),
        );
    }

    /**
     * THE KPI STRIP IS THE ATTACHED MODULES' FIGURES, and only theirs. The
     * surveys module reports; the roster module reports too, and its figure stays
     * off the page until the department attaches it.
     */
    public function testThePerformanceStripCarriesAnAttachedModulesFigureAndNoOthers(): void
    {
        $department = $this->seed();
        $this->attach($department, 'surveys');

        $crawler = $this->lens($department);
        $strip = $crawler->filter('[data-tab-panel="performance"] [data-dept-kpis]');

        self::assertStringContainsString('Surveys logged', $strip->text());
        self::assertStringContainsString('88', $strip->text());
        self::assertStringNotContainsString('Shifts filled', $strip->text(), 'a detached module puts no figure on the page');
    }

    /**
     * A PROVIDER THAT HONOURS THE ONE-SET RULE IS DRAWN ONCE — one tile per
     * figure and one row per figure, which is the single row of representative
     * tiles the design draws per module. The surveys module answers one set for
     * the department it was asked about, so its label cannot appear twice.
     */
    public function testAFigureIsListedOnceWhenItsProviderAnswersOneSet(): void
    {
        $department = $this->seed();
        $this->attach($department, 'surveys');

        $crawler = $this->lens($department);

        self::assertCount(1, $this->tilesLabelled($crawler, 'Surveys logged'));
        self::assertCount(
            1,
            $crawler->filter('[data-dept-modules] .rln')->reduce(static fn (Crawler $c): bool => str_contains($c->text(), 'Surveys logged')),
        );
    }

    /**
     * AND A PROVIDER THAT STILL ANSWERS ONE SET PER AREA IS DRAWN UNDER THE AREA
     * LABEL EACH SET CARRIES — never as the same label three times over with
     * nothing to tell them apart. The roster fixture is that provider.
     */
    public function testSeveralSetsForOneCallAreDrawnUnderTheAreaEachCarries(): void
    {
        $department = $this->seed();
        $this->attach($department, 'roster');

        $strip = $this->lens($department)->filter('[data-tab-panel="performance"] [data-dept-kpis]');

        self::assertSame(
            ['Northern Reserve', 'Southern Plains'],
            $strip->filter('.dp-arealbl')->each(static fn (Crawler $c): string => trim($c->text())),
        );
        self::assertCount(2, $strip->filter('.kpi'), 'one tile per set, each under its own area');
    }

    /** AN UNKNOWN FIGURE IS A DASHED SLOT, never a zero. */
    public function testAnUnmeasuredFigureIsDrawnAsADashAndNotAZero(): void
    {
        $department = $this->seed();
        $this->attach($department, 'surveys');

        $crawler = $this->lens($department);
        $coverage = $crawler->filter('[data-dept-kpis] .kpi')
            ->reduce(static fn (Crawler $c): bool => str_contains($c->text(), 'Coverage'))
            ->first();

        self::assertStringContainsString("\u{2014}", $coverage->filter('.disp')->text());
        self::assertStringNotContainsString('0', $coverage->filter('.disp')->text());
    }

    /** With nothing attached the strip says there is nothing to roll up. */
    public function testTheStripOfAnUnattachedDepartmentSaysThereIsNothingToRollUp(): void
    {
        $crawler = $this->lens($this->seed());

        self::assertStringContainsString(
            'No module KPIs this period',
            $crawler->filter('[data-tab-panel="performance"] [data-dept-kpis]')->text(),
        );
    }

    // ---- who may write it --------------------------------------------------

    public function testAColleagueWithoutTeamManageIsRefused(): void
    {
        $department = $this->seed();
        $token = $this->tokenFrom('/departments/'.$department->getUuidString());

        $ranger = $this->person('Juma', 'Mwakalinga', TeamRoleEnum::Staff);
        $ranger->setPosition($this->position('Ranger', $department, [PermissionEnum::AreaView->value]));
        $this->em->flush();
        $this->client->loginUser($ranger);

        $this->client->request('POST', $this->toggleUrl($department, 'surveys'), ['_token' => $token]);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertSame([], $this->attachedSlugs($department));
    }

    public function testAnAnonymousVisitorIsSentToSignIn(): void
    {
        $department = $this->department('Ecology');
        $this->em->flush();

        $this->client->request('POST', $this->toggleUrl($department, 'surveys'));

        self::assertResponseRedirects('http://localhost/login');
    }

    /**
     * AN AREA ADMINISTRATOR REACHES THEIR OWN AREA'S DEPARTMENTS AND NO OTHERS —
     * the same boundary every other write on this controller keeps.
     */
    public function testAnAreaAdminCannotAttachToAnOrgLevelDepartment(): void
    {
        $north = $this->area('Northern Reserve');
        $this->modules();
        $ecology = $this->department('Ecology');
        $office = $this->areaDepartment('Warden Office', $north);
        $admin = $this->person('Amina', 'Salehe', TeamRoleEnum::Staff);
        $admin->setPosition($this->position('Warden', $office, [PermissionEnum::TeamManage->value]));
        $this->em->flush();
        $this->client->loginUser($admin);

        $token = $this->tokenFrom('/departments/'.$office->getUuidString());

        // Their own area department: allowed.
        $this->client->request('POST', $this->toggleUrl($office, 'surveys'), ['_token' => $token]);
        self::assertResponseRedirects('/departments/'.$office->getUuidString());

        // An org-level one: refused.
        $this->client->request('POST', $this->toggleUrl($ecology, 'surveys'), ['_token' => $token]);
        self::assertResponseStatusCodeSame(403);

        $this->em->clear();
        self::assertSame([], $this->attachedSlugs($ecology));
    }

    // ---- the cast ----------------------------------------------------------

    /**
     * An installation with the two modules this kernel's providers declare, and
     * one org-level department to attach them to.
     */
    private function seed(): Department
    {
        $this->administrator();
        $this->modules();
        $department = $this->department('Ecology');
        $this->em->flush();

        return $department;
    }

    /**
     * The catalogue rows for the modules this kernel's providers declare. A row
     * plus a registered provider is what makes a module installed; either alone
     * is not.
     */
    private function modules(): void
    {
        foreach (['surveys' => 'Surveys', 'roster' => 'Roster'] as $slug => $name) {
            $this->em->persist((new Module())->setSlug($slug)->setName($name));
        }
    }

    /** The performance tiles whose label is this one. */
    private function tilesLabelled(Crawler $crawler, string $label): Crawler
    {
        return $crawler->filter('[data-dept-kpis] .kpi')
            ->reduce(static fn (Crawler $c): bool => str_contains($c->text(), $label));
    }

    private function lens(Department $department): Crawler
    {
        return $this->client->request('GET', '/departments/'.$department->getUuidString());
    }

    private function toggleUrl(Department $department, string $slug): string
    {
        return '/departments/'.$department->getUuidString().'/modules/'.$slug.'/toggle';
    }

    /** Attach through the real write, so no test seeds a state the screen cannot make. */
    private function attach(Department $department, string $slug): void
    {
        $this->client->request('POST', $this->toggleUrl($department, $slug), [
            '_token' => $this->tokenFrom('/departments/'.$department->getUuidString()),
        ]);
        self::assertResponseRedirects();
        $this->em->clear();
    }

    private function attachForm(Crawler $crawler, string $module): Form
    {
        return $crawler->filter('[data-dept-attach] form')
            ->reduce(static fn (Crawler $c): bool => $module === trim($c->filter('button')->text()))
            ->first()
            ->form();
    }

    /** @return list<string> */
    private function attachedSlugs(Department $department): array
    {
        $fresh = $this->em->getRepository(Department::class)->findOneBy(['name' => $department->getName()]);
        self::assertInstanceOf(Department::class, $fresh);

        $slugs = [];
        foreach ($fresh->getModules() as $module) {
            $slugs[] = (string) $module->getSlug();
        }

        return $this->sorted($slugs);
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
