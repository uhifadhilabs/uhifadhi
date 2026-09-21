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
use Uhifadhi\Bundle\TeamBundle\Access\TeamConcerns;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Contracts\Access\ScopeKind;

/**
 * THE POSITIONS REGISTER — one collapsible card per position, and nothing
 * that edits.
 *
 * IT USED TO BE A WIDGET CANVAS: thirteen widgets and seven presets, five of
 * which were five renderings of one matrix. The ruled register is the
 * department card carried across — head, preview body, summary foot — and
 * the matrix lives once, on the position record. What is asserted here is
 * what a template can get wrong about that:
 *
 *   · the card's head states the SEATS and whether the next person is
 *     refused, which is the one thing a reader of a register is looking for;
 *   · the body is a PREVIEW — chips grouped by whoever declared the concern,
 *     sensitive ones marked — and carries no control that writes;
 *   · the foot counts by VERB and names the holders;
 *   · "grants nothing" is said in those words, because fail-closed is the
 *     model and an empty body would read as a rendering bug;
 *   · a RETIRED position is in the register, greyed, rather than hidden: an
 *     administrator told a name is taken has to be able to find the row
 *     holding it;
 *   · the scope filter is in the ADDRESS, so it is shareable.
 */
final class PositionRegisterTest extends WebTestCaseWithSchema
{
    /**
     * THE HEADER IS THE SECTION'S, NOT THE SCREEN'S. A section wears the area
     * idiom: every tab is headed "Team" and the strip says which one you are
     * on.
     */
    public function testTheRegisterRenders(): void
    {
        $this->administrator();
        $crawler = $this->client->request('GET', '/team/positions');

        self::assertResponseIsSuccessful();
        self::assertSame('Team', $crawler->filter('h1.pg')->text());
        self::assertSame('Positions', $crawler->filter('.atabs a.on')->text());
    }

    /**
     * EXACTLY ONE CONFIGURE ENTRY, AND THE FRAME WRITES IT. This page typed
     * its own as well, so the header drew the word twice — which is the whole
     * reason the frame owns the entry: "exactly one per surface" cannot be
     * true if every page may add one.
     */
    public function testTheHeaderDrawsOneConfigureEntryAndTheFrameOwnsIt(): void
    {
        $this->administrator();
        $crawler = $this->client->request('GET', '/team/positions');
        $actions = $crawler->filter('.pghead .pgact a');

        self::assertSame(
            ['Add a position', 'Configure'],
            $actions->each(static fn (Crawler $c): string => trim($c->text())),
        );
        self::assertSame('Configure', trim($actions->last()->text()), 'Configure is last in the row, always.');
    }

    /**
     * GATED ON READING THE REGISTER — `positions.read`. A Staff member with
     * no position holds nothing at all and is refused.
     */
    public function testItIsGatedOnReadingThePositionsRegister(): void
    {
        $frank = $this->person('Frank', 'Massawe');
        $this->em->flush();
        $this->client->loginUser($frank);

        $this->client->request('GET', '/team/positions');

        self::assertResponseStatusCodeSame(403);
    }

    /** On day one there are no positions, and the register says so. */
    public function testWithNoPositionsTheRegisterSaysSoRatherThanDrawingNothing(): void
    {
        $this->administrator();
        $crawler = $this->client->request('GET', '/team/positions');

        self::assertCount(0, $crawler->filter('.dcstack.dcreg .dcard'));
        self::assertStringContainsString('No positions yet', $crawler->html());
    }

    /** ONE CARD PER POSITION, by name, on the department register's idiom. */
    public function testThereIsOneCardPerPosition(): void
    {
        $this->administrator();
        $this->position('Sergeant');
        $this->position('Ranger');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions');

        self::assertSame(
            ['Ranger', 'Sergeant'],
            $crawler->filter('.dcstack.dcreg .dcard .ov-nm')->each(static fn (Crawler $c): string => $c->text()),
        );
    }

