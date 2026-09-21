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
use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentScopeChangeRepository;

/**
 * THE AREA-AWARE DEPARTMENT MANAGER — the register, and the lens every row opens.
 *
 * A department carries a scope now: area-level (confined to one area) or
 * org-level (spanning every area). This screen draws the two apart — area-level
 * first, grouped by their area's name, then org-level — states each row's scope
 * in an explicit column, creates in either scope (area-first, the picker
 * enumerating the installation's areas through the contract), opens every
 * department to its own lens, and changes a scope with a reason recorded to the
 * audit trail.
 *
 * THE SCREEN IS THE CANONICAL REGISTER, not a card wall: there is no
 * `.dcard`/`.pitem`/Unassigned-card vocabulary. The rules that hold whatever it
 * is drawn as (a department grants nothing; DELETE and DEACTIVATE are not
 * drawn, so not here) are asserted below.
 */
final class DepartmentScreenTest extends WebTestCaseWithSchema
{
    /**
     * THE BAND CARRIES WHAT THE DEPARTMENTS DID, not how many of them
     * there are. A reader can already see how many cards are on the
     * page; what they cannot see is what those departments have been
     * doing, which is what the performance seam knows.
     */
    public function testTheBandCarriesTheFiguresAndTheCountsMoveUnderTheFilters(): void
    {
        $crawler = $this->screen();

        $band = $crawler->filter('.factband .f .k')->each(static fn (Crawler $c): string => $c->text());

        // The host's three are always there; a module's figures lead them
        // where the installation runs any.
        self::assertContains('Areas', $band);
        self::assertContains('Seats filled', $band);
        self::assertContains('Goals', $band);
        self::assertNotContains('Departments', $band, 'a count of the cards is not a figure about the organization');

        // AND THE COUNTS ARE UNDER THE FILTERS, where a count of what is
        // being listed belongs.
        $caption = $crawler->filter('.dcfil .cnt')->text();
        self::assertStringContainsString('departments', $caption);
        self::assertStringContainsString('org-wide', $caption);
        self::assertStringContainsString('positions', $caption);
    }

    /** The register wears the same two controls the performance section does. */
    public function testTheHeaderCarriesTheScopeAndThePeriod(): void
    {
        $crawler = $this->screen();

        self::assertGreaterThan(0, $crawler->filter('.pgact .ov-ctl .i-dd')->count(), 'the scope, as addresses');
        self::assertSame(
            ['Month', 'Quarter', 'Year'],
            $crawler->filter('.pgact .periodpick a')->each(static fn (Crawler $c): string => $c->text()),
        );

        // A NARROWED REGISTER NARROWS THE BAND: a page that filtered its
        // rows to one area and kept the organization's figures would be
        // contradicting its own filter.
        $north = $this->north;
        self::assertNotNull($north, 'the register was seeded with an area');

        $narrowed = $this->client->request('GET', '/departments?scope=area&area='.$north->getUuidString());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Northern Reserve', $narrowed->filter('.pgact .i-ddval')->text());
    }

    // ---- the register lists both scope groups, area-first -----------------

    /**
     * THE TWO SCOPES ARE DRAWN APART, ORG-WIDE FIRST. The register is read
     * from the organization inwards: an org-wide department is one every area
     * inherits, which is the thing a reader has to know before the rest of
     * the list means anything. Each area's own follow, under its name.
     */
    /**
     * A DEPARTMENT'S CARD WEARS ITS OWN HUE, and it is the same category the
     * sidebar's dot reads.
     *
     * THE CARD CARRIES AN INDEX AND NEVER A COLOUR — the shell resolves it,
     * which is the only way one department reads the same in both palettes
     * and again on imagery. The mark was accent-tinted for every ACTIVE
     * department before this, which left all nine identical.
     */
    public function testEachDepartmentsCardCarriesItsOwnCategory(): void
    {
        $this->administrator();
        $this->department('Ecology');
        $this->department('Tourism');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/departments');
        self::assertResponseIsSuccessful();

        $cats = $crawler->filter('article.dcard')->each(
            static fn (Crawler $c): ?string => $c->attr('data-cat'),
        );

        self::assertSame(['1', '2'], $cats);
        self::assertCount(0, $crawler->filter('.ov-mk.on'), 'the mark is no longer accent-tinted for every active department');
    }

    public function testTheRegisterGroupsOrgWideFirstThenEachAreasOwn(): void
    {
        $crawler = $this->screen();

        $headings = $crawler->filter('[data-dp] .deptgroup .gh')->each(static fn (Crawler $c): string => $c->text());

        self::assertContains('Org-wide', $headings);
        self::assertContains('Northern Reserve', $headings);

        self::assertLessThan(
            array_search('Northern Reserve', $headings, true),
            array_search('Org-wide', $headings, true),
        );
    }

