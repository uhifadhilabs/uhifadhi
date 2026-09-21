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
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;

/**
 * ONE PERSON'S RECORD, and the four things it can be asked to change.
 *
 * The states worth asserting are the ones the design argues about: the REFUSAL
 * on the last active Super Admin — printed with its reason where the control
 * would have been, rather than greyed out — the deliberately ABSENT delete, the
 * SA-grant warning at the grant, and the invitation facts line, which appears
 * only beside "never signed in" and reads differently for an account nobody
 * invited.
 */
final class MemberRecordTest extends WebTestCaseWithSchema
{
    /** A second Super Admin, so the invariant is not in the way of ordinary tests. */
    private function withSuccessor(): User
    {
        $naomi = $this->person('Naomi', 'Kileo', TeamRoleEnum::SuperAdmin);
        $this->person('Asha', 'Mollel', TeamRoleEnum::SuperAdmin);
        $this->em->flush();
        $this->client->loginUser($naomi);

        return $naomi;
    }

    public function testTheRecordRenders(): void
    {
        $this->withSuccessor();
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/'.$grace->getUuidString());

        self::assertResponseIsSuccessful();
        self::assertSame('Grace Ndosi', $crawler->filter('h1.pg')->text());
    }

    /**
     * EVERY CARD SITS IN A ROW OF THE PAGE GRID — the same rule the department
     * lens is held to. `.c` is a plate that declares no margin, `.grid` is the
     * composition that declares the 20px between two of them, and a card written
     * straight into the page body has to invent a spacing of its own.
     */
    /**
     * THE RECORD IS A SPLIT, and every card is on one side of it: the record
     * itself in the main column, and what happened to it and what may be done
     * to it in the rail. A card loose in the page body belongs to neither and
     * gets the gap of whatever happens to precede it.
     */
    public function testEveryCardOnTheRecordSitsInTheColumnOrTheRail(): void
    {
        $this->withSuccessor();
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/'.$grace->getUuidString());

        $cards = $crawler->filter('.pgbody .c');
        self::assertGreaterThanOrEqual(6, $cards->count(), 'the record draws a card per section');

        foreach ($cards as $card) {
            $node = new Crawler($card);
            $label = $node->filter('.tab')->text('?');
            $parent = $card->parentNode instanceof \DOMElement ? $card->parentNode->getAttribute('class') : '';

            self::assertMatchesRegularExpression(
                '/\b(mb-col|mb-rail)\b/',
                $parent,
                \sprintf('the card "%s" is loose in the page body rather than in the column or the rail', $label),
            );
        }
    }

    /** The rail carries the history and the account actions, and nothing else. */
    public function testTheRailCarriesTheHistoryAndTheAccountActions(): void
    {
        $this->withSuccessor();
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/'.$grace->getUuidString());

        self::assertSame(
            ['History', 'Account actions'],
            $crawler->filter('.mb-rail .c .tab')->each(
                static fn (Crawler $c): string => trim(str_replace($c->filter('.src')->text(''), '', $c->text())),
            ),
        );
    }

    /**
     * THE HISTORY IS DERIVED FROM STORED FACTS, newest first — there is no
     * audit trail in this release, so the card states what the model can date
     * and nothing it cannot.
     */
    public function testTheHistoryStatesWhatTheModelCanDate(): void
    {
        $naomi = $this->withSuccessor();
        $joseph = $this->person('Joseph', 'Mrema')->setVerified(false);
        $joseph->markInvitedBy($naomi);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/'.$joseph->getUuidString());
        $lines = $crawler->filter('.mb-log .mb-lrow b')->each(static fn (Crawler $c): string => $c->text());

        self::assertContains('Invited', $lines);
        self::assertContains('Account created', $lines);
    }