    /**
     * THE SEATS ARE ON THE HEAD, and a full one is marked as refusing the
     * next person — the whole reason the count is there.
     */
    public function testTheHeadStatesTheSeatsAndWhetherTheNextPersonIsRefused(): void
    {
        $this->administrator();
        $sergeant = $this->position('Sergeant')->setSeatCount(1);
        $this->person('Joseph', 'Mollel')->setPosition($sergeant);
        $this->position('Ranger');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions');
        $heads = $crawler->filter('.dcard .dc-sub')->each(static fn (Crawler $c): string => $c->text());

        self::assertStringContainsString('unlimited seats', $heads[0], 'Ranger seats anybody.');
        self::assertStringContainsString('1 of 1', $heads[1]);
        self::assertStringContainsString('full', $heads[1]);
    }

    /** A free seat is counted, not merely implied by the two numbers. */
    public function testAnOpenPositionNamesHowManySeatsAreFree(): void
    {
        $this->administrator();
        $sergeant = $this->position('Sergeant')->setSeatCount(8);
        $this->person('Joseph', 'Mollel')->setPosition($sergeant);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions');

        self::assertStringContainsString('7 free', $crawler->filter('.dcard .dc-sub')->text());
    }

    /**
     * THE BODY IS A PREVIEW, grouped by whoever declared the concern, with
     * the sensitive ones marked — and it carries nothing that writes. The
     * matrix lives once, on the record.
     */
    public function testTheBodyPreviewsTheConcernsGroupedByDeclarerAndMarksTheSensitiveOnes(): void
    {
        $this->administrator();
        $this->position('Sergeant', ['surveys.read', TeamConcerns::PERSONAL_DETAILS.'.read']);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions');

        self::assertSame(
            ['Team', 'Surveys'],
            $crawler->filter('.dcard .rggrid-k')->each(static fn (Crawler $c): string => $c->text()),
        );
        self::assertStringContainsString('Personal details', $crawler->filter('.dcard .rgchip.sens')->text());
        self::assertCount(0, $crawler->filter('.dcard input'), 'The register previews; it never edits.');
    }

    /** The foot counts by verb and names the holders. */
    public function testTheFootCountsByVerbAndCarriesTheHolders(): void
    {
        $this->administrator();
        $sergeant = $this->position('Sergeant', ['surveys.read', 'surveys.record']);
        $this->person('Joseph', 'Mollel')->setPosition($sergeant);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions');

        self::assertStringContainsString('reads 1', $crawler->filter('.dcard .rgsum-l')->text());
        self::assertStringContainsString('records 1', $crawler->filter('.dcard .rgsum-l')->text());
        self::assertSame('JM', $crawler->filter('.dcard .rgav')->text());
    }

    /**
     * FAIL CLOSED, AND SAID OUT LOUD. Nothing is granted by default, read
     * included, so a position may perfectly well grant nothing — and an
     * empty body would read as a rendering fault instead of as the model.
     */
    public function testAPositionThatGrantsNothingSaysSo(): void
    {
        $this->administrator();
        $this->position('Community Liaison');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions');

        self::assertStringContainsString('Grants nothing', $crawler->filter('.dcard .rgnone')->text());
        self::assertSame('grants nothing', $crawler->filter('.dcard .rgkinds .pmx-sk')->text());
    }

    /**
     * THE SCOPE FILTER IS IN THE ADDRESS, so the choice is shareable and it
     * survives a save's redirect.
     */
    public function testTheScopeFilterNarrowsTheRegisterAndLivesInTheAddress(): void
    {
        $this->administrator();
        $this->position('Sergeant', [], [ScopeKind::Organization]);
        $this->position('Ranger', [], [ScopeKind::Area]);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions?kind=area');

        self::assertSame(['Ranger'], $crawler->filter('.dcard .ov-nm')->each(static fn (Crawler $c): string => $c->text()));
        self::assertSame('area', $crawler->filter('.rgbar .rgf.on')->text());
    }

