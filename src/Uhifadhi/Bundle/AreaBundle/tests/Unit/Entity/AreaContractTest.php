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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Unit\Entity;

use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Contracts\Entity\AreaInterface;

/**
 * THE TWO PROMISES THIS ENTITY MAKES TO AN INSTALLATION THAT ALREADY HAS ONE.
 *
 * 1. IT ANSWERS THE PLATFORM'S AREA CONTRACT. Modules map an area association at
 *    {@see AreaInterface} (published by module-contracts) and never at a class;
 *    the seam's per-area row is the oldest of them. This entity is the answer,
 *    and the bundle says so itself (see the resolution suite).
 *
 * 2. THE TABLE IS `area_of_interest`, AND THAT IS A COMPATIBILITY PROMISE, not
 *    a naming preference. Before this module existed, every installation was
 *    told to write its own area class and resolve the interface to it; the
 *    documented class was `AreaOfInterest` in `src/Entity/`, which under the
 *    skeleton's underscore naming strategy is exactly this table. An
 *    installation that migrated with that placeholder therefore does NOT need a
 *    rename migration when it adopts this module — it deletes its class,
 *    installs this one, and diffs. Change the name here and that promise breaks
 *    silently, in somebody else's production database, so it is pinned.
 *
 * A unit test, not an integration one, because both promises are properties of
 * the SOURCE — they must hold before any database is reachable, and an
 * installation reading them off the class is reading the same attribute.
 */
final class AreaContractTest extends TestCase
{
    public function testItAnswersTheSeamsAreaContract(): void
    {
        // Asked of the class rather than with `instanceof`: the promise is a
        // property of the SOURCE — it must hold before anything is constructed,
        // and an installation reading it off the class reads the same thing.
        self::assertTrue(
            new \ReflectionClass(AreaOfInterest::class)->implementsInterface(AreaInterface::class),
            'the seam maps its per-area row at AreaInterface; this entity is what answers it',
        );
    }

    public function testTheTableIsAreaOfInterestSoAPlaceholderInstallationNeedsNoRename(): void
    {
        $attributes = new \ReflectionClass(AreaOfInterest::class)->getAttributes(ORM\Table::class);

        self::assertCount(1, $attributes, 'the table name is stated explicitly, never left to the class name');
        self::assertSame('area_of_interest', $attributes[0]->newInstance()->name);
    }

    /**
     * IDENTITY IS THE SEAM'S FLOOR: getId() is always part of the area contract.
     *
     * The contract MAY expose more — v0.5 added getName() and getUuidString() so a
     * consumer holding an area only as AreaInterface can name and address it across
     * a module boundary (mirroring UserInterface). Pinning the EXACT surface is
     * module-contracts' own job (it has a surface test); this consumer test asserts
     * only what area-module depends on — that identity is there — and stays green
     * when the shared contract legitimately grows.
     */
    public function testTheContractItAnswersIncludesIdentity(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            new \ReflectionClass(AreaInterface::class)->getMethods(),
        );

        self::assertContains('getId', $methods);
    }
}