    /**
     * EVERY CARD STATES ITS SCOPE beside its name: the area's name for an
     * area-level department, "Org-wide" for one the organization owns.
     */
    public function testEachCardCarriesItsScope(): void
    {
        $crawler = $this->screen();

        self::assertSame('Northern Reserve', $this->row($crawler, 'Wetland Management')->filter('.ov-sc')->text());
        self::assertSame('Org-wide', $this->row($crawler, 'Ecology')->filter('.ov-sc')->text());
    }

    /**
     * AN AREA-LEVEL DEPARTMENT SITS UNDER ITS AREA'S HEADING, which is where
     * its scope is read from — the card states it too, and the two cannot
     * disagree because both are the same association.
     */
    public function testAnAreaLevelDepartmentSitsUnderItsAreasHeading(): void
    {
        $crawler = $this->screen();

        $section = $crawler->filter('[data-dp] details.nvsec')
            ->reduce(static fn (Crawler $c): bool => 'Northern Reserve' === $c->filter('.gh')->text())
            ->first();

        self::assertSame(
            ['Wetland Management'],
            $section->filter('.dcard .ov-nm')->each(static fn (Crawler $c): string => $c->text()),
        );
    }

    /**
     * THE PILLS ARE THE CREATE CHOOSER'S OWN WORDS, in the same order, and
     * every one of them is a link: the address is the state, so a filtered
     * register can be sent to somebody.
     */
    public function testTheScopePillsFilterTheRegisterOffTheAddress(): void
    {
        $this->screen();

        self::assertSame(
            ['Ecology', 'Protection Service'],
            $this->named($this->client->request('GET', '/departments?scope=org')),
        );
        self::assertSame(
            ['Wetland Management'],
            $this->named($this->client->request('GET', '/departments?scope=area')),
        );
    }

    /** And the search reads the name, which is what somebody types. */
    public function testTheSearchReadsTheName(): void
    {
        $this->screen();

        self::assertSame(['Wetland Management'], $this->named($this->client->request('GET', '/departments?q=wetland')));
    }

    /**
     * A GROUP THE SEARCH EMPTIES STAYS, because a heading that vanished would
     * make the register look shorter than it is; a group the FILTER excludes
     * goes, because a heading counting nothing is noise.
     */
    public function testASearchEmptiesAGroupAndAFilterRemovesIt(): void
    {
        $this->screen();

        $searched = $this->client->request('GET', '/departments?q=wetland');
        self::assertCount(2, $searched->filter('[data-dp] details.nvsec'));

        $filtered = $this->client->request('GET', '/departments?scope=org');
        self::assertCount(1, $filtered->filter('[data-dp] details.nvsec'));
    }

    /**
     * THE FOCUSED CARD IS THE ONE THE SIDEBAR POINTS AT, and it is marked
     * apart from the open one: focus is a line down the edge, open is a body.
     */
    public function testTheFocusedDepartmentIsMarkedAndIsNotTheOpenOne(): void
    {
        $this->screen();
        $crawler = $this->client->request('GET', '/departments?focus='.$this->uuidOf('Ecology'));

        $card = $this->row($crawler, 'Ecology');
        self::assertStringContainsString('dcfocus', (string) $card->attr('class'));
        self::assertStringNotContainsString('dcard on', (string) $card->attr('class'));
    }

    /** An open card draws the positions filed under it, and the way to add one. */
    public function testAnOpenCardDrawsThePositionsFiledUnderIt(): void
    {
        $this->screen();
        $crawler = $this->client->request('GET', '/departments?open='.$this->uuidOf('Ecology'));

        $card = $this->row($crawler, 'Ecology');
        self::assertStringContainsString('Positions in Ecology', $card->text());
        self::assertStringContainsString('Add position', $card->text());
    }

    /**
     * A CARD IS OPENED BY A LABELLED BUTTON, not by a caret in the corner.
     *
     * RULED 2026-09-20. A bare chevron top-left says nothing about what it
     * does and nothing about the state it is in; the control now sits in the
     * header's action cluster beside Open, reads EXPAND when the card is
     * shut and COLLAPSE when it is open, and carries the state in
     * `aria-expanded` as well as in the word. It is a STATE VERB and never a
     * count: "3 positions" on a button tells you what is inside, not what
     * pressing it does, and it changes under you when somebody files a
     * position.
     */
    public function testACardIsOpenedByALabelledButtonBesideOpen(): void
    {
        $crawler = $this->screen();
        $uuid = $this->uuidOf('Ecology');

        $shut = $this->row($crawler, 'Ecology')->filter('.dc-act .ovx.xdisc');
        // The word is written once and SHOUTED by the sheet, as every mono
        // label in the product is — the markup carries the sentence case.
        self::assertSame('Expand', trim($shut->filter('.t')->text()));
        self::assertSame('false', $shut->attr('aria-expanded'));
        self::assertStringContainsString('Ecology', (string) $shut->attr('aria-label'));

        $open = $this->row($this->client->request('GET', '/departments?open='.$uuid), 'Ecology')
            ->filter('.dc-act .ovx.xdisc');
        self::assertSame('Collapse', trim($open->filter('.t')->text()));
        self::assertSame('true', $open->attr('aria-expanded'));
    }

