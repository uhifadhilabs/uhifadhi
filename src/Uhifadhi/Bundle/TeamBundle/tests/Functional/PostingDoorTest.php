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

use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;

/**
 * THE PERSON'S RECORD SAYS WHERE A POSTING IS MADE, AND GOES THERE.
 *
 * THE DEFECT THIS PINS. The Postings card said "read-only, made in the area"
 * and then named nothing a reader could open — the owner went looking for
 * where a posting is made and did not find it. Read-only is right: a posting
 * belongs to the station, in the area that owns the ground. Naming the page
 * without a door to it is not.
 *
 * AND IT COSTS BOTH PAIRS BETWEEN THE READER AND THE DEED: `stations.read`,
 * which opens the page behind it, and `assignments.manage`, which is the
 * thing the door invites them to do. Somebody holding only one of them gets
 * the record and no door, because an offer that refuses is worse than no
 * offer.
 */
final class PostingDoorTest extends WebTestCaseWithSchema
{
    /**
     * A PERSON POSTED NOWHERE IS AN ORDINARY STATE, and the card now says so
     * and says where to change it.
     */
    public function testTheRecordOffersTheWayToPostSomebody(): void
    {
        $this->administrator();
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/team/'.$grace->getUuidString());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Station them', $crawler->filter('.mb-empty')->text());
        self::assertSame('/areas', $crawler->filter('.mb-empty a.more')->attr('href'), 'With no one area to send them to, the register is where they choose.');
    }

    /**
     * AND IT IS ABSENT FOR SOMEBODY WHO MAY ADMINISTER THE TEAM AND NOT WRITE
     * TO AN AREA — absent, never disabled.
     */
    public function testTheDoorIsAbsentForSomebodyWhoMayNotWriteToAnArea(): void
    {
        $manager = $this->person('Asha', 'Mollel', TeamRoleEnum::Staff);
        $manager->setPosition($this->position('Team lead', [
            'directory.read',
            'directory.manage',
            'personal-details.read',
            'personal-details.manage',
            'positions.read',
            'positions.configure',
            'departments.read',
            'departments.configure',
        ]));
        // Placed across the organization, so nothing about WHERE they stand
        // is what closes the door — it is closed because the position grants
        // nothing about the ground, which is the only thing this test is
        // about.
        $this->place($manager);
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();
        $this->client->loginUser($manager);

        $crawler = $this->client->request('GET', '/team/'.$grace->getUuidString());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Not stationed anywhere', $crawler->filter('.mb-empty')->text());
        self::assertCount(0, $crawler->filter('.mb-empty a.more'));
    }

    /**
     * THE CARD STATES A FACT ABOUT THE PERSON, not about a column. "Nullable ·
     * office-based staff have none" was a docblock that had got into the
     * product.
     */
    public function testTheCardStatesAFactAndNotASchemaNote(): void
    {
        $this->administrator();
        $grace = $this->person('Grace', 'Ndosi');
        $this->em->flush();

        $card = $this->client->request('GET', '/team/'.$grace->getUuidString())->filter('.mb-empty')->text();

        self::assertStringContainsString('Office-based staff hold no assignment', $card);
        self::assertStringNotContainsString('Nullable', $card);
    }
}
