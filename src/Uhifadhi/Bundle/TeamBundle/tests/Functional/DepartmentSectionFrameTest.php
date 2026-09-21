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
 * DEPARTMENTS WEARS THE AREA IDIOM — the ruling, asserted.
 *
 * An org-level section is read the way an area is read: the same header on
 * every tab (the section's name), a subline that says what THIS tab is for, one
 * tab strip between the head and the body with exactly one tab lit, and the one
 * Configure action at the right-hand end of the action row on every tab. The
 * tab set is Overview · Departments · Modules; Configure is an action and never
 * a tab, and the configure page shows its own sections where a data tab shows
 * the strip.
 *
 * THE STRIP IS THE SHELL'S, NOT THIS BUNDLE'S. Team contributes the list
 * through the same tabs contract a module uses, which is the whole point of the
 * contract: nothing here is a second implementation of a strip.
 */
final class DepartmentSectionFrameTest extends WebTestCaseWithSchema
{
    /**
     * Every tab of the section, and the label that must be lit on it.
     *
     * @return \Generator<string, array{string, string}>
     */
    public static function tabs(): \Generator
    {
        yield 'overview' => ['/departments/overview', 'Overview'];
        yield 'register' => ['/departments', 'Departments'];
        yield 'modules' => ['/departments/modules', 'Modules'];
    }

    /**
     * THE SAME THREE TABS ON EVERY TAB, IN THE RULED ORDER, with exactly one of
     * them lit — the one you are standing on.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tabs')]
    public function testEveryTabCarriesTheWholeStripWithItsOwnTabLit(string $path, string $lit): void
    {
        $crawler = $this->visit($path);

        self::assertSame(
            ['Overview', 'Departments', 'Modules'],
            $crawler->filter('.atabs a')->each(static fn (Crawler $c): string => $c->text()),
        );
        self::assertSame([$lit], $crawler->filter('.atabs a.on')->each(static fn (Crawler $c): string => $c->text()));
    }

    /**
     * THE SAME HEADER ON EVERY TAB — the section's name — and a subline that is
     * this tab's own. A reader who lands anywhere in the section reads the same
     * title; the strip, not the title, says which screen they are on.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tabs')]
    public function testEveryTabIsHeadedBySectionNameAndCarriesItsOwnSubline(string $path, string $lit): void
    {
        $crawler = $this->visit($path);

        self::assertSame('Departments', $crawler->filter('.pghead h1.pg')->text());
        self::assertNotSame('', trim($crawler->filter('.pghead .pgsub')->text()));
    }

    /** And the sublines differ: a shared line on every tab would say nothing. */
    public function testTheSublinesAreOnePerTab(): void
    {
        $sublines = [];
        foreach (self::tabs() as $tab) {
            $sublines[] = $this->visit($tab[0])->filter('.pghead .pgsub')->text();
        }

        self::assertSame($sublines, array_unique($sublines));
    }

    /**
     * THE ONE CONFIGURE ACTION, ON EVERY TAB, LAST IN THE ROW. The frame writes
     * it, so the assertion is that the org-level section reaches the frame at
     * all: before this, a surface with no area in its address got no action,
     * because the shell's own configure page is area-shaped.
     *
     * IT OPENS THE SURFACE'S FIRST SECTION, which is the house's rank and not
     * this section's choice — the same control opens the same kind of screen
     * everywhere in the product.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tabs')]
    public function testEveryTabCarriesTheConfigureAction(string $path, string $lit): void
    {
        $actions = $this->visit($path)->filter('.pgact > *');

        self::assertGreaterThan(0, $actions->count());
        self::assertSame('Configure', trim($actions->last()->text()));
        self::assertSame('/departments/configure/lists', $actions->last()->attr('href'));
    }

    // ---- the configure surface -------------------------------------------

    /** The configure page shows its SECTIONS where a data tab shows the strip. */
    public function testTheConfigurePageShowsItsSectionsInTheStrip(): void
    {
        $crawler = $this->visit('/departments/configure');

        self::assertSame(
            ['Lists', 'Departments settings'],
            $crawler->filter('.atabs a')->each(static fn (Crawler $c): string => $c->text()),
        );
        self::assertSame(['Departments settings'], $crawler->filter('.atabs a.on')->each(static fn (Crawler $c): string => $c->text()));
    }

    /** And the Lists section is a screen of its own, lit in the same strip. */
    public function testTheListsSectionLightsItsOwnEntry(): void
    {
        $crawler = $this->visit('/departments/configure/lists');

        self::assertSame(['Lists'], $crawler->filter('.atabs a.on')->each(static fn (Crawler $c): string => $c->text()));
    }

    /**
     * ON THE CONFIGURE SURFACE THE ACTION IS THE WAY BACK, not a link to where
     * you already are — the shell's rule, and it must hold for an org section
     * too. The way back is the section's first tab.
     */
    public function testOnConfigureTheActionIsTheWayBack(): void
    {
        $action = $this->visit('/departments/configure')->filter('.pgact > *')->last();

        self::assertSame('/departments/overview', $action->attr('href'));
    }

    private bool $signedIn = false;

    private function visit(string $path): Crawler
    {
        if (!$this->signedIn) {
            $admin = $this->person('Naomi', 'Kileo', TeamRoleEnum::Admin);
            $admin->setPosition($this->position('Warden', [PermissionEnum::TeamManage->value]));
            $this->em->flush();
            $this->client->loginUser($admin);
            $this->signedIn = true;
        }

        $crawler = $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();

        return $crawler;
    }
}