    /** And the old caret is gone from the header's left. */
    public function testTheBareCaretIsGoneFromTheCardsCorner(): void
    {
        $card = $this->row($this->screen(), 'Ecology');

        self::assertCount(0, $card->filter('.dc-hd > .ovx'));
        self::assertCount(1, $card->filter('.dc-act .ovx.xdisc'));
    }

    /**
     * THE FOOTER IS ONE LINE UNTIL IT IS ASKED FOR MORE.
     *
     * A COLLAPSED CARD IS A ROW IN A LIST, and a register of nine of them is
     * read by running down the names. A footer that drew the confine form,
     * the rename field and the deactivate button open made every collapsed
     * card a hundred pixels taller than the design's and turned the list into
     * a stack of forms. So the strip states the scope and offers two
     * disclosures; both are `<details>`, so they open with no script of ours.
     */
    public function testTheFooterIsOneLineWithItsFormsBehindDisclosures(): void
    {
        $crawler = $this->screen();
        $card = $this->row($crawler, 'Ecology');

        // One line in the strip, and the two ways to more.
        self::assertCount(1, $card->filter('.dc-foot > .dc-line'));
        self::assertCount(2, $card->filter('.dc-foot details'));

        // Both closed on arrival: nothing in the footer is open by default.
        self::assertSame(
            [null, null],
            $card->filter('.dc-foot details')->each(static fn (Crawler $c): ?string => $c->attr('open')),
            'A footer disclosure is open before anybody asked.',
        );

        // And the strip says what the scope IS without being opened.
        self::assertStringContainsString('every area reads it', $card->filter('.dc-foot > .dc-line')->text());
    }

    /** The forms are still there, and still post where they posted. */
    public function testTheDisclosuresHoldTheSameThreeOperations(): void
    {
        $crawler = $this->screen();
        $card = $this->row($crawler, 'Ecology');

        self::assertCount(1, $card->filter('.dc-foot details form[action$="/scope"]'));
        self::assertCount(1, $card->filter('.dc-foot details form[action$="/rename"]'));
        self::assertCount(1, $card->filter('.dc-foot details form[action$="/deactivate"]'));
    }

    /**
     * THE BODY OPENS WITH NO SCRIPT EITHER: the chevron is a link and which
     * card is open is in the address, so an opened card is a link somebody
     * can send.
     */
    public function testTheBodyOpensByTheAddressAndNotByAScript(): void
    {
        $crawler = $this->screen();
        $uuid = $this->uuidOf('Ecology');

        $chevron = $this->row($crawler, 'Ecology')->filter('.dc-hd a.ovx');
        self::assertStringContainsString('open='.$uuid, (string) $chevron->attr('href'));
        self::assertSame('false', $chevron->attr('aria-expanded'));

        $opened = $this->client->request('GET', '/departments?open='.$uuid);
        self::assertSame('true', $this->row($opened, 'Ecology')->filter('.dc-hd a.ovx')->attr('aria-expanded'));
    }

    /**
     * The names in the register, in the order it draws them.
     *
     * @return list<string>
     */
    private function named(Crawler $crawler): array
    {
        return $crawler->filter('[data-dp] .dcard .ov-nm')->each(static fn (Crawler $c): string => $c->text());
    }

    // ---- every department opens to its lens -------------------------------

    /**
     * EVERY DEPARTMENT IS OPENABLE — the name and the Lens action both point at
     * the department's own page, area-level or org-level alike.
     */
    public function testEveryRowOpensItsDepartmentThroughNameAndLens(): void
    {
        $crawler = $this->screen();

        foreach (['Wetland Management', 'Ecology'] as $name) {
            $row = $this->row($crawler, $name);
            $show = '/departments/'.$this->uuidOf($name);

            self::assertSame($show, $row->filter('.ov-nm')->attr('href'), $name.' name links to its lens');
            self::assertSame($show, $row->filter('.dc-act .ov-open')->attr('href'), $name.' Open action links to its lens');
        }
    }

    /** And the lens opens, carrying the department's name and its scope. */
    public function testTheAreaLevelLensOpensAndReadsAsAreaScoped(): void
    {
        $this->screen();

        $crawler = $this->client->request('GET', '/departments/'.$this->uuidOf('Wetland Management'));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Wetland Management', $crawler->filter('.dpthead h1')->text());
        self::assertStringContainsString('Northern Reserve', $crawler->filter('.dpthead .scope.area')->text());
        self::assertStringContainsString('confined to Northern Reserve', $crawler->filter('.scoperule')->first()->text());
    }

