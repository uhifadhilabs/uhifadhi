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
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;

/**
 * THE GRANTS MATRIX, RENDERED AND SAVED.
 *
 * IT USED TO BE A LIST OF FLAT PERMISSION VALUES under an umbrella the
 * catalogue invented. A grant is a (concern, verb) pair now, so the screen is
 * a matrix in the literal sense: one group per DECLARER, a row per CONCERN,
 * the six verbs as columns, and a cell only where the concern declares the
 * verb. What is asserted here is what a template can get that wrong about:
 *
 *   · the groups are CLEARLY BOUNDED and each names who declared it;
 *   · every row carries the sentence that says what the concern is about;
 *   · the columns are the six verbs, and there is NO CELL where a concern
 *     declares no verb — a checkbox that would mean nothing;
 *   · a position that grants nothing says so in those words;
 *   · an orphaned grant is drawn muted and SURVIVES a save that does not touch
 *     it, because editing a position is not a migration;
 *   · a module-declared pair round-trips through the save.
 *
 * THE CREATE FORM USED TO ASK FOR A DEPARTMENT FIRST and the name was unique
 * inside it; the ruling took the department off the position, so the form asks
 * for a name and the organization holds one of each.
 */
final class PositionMatrixTest extends WebTestCaseWithSchema
{
    /**
     * THE HEADER IS THE SECTION'S, NOT THE SCREEN'S. A section wears the area
     * idiom: every tab is headed "Team" and the strip says which one you are
     * on, so the matrix is identified by its lit tab rather than by a title
     * that changes under the reader.
     */
    public function testTheMatrixPageRenders(): void
    {
        $this->administrator();
        $crawler = $this->client->request('GET', '/team/positions');

        self::assertResponseIsSuccessful();
        self::assertSame('Team', $crawler->filter('h1.pg')->text());
        self::assertSame('Positions', $crawler->filter('.atabs a.on')->text());
    }

    /**
     * FOUR TO A ROW, and this fold already was — pinned so it stays that way.
     * A figure row is four (ruled); five wrapped an orphan onto a second line
     * on a small laptop, and the way a strip grows a fifth is one card at a
     * time with nobody counting.
     */
    public function testThePositionsFoldIsFourCards(): void
    {
        $this->administrator();
        $crawler = $this->client->request('GET', '/team/positions');

        $cards = $crawler->filter('.kstrip .c.kpi');
        self::assertCount(4, $cards, 'A figure row is four to a row, and never five.');
        self::assertSame(
            ['Positions', 'Placement', 'Grantable', 'Above the matrix'],
            $cards->filter('.tab')->each(static fn (Crawler $c): string => $c->text()),
        );
    }

