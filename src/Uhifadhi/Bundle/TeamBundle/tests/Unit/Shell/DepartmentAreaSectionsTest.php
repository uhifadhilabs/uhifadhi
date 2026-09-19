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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Unit\Shell;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\TeamBundle\Controller\AreaDepartmentController;
use Uhifadhi\Bundle\TeamBundle\Shell\DepartmentAreaSections;

/**
 * The entry this bundle puts on an area's configure strip: one screen, at the
 * section's own address, for the area it was asked about.
 */
#[CoversClass(DepartmentAreaSections::class)]
final class DepartmentAreaSectionsTest extends TestCase
{
    public function testItContributesTheDepartmentsScreenForThatArea(): void
    {
        $sections = (new DepartmentAreaSections())->sectionsFor('0198f0a0-0000-7000-8000-000000000001', 'Northreach');

        self::assertCount(1, $sections);
        self::assertSame('departments', $sections[0]->id);
        self::assertSame('Departments', $sections[0]->label);
        self::assertSame(AreaDepartmentController::SECTION, $sections[0]->routeName);
        self::assertSame(['uuid' => '0198f0a0-0000-7000-8000-000000000001'], $sections[0]->parameters);
    }
}