    /**
     * AN INVITATION NOBODY OPENED IS CHASED FROM THE RECORD, and the control
     * is OFFERED AND REFUSED where there is no transport — visible, inert,
     * with the reason on it. Hiding it would leave an administrator hunting
     * for a feature the product has; swallowing the click would leave a
     * colleague waiting for an email nobody sent.
     */
    public function testAnInvitationCanBeChasedFromTheRecordAndSaysWhenItCannotBeSent(): void
    {
        $naomi = $this->withSuccessor();
        $joseph = $this->person('Joseph', 'Mrema')->setVerified(false);
        $joseph->markInvitedBy($naomi);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/'.$joseph->getUuidString());
        $button = $crawler->filter('.mb-state button[type="submit"]')->first();

        self::assertStringContainsString('Send the link again', $button->text());
        // This kernel configures no mailer, which is the state a fresh
        // installation is in until somebody sets MAILER_DSN.
        self::assertNotNull($button->attr('disabled'));
        self::assertStringContainsString('MAILER_DSN', (string) $button->attr('title'));
    }

    /** And with no transport the write refuses rather than discarding silently. */
    public function testChasingAnInvitationWithNoMailerRefusesInsteadOfDiscarding(): void
    {
        $naomi = $this->withSuccessor();
        $joseph = $this->person('Joseph', 'Mrema')->setVerified(false);
        $joseph->markInvitedBy($naomi);
        $this->em->flush();
        $first = $joseph->getVerificationToken();

        $this->client->request('POST', '/team/'.$joseph->getUuidString().'/invite-again', [
            '_token' => $this->tokenFrom('/team/'.$joseph->getUuidString()),
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('No invitation was sent.', $this->client->followRedirect()->text());
        $this->em->clear();
        $again = $this->em->getRepository(User::class)->find($joseph->getId());
        self::assertInstanceOf(User::class, $again);
        self::assertSame($first, $again->getVerificationToken(), 'a refused resend rotated the token anyway');
    }

    /**
     * NOT OFFERED ONCE THEY HAVE SIGNED IN: the token is spent, the account is
     * theirs, and the way back in is a password reset.
     */
    public function testSomebodyWhoHasSignedInIsOfferedAResetAndNotAnInvitation(): void
    {
        $this->withSuccessor();
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/'.$grace->getUuidString());

        self::assertStringNotContainsString('Send the link again', $crawler->filter('.pgbody')->text());
        self::assertStringContainsString('Send a reset link', $crawler->filter('.mb-state')->text());
    }

    /**
     * A SCREEN DOES NOT NAME AN ACTION THAT DOES NOT EXIST. The record once
     * drew a "delete this person" row as deliberately absent; naming the
     * absent action only teaches a reader to look for it. Accounts are
     * deactivated, kept and listed, and that is the only thing the card says.
     */
    public function testTheRecordNamesNoDeleteAtAll(): void
    {
        $this->withSuccessor();
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/'.$grace->getUuidString());

        self::assertCount(0, $crawler->filter('.mb-drow.absent'));
        self::assertStringNotContainsString('Delete this person', $crawler->filter('.pgbody')->text());
        self::assertStringNotContainsString('Recycle bin', $crawler->filter('.pgbody')->text());
    }

    public function testThereIsNoDeleteRouteEither(): void
    {
        $this->withSuccessor();
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $this->client->request('POST', '/team/'.$grace->getUuidString().'/delete');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeactivatingKeepsTheRowAndStampsTheMoment(): void
    {
        $this->withSuccessor();
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$grace->getUuidString());
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/deactivate', ['_token' => $token]);

        self::assertResponseRedirects();
        $this->em->clear();
        $stored = $this->em->getRepository(User::class)->findOneBy(['email' => 'g.ndosi@example.test']);
        self::assertInstanceOf(User::class, $stored, 'The row is not deleted.');
        self::assertFalse($stored->isActive());
        self::assertNotNull($stored->getDisabledAt());

        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('Nothing has been deleted', $crawler->html());
    }

    public function testReactivatingClearsTheStamp(): void
    {
        $this->withSuccessor();
        $grace = $this->person('Grace', 'Ndosi');
        $grace->deactivate();
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$grace->getUuidString());
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/reactivate', ['_token' => $token]);

        $this->em->clear();
        $stored = $this->em->getRepository(User::class)->findOneBy(['email' => 'g.ndosi@example.test']);
        self::assertInstanceOf(User::class, $stored);
        self::assertTrue($stored->isActive());
        self::assertNull($stored->getDisabledAt());
    }

    /**
     * THE REFUSAL, PRINTED WITH ITS REASON — not a greyed-out control that says
     * "not now" and leaves the reader guessing.
     */
    public function testTheLastActiveSuperAdminSeesTheRefusalWithItsReason(): void
    {
        $naomi = $this->person('Naomi', 'Kileo', TeamRoleEnum::SuperAdmin);
        $this->em->flush();
        $this->client->loginUser($naomi);

        $crawler = $this->client->request('GET', '/team/'.$naomi->getUuidString());

        self::assertCount(1, $crawler->filter('.mb-refuse'));
        self::assertStringContainsString('Refused — last active Super Admin', $crawler->filter('.mb-refuse')->text());
        self::assertStringContainsString('Naomi Kileo is the only one', $crawler->filter('.mb-refuse')->text());
        // And the deactivate control is replaced by the refusal, not disabled.
        self::assertStringContainsString('refused — the last active Super Admin', $crawler->html());
    }

    public function testDemotingTheLastActiveSuperAdminIsRefusedOnSubmitToo(): void
    {
        $naomi = $this->person('Naomi', 'Kileo', TeamRoleEnum::SuperAdmin);
        $this->em->flush();
        $this->client->loginUser($naomi);

        $token = $this->tokenFrom('/team/'.$naomi->getUuidString());
        $this->client->request('POST', '/team/'.$naomi->getUuidString().'/tier', [
            '_token' => $token, 'tier' => 'staff',
        ]);

        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('only active Super Admin', $crawler->html());

        $this->em->clear();
        $stored = $this->em->getRepository(User::class)->findOneBy(['email' => 'n.kileo@example.test']);
        self::assertInstanceOf(User::class, $stored);
        self::assertSame(TeamRoleEnum::SuperAdmin, $stored->getTeamRole(), 'Nothing was written.');
    }

    public function testDeactivatingTheLastActiveSuperAdminIsRefusedOnSubmitToo(): void
    {
        $naomi = $this->person('Naomi', 'Kileo', TeamRoleEnum::SuperAdmin);
        $this->em->flush();
        $this->client->loginUser($naomi);

        $token = $this->tokenFrom('/team/'.$naomi->getUuidString());
        $this->client->request('POST', '/team/'.$naomi->getUuidString().'/deactivate', ['_token' => $token]);

        $this->client->followRedirect();
        $this->em->clear();
        $stored = $this->em->getRepository(User::class)->findOneBy(['email' => 'n.kileo@example.test']);
        self::assertInstanceOf(User::class, $stored);
        self::assertTrue($stored->isActive(), 'Nothing was written.');
    }

    /** TRANSFER, THEN LEAVE — and the refusal lifts in the same moment. */
    public function testGrantingItToASuccessorLiftsTheRefusal(): void
    {
        $naomi = $this->withSuccessor();

        $crawler = $this->client->request('GET', '/team/'.$naomi->getUuidString());

        self::assertCount(0, $crawler->filter('.mb-refuse'));
    }

    /** THE WARNING LIVES AT THE GRANT — beside somebody who is not yet one. */
    public function testTheSuperAdminGrantCarriesItsWarning(): void
    {
        $this->withSuccessor();
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/'.$grace->getUuidString());

        self::assertCount(1, $crawler->filter('.mb-grant'));
        self::assertStringContainsString('every permission you hold', $crawler->filter('.mb-grant')->text());
        self::assertStringContainsString('transfer before you leave', strtolower($crawler->filter('.mb-grant')->text()));
    }

    /** And it is absent where there is nothing to warn about. */
    public function testSomebodyAlreadySuperAdminGetsNoGrantWarning(): void
    {
        $naomi = $this->withSuccessor();

        $crawler = $this->client->request('GET', '/team/'.$naomi->getUuidString());

        self::assertCount(0, $crawler->filter('.mb-grant'));
    }

    /**
     * THE INVITATION FACTS LINE reads differently for the two ways an account
     * comes to exist, and appears only beside "never signed in".
     */
    public function testAnInvitedAccountNamesWhoInvitedThem(): void
    {
        $naomi = $this->withSuccessor();
        $joseph = $this->person('Joseph', 'Mrema')->setVerified(false);
        $joseph->markInvitedBy($naomi);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/'.$joseph->getUuidString());

        self::assertStringContainsString('Invited by Naomi Kileo', $crawler->filter('.mb-state')->text());
    }

    public function testAnAccountCreatedDirectlySaysNobodyInvitedThem(): void
    {
        $this->withSuccessor();
        $hawa = $this->person('Hawa', 'Rajabu')->setVerified(false);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/'.$hawa->getUuidString());

        self::assertStringContainsString('Created with a password, handed over', $crawler->filter('.mb-state')->text());
        self::assertStringContainsString('no invitation outstanding', $crawler->filter('.mb-state')->text());
    }

    public function testSomebodyWhoHasArrivedGetsNoInvitationLine(): void
    {
        $this->withSuccessor();
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/'.$grace->getUuidString());

        self::assertStringContainsString('Signed in and verified', $crawler->filter('.mb-state')->text());
        self::assertStringNotContainsString('invited by', $crawler->filter('.mb-state')->text());
    }

    /**
     * "WHAT THAT ACTUALLY GRANTS, RIGHT NOW" — every catalogue row, with the
     * reason on it. For somebody above the matrix every row reads "by tier".
     */
    public function testTheEffectiveLedgerSaysWhyOnEveryRow(): void
    {
        $this->withSuccessor();
        $ranger = $this->position('Ranger', ['surveys.read']);
        $grace = $this->person('Grace', 'Ndosi');
        $grace->setPosition($ranger);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/'.$grace->getUuidString());

        self::assertStringContainsString('by position', $crawler->filter('.pm-eff')->text());
        self::assertStringContainsString('not held', $crawler->filter('.pm-eff')->text());
        // BOTH NAMES ON EVERY ROW: the product's words for the concern and
        // the verb, and the pair the voter actually checks.
        self::assertStringContainsString('surveys.read', $crawler->filter('.pm-eff')->text());
    }

    public function testATierAboveTheMatrixReadsByTierOnEveryRow(): void
    {
        $naomi = $this->withSuccessor();

        $crawler = $this->client->request('GET', '/team/'.$naomi->getUuidString());

        self::assertStringNotContainsString('by position', $crawler->filter('.pm-eff')->text());
        self::assertStringContainsString('by tier', $crawler->filter('.pm-eff')->text());
        self::assertStringContainsString('the position changes nothing they may do', $crawler->filter('.pgbody')->text());
    }

    public function testAssigningAPositionWritesIt(): void
    {
        $this->withSuccessor();
        $ranger = $this->position('Ranger', ['surveys.read']);
        $frank = $this->person('Frank', 'Massawe');
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$frank->getUuidString());
        $this->client->request('POST', '/team/'.$frank->getUuidString().'/position', [
            '_token' => $token, 'position' => $ranger->getUuidString(),
        ]);

        $this->em->clear();
        $stored = $this->em->getRepository(User::class)->findOneBy(['email' => 'f.massawe@example.test']);
        self::assertInstanceOf(User::class, $stored);
        self::assertSame('Ranger', $stored->getPosition()?->getName());
    }