    /**
     * WHICH CARDS ARE OPEN IS THE SERVER'S ANSWER, so the label beside the
     * chevron cannot drift out of step with the card under it.
     */
    public function testWhichCardsAreOpenLivesInTheAddress(): void
    {
        $this->administrator();
        $this->position('Sergeant', ['surveys.read']);
        $this->em->flush();

        $open = $this->client->request('GET', '/team/positions');
        self::assertSame('Collapse', $open->filter('.dcard .xdisc .t')->text());
        self::assertCount(1, $open->filter('.dcard .dc-body'));

        $shut = $this->client->request('GET', '/team/positions?open=none');
        self::assertSame('Expand', $shut->filter('.dcard .xdisc .t')->text());
        self::assertCount(0, $shut->filter('.dcard .dc-body'));
    }

    /**
     * A RETIRED POSITION IS IN THE REGISTER, MARKED — not hidden. We do not
     * delete things, and an administrator told a name is taken has to be
     * able to find the row that is holding it.
     */
    public function testARetiredPositionIsDrawnMarkedRatherThanHidden(): void
    {
        $this->administrator();
        $this->position('Sergeant')->retire(new \DateTimeImmutable());
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions');

        self::assertCount(1, $crawler->filter('.dcard'));
        self::assertSame('retired', $crawler->filter('.dcard .chip.stoff')->text());
    }

    /**
     * THE CREATE CARD IS A POSITION'S TWO FACTS — a name and a seat count, as
     * the design draws it. No department, because a position belongs to none;
     * no allowed kinds either, those are set on the record's configure page,
     * so a new position keeps the entity's default until somebody goes there.
     */
    public function testTheCreateCardWritesTheNameAndTheSeats(): void
    {
        $this->administrator();
        $crawler = $this->client->request('GET', '/team/positions');

        self::assertCount(0, $crawler->filter('#add select[name="department"]'));
        self::assertCount(0, $crawler->filter('#add input[name="allows[]"]'), 'The kinds a position allows are not on the add row.');
        self::assertSame(['name', 'seats'], $crawler->filter('#add .crlab')->each(static fn ($l): string => strtolower(trim($l->text()))));

        $form = $crawler->filter('#add form')->form();
        $form['name'] = 'Sergeant';
        $form['seats'] = '8';
        $this->client->submit($form);

        $this->client->followRedirect();
        $crawler = $this->client->request('GET', '/team/positions');

        self::assertStringContainsString('0 of 8', $crawler->filter('.dcard .dc-sub')->text());
        self::assertStringContainsString('8 free', $crawler->filter('.dcard .dc-sub')->text());
        // It grants nothing yet, so the head's chip slot says that rather
        // than the kinds — the kinds it allows are read on the record.
        self::assertSame('grants nothing', $crawler->filter('.dcard .rgkinds .pmx-sk')->text());
        self::assertSame(
            [ScopeKind::Area],
            $this->em->getRepository(Position::class)->findOneBy(['name' => 'Sergeant'])?->getAllowedKinds(),
        );
    }

    /** The organization holds one of each name, and the refusal says so. */
    public function testASecondPositionOfTheSameNameIsRefusedInTheOrganizationsWords(): void
    {
        $this->administrator();
        $this->position('Sergeant');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions');
        $form = $crawler->filter('#add form')->form();
        $form['name'] = 'Sergeant';
        $this->client->submit($form);

        self::assertStringContainsString(
            'already has a position called',
            (string) $this->client->followRedirect()->filter('.flashes')->text(),
        );
    }

    /**
     * THE WIDGET SURFACE IS RETIRED, AND STILL ROUTED. Deleting a shipped
     * route 404s every bookmark in the release that changed the page, so the
     * names stay for one release and go to what replaced them.
     */
    public function testTheRetiredWidgetLibraryRedirectsToTheRegister(): void
    {
        $this->administrator();
        $this->client->request('GET', '/team/positions/widgets');

        self::assertResponseRedirects('/team/positions');
    }
}