    public function testTheOrgLevelLensReadsAcrossEveryArea(): void
    {
        $this->screen();

        $crawler = $this->client->request('GET', '/departments/'.$this->uuidOf('Ecology'));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Org-level', $crawler->filter('.dpthead .scope.org')->text());
        self::assertStringContainsString('reads across every area', $crawler->filter('.scoperule')->first()->text());
    }

    /**
     * THE IDENTITY CARD IS ON THE OVERVIEW TAB ONLY — the page-chrome ruling. It
     * lives inside the Overview panel, so it is present once and hidden with that
     * panel; the header/breadcrumb and the scope strip carry name+scope on every
     * tab instead.
     */
    public function testTheIdentityCardLivesOnTheOverviewPanelAlone(): void
    {
        $this->screen();

        $crawler = $this->client->request('GET', '/departments/'.$this->uuidOf('Ecology'));

        $factbands = $crawler->filter('.factband');
        self::assertCount(1, $factbands, 'exactly one identity card');
        self::assertSame(
            'overview',
            $factbands->closest('[data-tab-panel]')?->attr('data-tab-panel'),
            'the identity card is inside the Overview panel',
        );
        // The scope strip is NOT inside any tab panel — it sits above the tabs,
        // so scope reads on every tab.
        self::assertNull($crawler->filter('.scoperule')->first()->closest('[data-tab-panel]'));
    }

    /** KPIs, when the follow-up wires them, are laid on a single row (kstrip). */
    public function testThePerformanceKpisSitOnASingleRow(): void
    {
        $this->screen();

        $crawler = $this->client->request('GET', '/departments/'.$this->uuidOf('Ecology'));

        self::assertGreaterThan(0, $crawler->filter('[data-tab-panel="performance"] .grid.kstrip')->count());
    }

    /**
     * EVERY CARD SITS IN A ROW OF THE PAGE GRID, because the card declares no
     * margin and never has: `.c` is a plate, `.grid` is the composition, and the
     * 20px between two cards is the grid's gap. A `.c` written straight into the
     * page body therefore touches whatever follows it — which is what the lens
     * did, with four card edges meeting at 0px and the position note pinned to
     * the card above it.
     */
    public function testEveryCardOnTheLensSitsInARowOfThePageGrid(): void
    {
        $this->screen();

        $crawler = $this->client->request('GET', '/departments/'.$this->uuidOf('Ecology'));

        $cards = $crawler->filter('.pgbody .c');
        self::assertGreaterThanOrEqual(5, $cards->count(), 'the lens draws a card on every one of its tabs');

        foreach ($cards as $card) {
            $node = new Crawler($card);
            $label = $node->filter('.tab')->text('?');

            self::assertNotNull(
                $node->closest('.grid'),
                \sprintf('the card "%s" is in no .grid row, so nothing declares the gap under it', $label),
            );
            self::assertStringContainsString(
                'grid',
                (string) ($card->parentNode instanceof \DOMElement ? $card->parentNode->getAttribute('class') : ''),
                \sprintf('the card "%s" is a bare child of the page body rather than a cell of a grid row', $label),
            );
        }
    }

    // ---- create, per-area and per-org -------------------------------------

    /**
     * THE CREATE PICKER ENUMERATES THE INSTALLATION'S AREAS through the contract —
     * every area is an option, by name.
     */
    public function testTheCreatePickerListsEveryAreaByName(): void
    {
        $this->administrator();
        $this->area('Northern Reserve');
        $this->area('Western Reserve');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/departments');

        $options = $crawler->filter('form[data-create-department] select[name="area"] option')
            ->each(static fn (Crawler $c): string => $c->text());

        self::assertContains('Northern Reserve', $options);
        self::assertContains('Western Reserve', $options);
    }

    /** CREATE PER-AREA — the picked area becomes the department's scope. */
    public function testCreatingADepartmentPerAreaConfinesItToThePickedArea(): void
    {
        $this->administrator();
        $north = $this->area('Northern Reserve');
        $this->area('Western Reserve');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/departments');
        $form = $crawler->selectButton('Add department')->form();
        $form['scope'] = 'area';
        $form['area'] = (string) $north->getUuidString();
        $form['name'] = 'Wetland Management';
        $this->client->submit($form);

        self::assertResponseRedirects('/departments');

        $this->em->clear();
        $created = $this->em->getRepository(Department::class)->findOneBy(['name' => 'Wetland Management']);
        self::assertInstanceOf(Department::class, $created);
        self::assertTrue($created->isAreaLevel());
        self::assertSame('Northern Reserve', $created->getArea()?->getName());
    }