    /**
     * GATED ON READING THE REGISTER — `positions.read`, not the flat
     * `team.manage` it used to name. A Staff member with no position holds
     * nothing at all and is refused.
     */
    public function testItIsGatedOnReadingThePositionsRegister(): void
    {
        $frank = $this->person('Frank', 'Massawe');
        $this->em->flush();
        $this->client->loginUser($frank);

        $this->client->request('GET', '/team/positions');

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * B SHIPS SELECTED, and its empty state is the argument: on day one there
     * are no positions, and B is the only direction whose empty state is a
     * control that makes the first one.
     */
    public function testWithNoPositionsTheShippedDirectionSaysSoRatherThanDrawingNothing(): void
    {
        $this->administrator();
        $crawler = $this->client->request('GET', '/team/positions');

        self::assertCount(1, $crawler->filter('[data-pm="b"]'));
        self::assertStringContainsString('There are no positions yet', $crawler->html());
    }

    /**
     * ONE GROUP PER DECLARER, BOUNDED AND WEARING THE PACKAGE THAT DECLARED
     * IT. Whoever enforces a concern declares it, so a reader has to be able
     * to see where the team's concerns end and a module's begin — and which
     * package removing would take a group away.
     */
    public function testEachDeclarerIsABoundedGroupNamingWhoDeclaredIt(): void
    {
        $this->administrator();
        $this->position('Ranger', ['surveys.read']);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions');

        // The team's own concerns, and the ones the surveys fixture declares.
        $groups = $crawler->filter('[data-pm="b"] .pm-group');
        self::assertGreaterThanOrEqual(2, $groups->count());

        $declarers = $crawler->filter('[data-pm="b"] .pm-umb > b')->each(static fn (Crawler $c): string => $c->text());
        self::assertContains('Team', $declarers);
        // The declaring package's own word, from its own declaration — never
        // a word this page invented.
        self::assertContains('Surveys', $declarers);
    }

    /**
     * THE SIX VERBS ARE THE COLUMNS, in their fixed order, in every group —
     * a matrix whose columns differ per group is one nobody can read across.
     */
    public function testTheColumnsAreTheSixVerbsInEveryGroup(): void
    {
        $this->administrator();
        $this->position('Ranger', []);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions');

        $tables = $crawler->filter('[data-pm="b"] .pm-group table.pm-grid');
        self::assertGreaterThanOrEqual(2, $tables->count());

        foreach ($tables as $table) {
            $heads = new Crawler($table)->filter('thead th.pm-verbh')->each(static fn (Crawler $c): string => $c->text());
            self::assertSame(['Read', 'Record', 'Manage', 'Configure', 'Delete', 'Export'], $heads);
        }
    }

    /**
     * NO CELL WHERE THE CONCERN DECLARES NO VERB. Positions supports read and
     * configure and nothing else, so there is no box an administrator could
     * tick that would mean nothing — and the four gaps are drawn as gaps.
     */
    public function testThereIsNoCheckboxWhereAConcernDeclaresNoVerb(): void
    {
        $this->administrator();
        $this->position('Ranger', []);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions');

        foreach (['positions.read', 'positions.configure'] as $declared) {
            self::assertCount(1, $crawler->filter('[data-pm="b"] input.pm-check[value="'.$declared.'"]'), $declared.' is declared and has no cell.');
        }

        foreach (['positions.record', 'positions.manage', 'positions.delete', 'positions.export'] as $never) {
            self::assertCount(0, $crawler->filter('[data-pm="b"] input.pm-check[value="'.$never.'"]'), $never.' is a box that would mean nothing.');
        }
    }

    /** THE DESCRIPTION IS PRINTED UNDER THE NAME. That is the whole of the ruling. */
    public function testEveryRowCarriesItsSentence(): void
    {
        $this->administrator();
        $this->position('Ranger', []);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions');

        self::assertStringContainsString(
            'The positions the organization has written, what each one grants, and who holds it.',
            $crawler->filter('[data-pm="b"]')->html(),
        );
        // And the declaring module's own sentence reaches the page unchanged.
        self::assertStringContainsString(
            'The surveys this module records and the figures it publishes.',
            $crawler->filter('[data-pm="b"]')->html(),
        );
    }

    /**
     * A SENSITIVE CONCERN IS MARKED. Personal details are a fact about a
     * person an organization may reasonably want withheld without withholding
     * the page they sit on, and an administrator should be told that before
     * the click rather than after.
     */
    public function testASensitiveConcernIsMarkedAsOne(): void
    {
        $this->administrator();
        $this->position('Ranger', []);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions');

        self::assertGreaterThan(0, $crawler->filter('[data-pm="b"] .pm-sens')->count(), 'Nothing on the matrix is marked sensitive.');
    }

    /**
     * A POSITION THAT GRANTS NOTHING SAYS SO IN THOSE WORDS. A blank matrix
     * and a position that grants nothing look identical, and only one of them
     * is a fact worth saying.
     */
    public function testAPositionThatGrantsNothingSaysSo(): void
    {
        $this->administrator();
        $ranger = $this->position('Ranger', []);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions?position='.$ranger->getUuidString());

        self::assertStringContainsString('This position grants nothing', $crawler->html());
    }

    public function testTickingACellGrantsItAndTheFlashSaysWhoItReaches(): void
    {
        $naomi = $this->administrator();
        $ranger = $this->position('Ranger', []);
        $grace = $this->person('Grace', 'Ndosi');
        $grace->setPosition($ranger);
        $this->em->flush();

        $token = $this->tokenFrom('/team/positions');
        $this->client->request('POST', '/team/positions/'.$ranger->getUuidString().'/permissions', [
            '_token' => $token,
            'grants' => ['directory.read', 'positions.configure'],
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        $stored = $this->em->getRepository(Position::class)->findOneBy(['name' => 'Ranger']);
        self::assertInstanceOf(Position::class, $stored);
        self::assertSame(['directory.read', 'positions.configure'], $stored->getGrantValues());

        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('reaches 1 person', $crawler->html());
        unset($naomi);
    }

    /**
     * THE WHOLE GESTURE, THROUGH THE PAGE ITSELF: open the matrix, tick a box,
     * press the button the page actually renders, and come back to find it
     * held.
     *
     * EVERY OTHER SAVE TEST HERE HAND-BUILDS ITS POST, and that is exactly how
     * a matrix nobody could save shipped green: the endpoint, the token, the
     * redirect and the persistence were all correct, and the screen was
     * unusable because the button was not reachable. A test that never asks
     * the page for its own form cannot see that.
     */
    public function testTickingACellAndPressingSaveOnTheRenderedPageGrantsIt(): void
    {
        $this->administrator();
        $ranger = $this->position('Ranger', []);
        $this->em->flush();

        $url = '/team/positions?position='.$ranger->getUuidString();
        $crawler = $this->client->request('GET', $url);

        // The page's own control, found the way a person finds it.
        $form = $crawler->filter('form.pane')->selectButton('Save')->form();

        /** @var list<ChoiceFormField> $boxes */
        $boxes = $form['grants'];
        foreach ($boxes as $box) {
            if (['directory.read'] === $box->availableOptionValues()) {
                $box->tick();
            }
        }

        $this->client->submit($form);

        self::assertResponseRedirects();

        $this->em->clear();
        $stored = $this->em->getRepository(Position::class)->findOneBy(['name' => 'Ranger']);
        self::assertInstanceOf(Position::class, $stored);
        self::assertSame(['directory.read'], $stored->getGrantValues(), 'The tick did not survive the save.');

        // And it comes back ticked, so a reload shows what was granted.
        $reloaded = $this->client->request('GET', $url);
        self::assertCount(
            1,
            $reloaded->filter('input.pm-check[value="directory.read"][checked]'),
            'The saved grant is not drawn as held when the page is reloaded.',
        );
    }

    /** A MODULE-DECLARED PAIR ROUND-TRIPS, which an enum-typed write could not do. */
    public function testAModuleDeclaredPairRoundTripsThroughTheSave(): void
    {
        $this->administrator();
        $ranger = $this->position('Ranger', []);
        $this->em->flush();

        $token = $this->tokenFrom('/team/positions');
        $this->client->request('POST', '/team/positions/'.$ranger->getUuidString().'/permissions', [
            '_token' => $token,
            'grants' => ['surveys.record'],
        ]);

        $this->em->clear();
        $stored = $this->em->getRepository(Position::class)->findOneBy(['name' => 'Ranger']);
        self::assertInstanceOf(Position::class, $stored);
        self::assertSame(['surveys.record'], $stored->getGrantValues(), 'A module\'s grant is not silently dropped.');
    }

    /**
     * PRUNE, NOT PURGE, THROUGH THE WHOLE ROUND TRIP: an orphan is drawn muted,
     * posted back by the form, and survives a save that adds something else.
     */
    public function testAnOrphanedGrantIsDrawnAndSurvivesASave(): void
    {
        $this->administrator();
        $botanist = new Position()->setName('Botanist');
        // How it got there: the module was installed at the time.
        $botanist->setGrantValues(['vegetation.record'], ['vegetation.record']);
        $this->em->persist($botanist);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions?position='.$botanist->getUuidString());

        self::assertStringContainsString('no longer described', $crawler->html());
        self::assertStringContainsString('vegetation.record', $crawler->html());
        self::assertStringContainsString('no installed module declares it', $crawler->html());

        // The form posts it back, so an unrelated save keeps it.
        $token = $this->tokenFrom('/team/positions?position='.$botanist->getUuidString());
        $this->client->request('POST', '/team/positions/'.$botanist->getUuidString().'/permissions', [
            '_token' => $token,
            'grants' => ['vegetation.record', 'directory.read'],
        ]);

        $this->em->clear();
        $stored = $this->em->getRepository(Position::class)->findOneBy(['name' => 'Botanist']);
        self::assertInstanceOf(Position::class, $stored);
        self::assertContains('vegetation.record', $stored->getGrantValues());
        self::assertContains('directory.read', $stored->getGrantValues());
    }

    /** And it can still be taken away: it is a grant, not a fixture. */
    public function testAnOrphanedGrantCanBeRevoked(): void
    {
        $this->administrator();
        $botanist = new Position()->setName('Botanist');
        $botanist->setGrantValues(['vegetation.record'], ['vegetation.record']);
        $this->em->persist($botanist);
        $this->em->flush();

        $token = $this->tokenFrom('/team/positions?position='.$botanist->getUuidString());
        $this->client->request('POST', '/team/positions/'.$botanist->getUuidString().'/permissions', [
            '_token' => $token,
            'grants' => [],
        ]);

        $this->em->clear();
        $stored = $this->em->getRepository(Position::class)->findOneBy(['name' => 'Botanist']);
        self::assertInstanceOf(Position::class, $stored);
        self::assertSame([], $stored->getGrantValues());
    }

    /**
     * ONE NAME, ONE POSITION, ORGANIZATION-WIDE. A position belongs to no
     * department, so a second "Analyst" is the same job entered twice however
     * the organization is drawn on a chart — and the refusal says exactly
     * that, because an administrator who was used to filing one per department
     * needs to be told what changed rather than left staring at a form.
     */
    public function testASecondPositionOfTheSameNameIsRefusedAndTheSentenceSaysWhy(): void
    {
        $this->administrator();

        $token = $this->tokenFrom('/team/positions');
        $this->client->request('POST', '/team/positions', ['_token' => $token, 'name' => 'Analyst']);
        $this->client->request('POST', '/team/positions', ['_token' => $token, 'name' => 'Analyst']);

        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful('The matrix has to come back, or the refusal is a crash rather than a sentence.');
        self::assertStringContainsString('This organization already has a position called', $crawler->html());
        self::assertStringContainsString('A position belongs to no department', $crawler->html());

        $this->em->clear();
        self::assertCount(1, $this->em->getRepository(Position::class)->findBy(['name' => 'Analyst']));
    }

    /** Two different names are two positions, written from the same form. */
    public function testTwoDifferentNamesAreTwoPositions(): void
    {
        $this->administrator();

        $token = $this->tokenFrom('/team/positions');
        foreach (['Analyst', 'Ranger'] as $name) {
            $this->client->request('POST', '/team/positions', [
                '_token' => $token, 'name' => $name,
            ]);
        }

        $this->em->clear();
        self::assertCount(2, $this->em->getRepository(Position::class)->findAll());
    }

    /**
     * BOTH WIDGET LIBRARIES RENDER. Asserted because they were the one pair of
     * routes no other test opened, and a real install found them broken: the
     * library's action URLs carry a placeholder uuid, and Requirement::UUID
     * refuses a nil one, so the router threw at RENDER time and took the whole
     * page down. A route that is never requested is a route that is untested.
     */
    public function testBothWidgetLibrariesRender(): void
    {
        $this->administrator();

        foreach (['/team/widgets', '/team/positions/widgets'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url.' does not render.');
        }
    }

    /**
     * EVERY DIRECTION STILL DRAWS. The five renderings are five readings of
     * ONE declaration, and the library is the only page that opens all of
     * them at once — so a direction that stopped compiling when the matrix
     * moved to pairs is caught here rather than by whoever adopts it.
     */
    public function testEveryDirectionRendersInTheLibrary(): void
    {
        $this->administrator();
        $this->position('Ranger', ['directory.read', 'surveys.read']);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/positions/widgets');

        self::assertResponseIsSuccessful();
        foreach (['a', 'b', 'c', 'd', 'e', 'cat'] as $direction) {
            self::assertGreaterThan(
                0,
                $crawler->filter('[data-pm="'.$direction.'"]')->count(),
                'Direction '.$direction.' does not render.',
            );
        }
    }

    /** A write with no token is refused rather than performed. */
    public function testASaveWithoutACsrfTokenIsRefused(): void
    {
        $this->administrator();
        $ranger = $this->position('Ranger', []);
        $this->em->flush();

        $this->client->request('POST', '/team/positions/'.$ranger->getUuidString().'/permissions', [
            'grants' => ['directory.read'],
        ]);

        self::assertResponseStatusCodeSame(404);
    }
}
