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
use Uhifadhi\Bundle\TeamBundle\Entity\DepartmentKind;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;

/**
 * THE ONE LIST THE DEPARTMENTS SECTION OWNS — the department kind.
 *
 * A KIND IS A LENS AND NOT A PERMISSION. It groups departments for reading,
 * grants nothing and confines nothing, which is why it is a row an
 * organization adds rather than an enum a release adds. The tests below are
 * about the word: it is trimmed, it is not empty, and no two kinds share one.
 *
 * THE SCOPES ARE NOT A LIST and the page says so rather than hiding them —
 * somebody who cannot find the button has to be told there is not one.
 */
final class DepartmentKindTest extends WebTestCaseWithSchema
{
    public function testAKindIsAddedFromTheListsPage(): void
    {
        $this->lists();

        $this->post('/departments/configure/lists/kinds', ['name' => ' Operational ', 'meaning' => ' runs the ground ']);

        $kind = $this->em->getRepository(DepartmentKind::class)->findOneBy(['name' => 'Operational']);
        self::assertInstanceOf(DepartmentKind::class, $kind);
        self::assertSame('runs the ground', $kind->getMeaning(), 'The name and the meaning are trimmed.');
    }

    /**
     * AN EMPTY MEANING IS NULL, NOT "". "Nobody has said what this means" is a
     * real state, and two spellings of one absence is two readings of it.
     */
    public function testAKindWithNoMeaningStoresNullAndNotAnEmptyString(): void
    {
        $this->lists();

        $this->post('/departments/configure/lists/kinds', ['name' => 'Support', 'meaning' => '   ']);

        self::assertNull($this->em->getRepository(DepartmentKind::class)->findOneBy(['name' => 'Support'])?->getMeaning());
    }

    /** Two kinds by one name is one kind entered twice, and it is refused. */
    public function testASecondKindCannotTakeAnExistingName(): void
    {
        $this->lists();
        $this->post('/departments/configure/lists/kinds', ['name' => 'Operational']);
        $this->post('/departments/configure/lists/kinds', ['name' => 'Operational']);

        self::assertCount(1, $this->em->getRepository(DepartmentKind::class)->findBy(['name' => 'Operational']));
    }

    /** A kind needs a name, and the page says so rather than storing a blank. */
    public function testAKindNeedsAName(): void
    {
        $this->lists();

        $this->post('/departments/configure/lists/kinds', ['name' => '  ']);

        self::assertCount(0, $this->em->getRepository(DepartmentKind::class)->findAll());
    }

    public function testAKindIsRenamed(): void
    {
        $this->lists();
        $this->post('/departments/configure/lists/kinds', ['name' => 'Operational', 'meaning' => 'runs the ground']);

        $kind = $this->em->getRepository(DepartmentKind::class)->findOneBy(['name' => 'Operational']);
        self::assertInstanceOf(DepartmentKind::class, $kind);

        $this->post('/departments/configure/lists/kinds/'.$kind->getUuidString().'/rename', ['name' => 'Field', 'meaning' => 'on the ground']);

        $this->em->clear();
        self::assertNotNull($this->em->getRepository(DepartmentKind::class)->findOneBy(['name' => 'Field']));
    }

    /**
     * THE SCOPES ARE THE MODEL, and the page states them read-only. A third
     * scope would be a third rule in every query a module writes.
     */
    public function testTheScopesAreShownAndAreNotEditable(): void
    {
        $crawler = $this->lists();

        $card = $crawler->filter('.c')->reduce(static fn (Crawler $c): bool => str_contains($c->filter('.tab')->text(), 'Scopes'))->first();

        self::assertCount(2, $card->filter('tbody tr'));
        self::assertCount(0, $card->filter('form'));
    }

    /** And the create card is the house one, always open at the top of the page. */
    public function testTheCreateCardIsTheHouseCardAndIsAlwaysOpen(): void
    {
        $crawler = $this->lists();

        self::assertCount(1, $crawler->filter('.dcadd .crcard'));
        self::assertSame('Add a department kind', $crawler->filter('.dcaddhd b')->text());
    }

    private function lists(): Crawler
    {
        $admin = $this->person('Naomi', 'Kileo', TeamRoleEnum::Admin);
        $admin->setPosition($this->position('Warden', null, [PermissionEnum::TeamManage->value]));
        $this->em->flush();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/departments/configure/lists');
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    /**
     * Post with a REAL token, pulled off the page the form is on — the same
     * one a person's browser would send, so the guard is exercised rather
     * than side-stepped.
     *
     * @param array<string, string> $fields
     */
    private function post(string $path, array $fields): void
    {
        $token = (string) $this->client->request('GET', '/departments/configure/lists')
            ->filter('input[name="_token"]')->first()->attr('value');

        $this->client->request('POST', $path, $fields + ['_token' => $token]);
        self::assertResponseRedirects('/departments/configure/lists');
        $this->em->clear();
    }
}