    /** CREATE PER-ORG — no area, spans every one. */
    public function testCreatingADepartmentPerOrgLeavesItOrgWide(): void
    {
        $this->administrator();
        $this->area('Northern Reserve');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/departments');
        $form = $crawler->selectButton('Add department')->form();
        $form['scope'] = 'org';
        $form['name'] = 'Administration';
        $this->client->submit($form);

        self::assertResponseRedirects('/departments');

        $this->em->clear();
        $created = $this->em->getRepository(Department::class)->findOneBy(['name' => 'Administration']);
        self::assertInstanceOf(Department::class, $created);
        self::assertTrue($created->isOrgLevel());
        self::assertNull($created->getArea());
    }

    /** A name may repeat FROM ONE AREA TO ANOTHER — two areas may each run one. */
    public function testTheSameNameMayExistInTwoDifferentAreas(): void
    {
        $this->administrator();
        $north = $this->area('Northern Reserve');
        $west = $this->area('Western Reserve');
        $this->areaDepartment('Anti-Poaching', $north);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/departments');
        $form = $crawler->selectButton('Add department')->form();
        $form['scope'] = 'area';
        $form['area'] = (string) $west->getUuidString();
        $form['name'] = 'Anti-Poaching';
        $this->client->submit($form);

        $this->em->clear();
        self::assertCount(2, $this->em->getRepository(Department::class)->findBy(['name' => 'Anti-Poaching']));
    }

    /** But two ORG-WIDE departments of one name are the same one entered twice. */
    public function testASecondOrgWideDepartmentWithTheSameNameIsRefused(): void
    {
        $crawler = $this->screen();

        $form = $crawler->selectButton('Add department')->form();
        $form['scope'] = 'org';
        $form['name'] = 'Ecology';
        $this->client->submit($form);

        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('already', $crawler->filter('[data-shell-flash]')->text());

        $this->em->clear();
        self::assertCount(1, $this->em->getRepository(Department::class)->findBy(['name' => 'Ecology']));
    }

    public function testADepartmentWithNoNameIsRefused(): void
    {
        $this->administrator();
        $this->area('Northern Reserve');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/departments');
        $this->client->submit($crawler->selectButton('Add department')->form());

        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('needs a name', $crawler->filter('[data-shell-flash]')->text());
        self::assertCount(0, $this->em->getRepository(Department::class)->findAll());
    }

    // ---- the audited scope change -----------------------------------------

    /**
     * CONFINE (org → area) — the department narrows to a picked area, and the
     * reason is recorded to the audit trail on the transition.
     */
    public function testConfiningAnOrgDepartmentToAnAreaRecordsAnAuditedReason(): void
    {
        $crawler = $this->screen();

        $form = $this->row($crawler, 'Ecology')->filter('form[action$="/scope"]')->selectButton('Confine to area')->form();
        $form['area'] = (string) $this->uuidOf('__area:Northern Reserve');
        $form['reason'] = 'Ecology now works only in the north.';
        $this->client->submit($form);

        self::assertResponseRedirects('/departments');

        $this->em->clear();
        $ecology = $this->em->getRepository(Department::class)->findOneBy(['name' => 'Ecology']);
        self::assertInstanceOf(Department::class, $ecology);
        self::assertTrue($ecology->isAreaLevel());
        self::assertSame('Northern Reserve', $ecology->getArea()?->getName());

        $trail = $this->scopeChanges()->findForDepartment($ecology);
        self::assertCount(1, $trail);
        self::assertSame('Ecology now works only in the north.', $trail[0]->getReason());
        self::assertSame('Naomi', $trail[0]->getChangedBy()?->getFirstName(), 'the audit line records who');
    }

    /** PROMOTE (area → org) — the department widens, still audited with a reason. */
    public function testPromotingAnAreaDepartmentToOrgWideRecordsAnAuditedReason(): void
    {
        $crawler = $this->screen();

        $form = $this->row($crawler, 'Wetland Management')->filter('form[action$="/scope"]')->selectButton('Promote to org-wide')->form();
        $form['reason'] = 'Its remit is now the whole park.';
        $this->client->submit($form);

        self::assertResponseRedirects('/departments');

        $this->em->clear();
        $wetland = $this->em->getRepository(Department::class)->findOneBy(['name' => 'Wetland Management']);
        self::assertInstanceOf(Department::class, $wetland);
        self::assertTrue($wetland->isOrgLevel());

        $trail = $this->scopeChanges()->findForDepartment($wetland);
        self::assertCount(1, $trail);
        self::assertSame('Its remit is now the whole park.', $trail[0]->getReason());
    }