    /**
     * THE PICKER IS ONE FLAT LIST. It used to be grouped into optgroups, one
     * per department, because a position was filed under one; the ruling took
     * the department off the position, so there is one Analyst in the
     * organization and a reader choosing from this list never has to ask
     * which.
     */
    public function testThePositionPickerIsOneFlatListWithNoDepartmentGroups(): void
    {
        $this->withSuccessor();
        $this->position('Ranger', ['surveys.read']);
        $this->position('Analyst');
        $frank = $this->person('Frank', 'Massawe');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/'.$frank->getUuidString());
        $picker = $crawler->filter('.mb-assignrow select[name="position"]');

        self::assertCount(0, $picker->filter('optgroup'), 'A position belongs to no department, so there is nothing to group by.');
        self::assertSame(
            ['— no position —', 'Analyst', 'Ranger'],
            $picker->filter('option')->each(static fn (Crawler $c): string => $c->text()),
        );
    }

    /**
     * THE FLASH NAMES THE BARE POSITION — the whole name there is. It used to
     * print "Protection Service / Ranger", and half of that is a fact about a
     * post that no longer exists.
     */
    public function testTheFlashNamesTheBarePosition(): void
    {
        $this->withSuccessor();
        $ranger = $this->position('Ranger', ['surveys.read']);
        $frank = $this->person('Frank', 'Massawe');
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$frank->getUuidString());
        $this->client->request('POST', '/team/'.$frank->getUuidString().'/position', [
            '_token' => $token, 'position' => $ranger->getUuidString(),
        ]);

        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('Frank Massawe now holds Ranger.', $crawler->html());
    }

    /**
     * A FULL POST REFUSES AT THE DOOR, AND THE REFUSAL NAMES THE HOLDER.
     *
     * The seat count is enforced in the service so that no door can forget to
     * ask; what THIS holds is that the door reads the refusal back as a
     * sentence rather than letting it out as a 500. The message is the whole
     * point of the exception — "that position is full" is not actionable and
     * "Joseph Mollel holds it" is, because the administrator's next move is to
     * end that holding or pick another position and they cannot choose without
     * the name.
     */
    public function testAFullPositionIsRefusedAndTheRefusalNamesWhoHoldsIt(): void
    {
        $this->withSuccessor();
        $head = $this->position('Head of Protection', ['surveys.read']);
        $head->setSeatCount(1);
        $joseph = $this->person('Joseph', 'Mollel');
        $joseph->setPosition($head);
        $frank = $this->person('Frank', 'Massawe');
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$frank->getUuidString());
        $this->client->request('POST', '/team/'.$frank->getUuidString().'/position', [
            '_token' => $token, 'position' => $head->getUuidString(),
        ]);

        // A REDIRECT AND NOT A CRASH. Asserting the flash alone would pass on
        // a page that had already fallen over on the way to rendering it.
        self::assertTrue($this->client->getResponse()->isRedirect());
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $html = $crawler->html();
        self::assertStringContainsString('Head of Protection', $html);
        self::assertStringContainsString('Joseph Mollel', $html);
        self::assertStringContainsString('one seat', $html);

        // AND NOBODY WAS SEATED. A refusal that still wrote would be worse
        // than no refusal, because the page would say it had not.
        $this->em->clear();
        $stored = $this->em->getRepository(User::class)->findOneBy(['email' => 'f.massawe@example.test']);
        self::assertInstanceOf(User::class, $stored);
        self::assertNull($stored->getPosition(), 'the refused assignment left Frank holding nothing.');
    }

    /**
     * A DEACTIVATED HOLDER DOES NOT OCCUPY A SEAT. Somebody who has left is
     * kept on the roster rather than deleted, and a singular post whose only
     * holder left is a post that stands empty — a seat count that counted
     * former holders would make every one-seat position unfillable the first
     * time somebody moved on.
     */
    public function testSomebodyWhoHasLeftFreesTheSeatTheyHeld(): void
    {
        $this->withSuccessor();
        $head = $this->position('Head of Protection', ['surveys.read']);
        $head->setSeatCount(1);
        $joseph = $this->person('Joseph', 'Mollel');
        $joseph->setPosition($head);
        $joseph->deactivate();
        $frank = $this->person('Frank', 'Massawe');
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$frank->getUuidString());
        $this->client->request('POST', '/team/'.$frank->getUuidString().'/position', [
            '_token' => $token, 'position' => $head->getUuidString(),
        ]);

        $this->em->clear();
        $stored = $this->em->getRepository(User::class)->findOneBy(['email' => 'f.massawe@example.test']);
        self::assertInstanceOf(User::class, $stored);
        self::assertSame('Head of Protection', $stored->getPosition()?->getName());
    }

    /** "No position" is a real choice, and the flash says what it costs. */
    public function testTakingThePositionAwayIsARealChoice(): void
    {
        $this->withSuccessor();
        $ranger = $this->position('Ranger', ['surveys.read']);
        $grace = $this->person('Grace', 'Ndosi');
        $grace->setPosition($ranger);
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$grace->getUuidString());
        $this->client->request('POST', '/team/'.$grace->getUuidString().'/position', [
            '_token' => $token, 'position' => '',
        ]);

        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('no permissions at all', $crawler->html());
    }

    public function testTheRecordFieldsSave(): void
    {
        $this->withSuccessor();
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $token = $this->tokenFrom('/team/'.$grace->getUuidString());
        $this->client->request('POST', '/team/'.$grace->getUuidString(), [
            '_token' => $token,
            'firstName' => 'Grace',
            'lastName' => 'Ndosi-Mwangi',
            'email' => 'G.Ndosi@Example.TEST',
            'rangerCode' => 'R-104',
        ]);

        $this->em->clear();
        $stored = $this->em->getRepository(User::class)->findOneBy(['rangerCode' => 'r-104']);
        self::assertInstanceOf(User::class, $stored);
        self::assertSame('Grace Ndosi-Mwangi', $stored->getFullName());
        self::assertSame('g.ndosi@example.test', $stored->getEmail(), 'The entity folds the email itself.');
    }

    /**
     * PERSONAL DETAILS ARE THEIR OWN CONCERN, AND THE PAGE PROVES IT. Somebody
     * holding `directory.read` and NOT `personal-details.read` may know that
     * Grace is on the team, what she is called and what position she holds,
     * and may not read how to reach her or what her account is doing. The
     * page stays open and the half of it that is about the person shuts,
     * which is the whole reason the two were declared separately.
     */
    public function testAReaderWithoutPersonalDetailsSeesThePersonAndNotTheirContactDetails(): void
    {
        $this->person('Naomi', 'Kileo', TeamRoleEnum::SuperAdmin);
        $colleague = $this->person('Asha', 'Mollel');
        $colleague->setPosition($this->position('Duty Officer', ['directory.read']));
        // A grant is only held somewhere, so the reader is placed across the
        // organization: what shuts the contact block is the missing pair and
        // nothing about where they stand.
        $this->place($colleague);

        $grace = $this->person('Grace', 'Ndosi');
        $grace->setPosition($this->position('Ranger', ['surveys.read']));
        $this->em->flush();
        $address = (string) $grace->getEmail();
        $this->client->loginUser($colleague);

        $crawler = $this->client->request('GET', '/team/'.$grace->getUuidString());

        self::assertResponseIsSuccessful();
        self::assertSame('Grace Ndosi', $crawler->filter('h1.pg')->text());
        self::assertStringContainsString('Ranger', $crawler->filter('.mb-band')->text(), 'Who they are and what they do is the directory.');

        self::assertStringNotContainsString($address, $crawler->html(), 'The address is a contact detail and this reader may not read one.');
        self::assertStringNotContainsString('sign-in state', $crawler->html(), 'And neither is what their account is doing.');
        self::assertCount(0, $crawler->filter('input[name="email"]'), 'Nor written, which would print it just the same.');
    }

    /**
     * AND THE SAME PAGE, READ BY SOMEBODY WHO HOLDS BOTH, carries all of it —
     * so the assertions above are about the missing pair and not about a card
     * that stopped rendering for everybody.
     */
    public function testAReaderHoldingPersonalDetailsSeesTheContactDetails(): void
    {
        $this->withSuccessor();
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();
        $address = (string) $grace->getEmail();

        $crawler = $this->client->request('GET', '/team/'.$grace->getUuidString());

        self::assertStringContainsString($address, $crawler->html());
        self::assertStringContainsString('sign-in state', $crawler->html());
        self::assertCount(1, $crawler->filter('input[name="email"]'));
    }

    /** Impersonation is offered to a Super Admin only; for anybody else, absent. */
    public function testSwitchUserIsAbsentForAnAdministratorWhoIsNotASuperAdmin(): void
    {
        $this->person('Naomi', 'Kileo', TeamRoleEnum::SuperAdmin);
        $senior = $this->position('Senior Ranger', ['directory.read']);
        $grace = $this->person('Grace', 'Ndosi');
        $grace->setPosition($senior);
        // THE POSITION GRANTS AND THE PLACEMENT REACHES, and the model fails
        // closed: somebody holding `directory.read` with nowhere to exercise
        // it reaches no ground at all, so the administrator in this scene is
        // placed across the organization before they read anything.
        $this->place($grace);
        $target = $this->person('Zawadi', 'Naisenya');
        $this->em->flush();
        $this->client->loginUser($grace);

        $crawler = $this->client->request('GET', '/team/'.$target->getUuidString());

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('_switch_user', $crawler->html());
    }
}
