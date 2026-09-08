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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;

/**
 * THE CATALOGUE IS SEVEN, AND THE LIST IS WRITTEN OUT HERE.
 *
 * Not counted — spelled. A permission is a power somebody holds, so adding one
 * is a decision, and a test that only counted would let the eighth arrive by
 * accident and the wrong seventh be swapped in silently.
 *
 * THE SEVENTH IS `team.manage`. Administering the team is an ordinary row in
 * the matrix under its own umbrella rather than a standing beside it, which is
 * what makes "who administers this installation" a question with a countable
 * answer.
 */
#[CoversClass(PermissionEnum::class)]
final class PermissionEnumTest extends TestCase
{
    public function testTheCatalogueIsExactlyTheSevenNamedPermissions(): void
    {
        self::assertSame([
            'area.view',
            'area.create',
            'area.edit',
            'area.delete',
            'module.view',
            'module.create',
            'team.manage',
        ], array_map(static fn (PermissionEnum $p): string => $p->value, PermissionEnum::all()));
    }

    /**
     * THERE IS NO INGESTION, and this test is the guard on that. This platform
     * has no ingestion capability, and a permission guarding nothing is a power
     * an admin can assign over code that does not exist. Named here rather than
     * merely absent above, because "not this one, on purpose" is the fact worth
     * keeping.
     */
    public function testIngestionIsNotInTheCatalogue(): void
    {
        // Asserted over the VALUES rather than with tryFrom(): a literal that
        // is not a case is a fact static analysis already knows, so that
        // assertion would be a tautology rather than a guard.
        self::assertNotContains(
            'ingestion.run',
            array_map(static fn (PermissionEnum $p): string => $p->value, PermissionEnum::all()),
        );

        foreach (PermissionEnum::all() as $permission) {
            self::assertNotSame('Ingestion', $permission->umbrella());
        }
    }

    public function testEachPermissionCarriesItsUmbrellaAndAction(): void
    {
        self::assertSame('Areas', PermissionEnum::AreaView->umbrella());
        self::assertSame('View', PermissionEnum::AreaView->action());

        // The label is the two words the matrix prints, joined the one way.
        self::assertSame('Modules · Add', PermissionEnum::ModuleCreate->label());
    }

    /**
     * A PERMISSION MINTS NO ROLE. An umbrella is a heading on the matrix, not a
     * region an access rule can name: a permission answers "may this person do
     * X *here*", and no path pattern can express the "here". Holding one has to
     * be decided against the person, per action and per area, which is exactly
     * what a role cannot do — so the coarse standing a role would have granted
     * is a power nobody can see being granted.
     *
     * Asserted on the ABSENCE OF THE METHOD, because a call site left compiling
     * would be a screen still gated on the coarse axis.
     */
    public function testNoPermissionMintsARole(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            new \ReflectionClass(PermissionEnum::class)->getMethods(\ReflectionMethod::IS_PUBLIC),
        );
        sort($methods);

        self::assertSame(
            ['action', 'all', 'cases', 'description', 'from', 'isAreaScoped', 'label', 'tryFrom', 'umbrella'],
            $methods,
            'A permission is decided by asking about the person, never by opening a region.',
        );
    }

    /**
     * The Team umbrella carries exactly one row, and that is not a mistake: an
     * umbrella is the heading the matrix groups under, so one row under one
     * heading is a catalogue an administrator can read.
     */
    public function testTeamManageIsTheSeventhUnderItsOwnUmbrella(): void
    {
        self::assertSame('Team', PermissionEnum::TeamManage->umbrella());
        self::assertSame('Manage', PermissionEnum::TeamManage->action());
        self::assertSame('Team · Manage', PermissionEnum::TeamManage->label());
    }

    public function testTheThreeUmbrellasAreTheOnlyOnes(): void
    {
        $umbrellas = array_values(array_unique(
            array_map(static fn (PermissionEnum $p): string => $p->umbrella(), PermissionEnum::all()),
        ));

        self::assertSame(['Areas', 'Modules', 'Team'], $umbrellas);
    }

    /**
     * EVERY PERMISSION CARRIES ITS SENTENCE, the core seven exactly as the
     * declared ones do. The matrix prints it under the name, so a core row that
     * had none would be the one row on the page an administrator cannot read —
     * and the rule this release states is that there are no such rows.
     */
    public function testEveryCorePermissionExplainsItself(): void
    {
        foreach (PermissionEnum::all() as $permission) {
            self::assertNotSame(
                '',
                trim($permission->description()),
                $permission->value.' is a power somebody can be granted with no sentence saying what it does.',
            );
        }
    }

    /**
     * The sentence is about the holder and not about the mechanism. Spot-checked
     * on the one permission whose meaning is easiest to state wrongly.
     */
    public function testTheSentenceSaysWhatHoldingItLetsSomebodyDo(): void
    {
        self::assertStringContainsString('team', strtolower(PermissionEnum::TeamManage->description()));
    }
}