    /** A BLANK REASON IS REFUSED, and the scope does not move. */
    public function testAScopeChangeWithNoReasonIsRefusedAndNothingMoves(): void
    {
        $crawler = $this->screen();

        $form = $this->row($crawler, 'Wetland Management')->filter('form[action$="/scope"]')->selectButton('Promote to org-wide')->form();
        $form['reason'] = '   ';
        $this->client->submit($form);

        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('needs a reason', $crawler->filter('[data-shell-flash]')->text());

        $this->em->clear();
        $wetland = $this->em->getRepository(Department::class)->findOneBy(['name' => 'Wetland Management']);
        self::assertInstanceOf(Department::class, $wetland);
        self::assertTrue($wetland->isAreaLevel(), 'the refusal happens before the scope moves');
        self::assertCount(0, $this->scopeChanges()->findForDepartment($wetland));
    }

    // ---- rename and filing still work -------------------------------------

    public function testARowRenamesItsDepartmentFromThePanel(): void
    {
        $crawler = $this->screen();

        $form = $this->row($crawler, 'Ecology')->filter('form[action$="/rename"]')->selectButton('Rename')->form();
        $form['name'] = 'Ecology & Research';
        $this->client->submit($form);

        self::assertResponseRedirects('/departments');

        $this->em->clear();
        self::assertNull($this->em->getRepository(Department::class)->findOneBy(['name' => 'Ecology']));
        self::assertInstanceOf(Department::class, $this->em->getRepository(Department::class)->findOneBy(['name' => 'Ecology & Research']));
    }

    public function testAPositionIsFiledIntoADepartmentFromItsMoveControl(): void
    {
        $crawler = $this->screen();

        // The loose position sits in the "No department yet" group; move it into Ecology.
        $form = $crawler->filter('[data-unfiled] .posline')->selectButton('Move')->form();
        $form['department'] = $this->uuidOf('Ecology');
        $this->client->submit($form);

        self::assertResponseRedirects('/departments');

        $this->em->clear();
        $volunteer = $this->em->getRepository(Position::class)->findOneBy(['name' => 'Volunteer']);
        self::assertSame('Ecology', $volunteer?->getDepartment()?->getName());
    }

    // ---- deactivate, never delete -----------------------------------------

    /** DELETE is never drawn; DEACTIVATE is — the standing fleet rule. */
    public function testTheScreenDeactivatesButNeverDeletesADepartment(): void
    {
        $crawler = $this->screen();

        $page = $crawler->filter('[data-dp]')->text();
        self::assertStringNotContainsString('Delete', $page);
        self::assertStringContainsString('Deactivate', $page);
    }

    /**
     * DEACTIVATING flips the flag without deleting: the row stays (greyed), its
     * positions keep their filing, and the footprint informs in the flash.
     */
    public function testDeactivatingADepartmentGreysItAndKeepsEverythingFiled(): void
    {
        $crawler = $this->screen();

        $form = $this->row($crawler, 'Ecology')->filter('form[action$="/deactivate"]')->selectButton('Deactivate anyway')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/departments');
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('deactivated', $crawler->filter('[data-shell-flash]')->text());

        $this->em->clear();
        $ecology = $this->em->getRepository(Department::class)->findOneBy(['name' => 'Ecology']);
        self::assertInstanceOf(Department::class, $ecology);
        self::assertFalse($ecology->isActive());
        self::assertNotNull($ecology->getDeactivatedAt());

        // The position filed under it keeps its filing — deactivate, never delete.
        $analyst = $this->em->getRepository(Position::class)->findOneBy(['name' => 'Analyst', 'department' => $ecology->getId()]);
        self::assertInstanceOf(Position::class, $analyst);

        // Greyed in the register.
        self::assertStringContainsString('dcinactive', (string) $this->row($crawler, 'Ecology')->attr('class'));
    }

    /** A DEACTIVATED department is dropped from the position move control. */
    public function testADeactivatedDepartmentIsHiddenFromTheMoveControl(): void
    {
        $crawler = $this->screen();
        $form = $this->row($crawler, 'Ecology')->filter('form[action$="/deactivate"]')->selectButton('Deactivate anyway')->form();
        $this->client->submit($form);

        $crawler = $this->client->request('GET', '/departments');
        $options = $crawler->filter('[data-unfiled] .moveform select[name="department"] option')
            ->each(static fn (Crawler $c): string => $c->text());

        self::assertNotContains('Ecology', $options, 'a wound-down department takes no new filings');
    }

    /** REACTIVATE brings it back into the register and the pickers. */
    public function testReactivatingADepartmentBringsItBack(): void
    {
        $crawler = $this->screen();
        $this->client->submit($this->row($crawler, 'Ecology')->filter('form[action$="/deactivate"]')->selectButton('Deactivate anyway')->form());

        $crawler = $this->client->request('GET', '/departments');
        $this->client->submit($this->row($crawler, 'Ecology')->filter('form[action$="/reactivate"]')->selectButton('Reactivate')->form());

        self::assertResponseRedirects('/departments');

        $this->em->clear();
        $ecology = $this->em->getRepository(Department::class)->findOneBy(['name' => 'Ecology']);
        self::assertInstanceOf(Department::class, $ecology);
        self::assertTrue($ecology->isActive());
        self::assertNull($ecology->getDeactivatedAt());
    }

