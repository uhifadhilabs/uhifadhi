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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Model\PositionCard;
use Uhifadhi\Contracts\Access\ScopeKind;

/**
 * THE ONE DERIVATION THE THREE POSITION SCREENS READ.
 *
 * WHERE A HOLDER MAY BE PLACED IS A PHRASE, AND IT IS NOT THE SCOPE'S OWN.
 * The record's subline once composed itself from {@see ScopeKind::reach()},
 * which answers a different question — what a grant at that scope REACHES —
 * and so an organization-only position read "placed Everything." The phrase
 * belongs to the reading, and this is where it is held to its words.
 */
#[CoversClass(PositionCard::class)]
final class PositionCardTest extends TestCase
{
    /** @return list<array{list<ScopeKind>, string}> */
    public static function placements(): array
    {
        return [
            [[ScopeKind::Organization, ScopeKind::Area], 'placed in one area or across the organization'],
            [[ScopeKind::Area, ScopeKind::Organization], 'placed in one area or across the organization'],
            [[ScopeKind::Organization], 'placed across the organization'],
            [[ScopeKind::Area], 'placed in one area'],
        ];
    }

    /**
     * @param list<ScopeKind> $kinds
     */
    #[DataProvider('placements')]
    public function testThePlacementPhraseIsTheKindsAndNotTheirReach(array $kinds, string $expected): void
    {
        self::assertSame($expected, self::card($kinds)->placedWhere());
    }

    /** Whatever the kinds are, the subline never borrows a scope's own sentence. */
    public function testThePhraseNeverPrintsAScopesReachSentence(): void
    {
        foreach (self::placements() as [$kinds]) {
            self::assertStringNotContainsString('Everything.', self::card($kinds)->placedWhere());
        }
    }

    /**
     * AND THE FOURTH CASE CANNOT BE REACHED THROUGH THE PRODUCT. The phrase
     * still answers for it — a `match` that cannot fall through is a `match`
     * that throws at a reader instead of telling them something — but the
     * entity refuses the state, and that refusal is the reason the card is
     * never asked to describe it.
     */
    public function testAPositionAllowingNoKindOfPlacementIsRefusedByTheEntity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Position()->setAllowedKinds([]);
    }

    /** @param list<ScopeKind> $kinds */
    private static function card(array $kinds): PositionCard
    {
        return new PositionCard(
            position: new Position()->setName('Sergeant')->setAllowedKinds($kinds),
            groups: [],
            holders: [],
            concernsGranted: 0,
            concernsDeclared: 0,
            sensitiveGranted: 0,
            verbTotals: [],
        );
    }
}