    /** The deactivate write is gated like every other. */
    public function testTheDeactivateWriteIsGated(): void
    {
        $department = $this->department('Ecology');
        $this->em->flush();

        $this->client->request('POST', '/departments/'.$department->getUuidString().'/deactivate');
        self::assertResponseRedirects('http://localhost/login');
    }

    // ---- §5.7: a scope change is a privilege change -----------------------

    /** PROMOTION informs that everyone filed under it gains authority everywhere. */
    public function testPromotingNoticesThatAuthorityWidensToEveryArea(): void
    {
        $this->administrator();
        $ng = $this->area('Northern Reserve');
        $wetland = $this->areaDepartment('Wetland Management', $ng);
        $position = $this->position('Wetland Ecologist', $wetland, [PermissionEnum::AreaView->value]);
        $this->person('Zawadi', 'Kimaro', TeamRoleEnum::Staff)->setPosition($position);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/departments');
        $form = $this->row($crawler, 'Wetland Management')->filter('form[action$="/scope"]')->selectButton('Promote to org-wide')->form();
        $form['reason'] = 'Its remit is now the whole park.';
        $this->client->submit($form);

        $crawler = $this->client->followRedirect();
        self::assertStringContainsStringIgnoringCase('every area', $crawler->filter('[data-shell-flash]')->text());
    }

    /** DEMOTION (confine) informs that authority elsewhere is lost. */
    public function testConfiningNoticesThatAuthorityElsewhereIsLost(): void
    {
        $this->administrator();
        $ng = $this->area('Northern Reserve');
        $ecology = $this->department('Ecology');
        $position = $this->position('Analyst', $ecology, [PermissionEnum::AreaView->value]);
        $this->person('Zawadi', 'Kimaro', TeamRoleEnum::Staff)->setPosition($position);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/departments');
        $form = $this->row($crawler, 'Ecology')->filter('form[action$="/scope"]')->selectButton('Confine to area')->form();
        $form['area'] = (string) $ng->getUuidString();
        $form['reason'] = 'Ecology now works only in the north.';
        $this->client->submit($form);

        $crawler = $this->client->followRedirect();
        self::assertStringContainsStringIgnoringCase('lost', $crawler->filter('[data-shell-flash]')->text());
    }

    /** The promote form carries the §5.7 privilege-gain notice (informs, never guards). */
    public function testThePromoteFormStatesThePrivilegeGain(): void
    {
        $crawler = $this->screen();

        $form = $this->row($crawler, 'Wetland Management')->filter('form[action$="/scope"]');
        self::assertStringContainsStringIgnoringCase('every area', $form->text());
    }

    // ---- §5.6: what an area administrator may touch -----------------------

    /** An area-X admin CREATES an area-level department in their own area. */
    public function testAnAreaAdminCreatesAnAreaDepartmentInTheirOwnArea(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);

        $crawler = $this->client->request('GET', '/departments');
        $form = $crawler->selectButton('Add department')->form();
        $form['scope'] = 'area';
        $form['area'] = (string) $north->getUuidString();
        $form['name'] = 'Wetland Ecology';
        $this->client->submit($form);

        self::assertResponseRedirects('/departments');
        $this->em->clear();
        $created = $this->em->getRepository(Department::class)->findOneBy(['name' => 'Wetland Ecology']);
        self::assertInstanceOf(Department::class, $created);
        self::assertTrue($created->isAreaLevel());
    }

    /** But NOT an org-level one — minting an org department is escalation. */
    public function testAnAreaAdminCannotCreateAnOrgDepartment(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);

        $crawler = $this->client->request('GET', '/departments');
        $form = $crawler->selectButton('Add department')->form();
        $form['scope'] = 'org';
        $form['name'] = 'Administration';
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertNull($this->em->getRepository(Department::class)->findOneBy(['name' => 'Administration']));
    }

    /** And NOT a department in another area. */
    public function testAnAreaAdminCannotCreateADepartmentInAnotherArea(): void
    {
        $north = $this->area('Northern Reserve');
        $west = $this->area('Western Reserve');
        $this->areaAdminIn($north);

        $crawler = $this->client->request('GET', '/departments');
        $form = $crawler->selectButton('Add department')->form();
        $form['scope'] = 'area';
        $form['area'] = (string) $west->getUuidString();
        $form['name'] = 'Anti-Poaching';
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(403);
    }

    /** An area admin may NOT change any department's scope. */
    public function testAnAreaAdminCannotChangeScope(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);

        // Their own area-level department — even so, scope change is unbounded.
        $crawler = $this->client->request('GET', '/departments');
        $form = $this->row($crawler, 'Warden Office')->filter('form[action$="/scope"]')->selectButton('Promote to org-wide')->form();
        $form['reason'] = 'trying to widen';
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(403);
    }

    /** An area admin may deactivate their OWN area department, not an org one. */
    public function testAnAreaAdminDeactivatesTheirOwnAreaDepartmentButNotAnOrgOne(): void
    {
        $north = $this->area('Northern Reserve');
        $this->areaAdminIn($north);
        $ecology = $this->department('Ecology'); // org-level
        $this->em->flush();

        // Own area department: allowed.
        $crawler = $this->client->request('GET', '/departments');
        $this->client->submit($this->row($crawler, 'Warden Office')->filter('form[action$="/deactivate"]')->selectButton('Deactivate anyway')->form());
        self::assertResponseRedirects('/departments');

        // Org department: refused.
        $this->client->request('POST', '/departments/'.$ecology->getUuidString().'/deactivate', [
            '_token' => $this->tokenFrom('/departments'),
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    // ---- who may reach it -------------------------------------------------

    public function testAColleagueWithoutTeamManageIsRefused(): void
    {
        $ranger = $this->person('Juma', 'Mwakalinga', TeamRoleEnum::Staff);
        $ranger->setPosition($this->position('Ranger', $this->department('Protection'), [PermissionEnum::AreaView->value]));
        $this->em->flush();
        $this->client->loginUser($ranger);

        $this->client->request('GET', '/departments');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAnAnonymousVisitorIsSentToSignIn(): void
    {
        $this->client->request('GET', '/departments');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testTheScopeChangeWriteIsGated(): void
    {
        // No login: the client from setUp is anonymous until loginUser() is
        // called, and the audited scope-change route must refuse it at the door.
        $department = $this->department('Ecology');
        $this->em->flush();

        $this->client->request('POST', '/departments/'.$department->getUuidString().'/scope');
        self::assertResponseRedirects('http://localhost/login');
    }

    // ---- the cast ---------------------------------------------------------

    /**
     * The register's cast: one area (Northern Reserve), an area-level department in it
     * (Wetland Management), two org-level (Ecology, Protection Service — the twin
     * Analysts the per-scope-uniqueness ruling exists for), and one loose
     * position nobody has filed.
     */
    private function screen(): Crawler
    {
        $this->administrator();

        $this->north = $this->area('Northern Reserve');

        $wetland = $this->areaDepartment('Wetland Management', $this->north);
        $ecology = $this->department('Ecology');
        $protection = $this->department('Protection Service');

        $this->position('Wetland Ecologist', $wetland);
        $this->position('Analyst', $ecology);
        $this->position('Analyst', $protection);
        $this->position('Volunteer', null);

        $this->em->flush();

        return $this->client->request('GET', '/departments');
    }

    /**
     * Sign in as an AREA-X administrator — a Staff member whose team.manage
     * comes through a position in an AREA-LEVEL department confined to $area, so
     * their authority-area is $area. They own a "Warden Office" department there
     * to act on.
     */
    private function areaAdminIn(\Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Area\HostArea $area): \Uhifadhi\Bundle\TeamBundle\Entity\User
    {
        $office = $this->areaDepartment('Warden Office', $area);
        $admin = $this->person('Amina', 'Salehe', TeamRoleEnum::Staff);
        $admin->setPosition($this->position('Warden', $office, [PermissionEnum::TeamManage->value]));
        $this->em->flush();
        $this->client->loginUser($admin);

        return $admin;
    }

    private ?\Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Area\HostArea $north = null;

    private function row(Crawler $crawler, string $name): Crawler
    {
        return $crawler->filter('[data-dp] .dcard')
            ->reduce(static fn (Crawler $c): bool => $name === $c->filter('.ov-nm')->text())
            ->first();
    }

    /** The uuid of a department by name — or, for "__area:Name", of an area. */
    private function uuidOf(string $name): string
    {
        if (str_starts_with($name, '__area:')) {
            $area = $this->em->getRepository(\Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures\Area\HostArea::class)
                ->findOneBy(['name' => substr($name, 7)]);
            self::assertNotNull($area);

            return (string) $area->getUuidString();
        }

        $department = $this->em->getRepository(Department::class)->findOneBy(['name' => $name]);
        self::assertInstanceOf(Department::class, $department);

        return (string) $department->getUuidString();
    }

    private function scopeChanges(): DepartmentScopeChangeRepository
    {
        /** @var DepartmentScopeChangeRepository $repo */
        $repo = static::getContainer()->get('test_public.'.DepartmentScopeChangeRepository::class);

        return $repo;
    }
}
