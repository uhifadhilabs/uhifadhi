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

namespace Uhifadhi\Contracts\Tests\Kpi;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Contracts\Kpi\DepartmentKpi;
use Uhifadhi\Contracts\Kpi\DepartmentKpiProviderInterface;
use Uhifadhi\Contracts\Kpi\DepartmentRef;

/**
 * THE TWO RULES THE MODEL CANON PUTS ON A FIGURE, and they are the difference
 * between a performance page that reports and one that lies.
 */
final class DepartmentKpiTest extends TestCase
{
    private function kpi(?float $value, ?float $previous = null, string $unit = ''): DepartmentKpi
    {
        return new DepartmentKpi(
            key: 'patrols',
            label: 'Patrols logged',
            moduleSlug: 'patrols',
            moduleName: 'Patrols',
            value: $value,
            unit: $unit,
            previous: $previous,
        );
    }

    /** UNKNOWN IS NOT ZERO. "We did not measure" and "we measured nothing" are different facts. */
    public function testAnUnknownFigureIsNotAZero(): void
    {
        $unknown = $this->kpi(null);

        self::assertFalse($unknown->isKnown());
        self::assertSame("\u{2014}", $unknown->display(), 'an unmeasured figure is a dash, never a 0');
        self::assertNull($unknown->delta());
    }

    /** A COUNT MOVES IN PERCENT. 88 against 79 is +11.4%. */
    public function testACountMovesInPercent(): void
    {
        $kpi = $this->kpi(88.0, 79.0);

        self::assertEqualsWithDelta(11.39, $kpi->delta(), 0.01);
        self::assertSame('+11.4%', $kpi->deltaLabel());
        self::assertSame('good', $kpi->direction());
    }

    /** A SHARE MOVES IN POINTS. 54% against 61% is −7 pts, never −11.5%. */
    public function testAShareMovesInPoints(): void
    {
        $kpi = $this->kpi(54.0, 61.0, DepartmentKpi::SHARE);

        self::assertTrue($kpi->isShare());
        self::assertEqualsWithDelta(-7.0, $kpi->delta(), 0.001);
        self::assertSame("\u{2212}7 pts", $kpi->deltaLabel(), 'a real minus sign, so digits line up');
        self::assertSame('bad', $kpi->direction());
    }

    /** A first month has no delta; inventing one from zero would read as infinite growth. */
    public function testAFirstPeriodHasNoDelta(): void
    {
        self::assertNull($this->kpi(88.0)->delta());
        self::assertNull($this->kpi(88.0, 0.0)->delta());
        self::assertSame('', $this->kpi(88.0)->direction());
    }

    public function testAFigureMustNameItsKeyAndTheModuleThatComputedIt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DepartmentKpi(key: '', label: 'Patrols', moduleSlug: 'patrols', moduleName: 'Patrols', value: 1.0);
    }

    /** A null area name means the department TOTAL — what every board cell is scored from. */
    public function testANamelessAreaMeansTheDepartmentTotal(): void
    {
        self::assertTrue($this->kpi(88.0)->isTotal());
    }

    public function testFewerThanTwoReadingsDrawNoSparkline(): void
    {
        $kpi = new DepartmentKpi('p', 'P', 'patrols', 'Patrols', 1.0, spark: [5.0]);
        self::assertSame('', $kpi->sparkPoints());
    }

    /** A flat series sits on the baseline rather than dividing by zero. */
    public function testAFlatSeriesDrawsAFlatLine(): void
    {
        $kpi = new DepartmentKpi('p', 'P', 'patrols', 'Patrols', 1.0, spark: [4.0, 4.0, 4.0]);
        self::assertSame('0.0,27.0 60.0,27.0 120.0,27.0', $kpi->sparkPoints());
    }

    /**
     * THE CONTRACT NEVER NAMES TEAM'S CLASS. Departments are TeamBundle's
     * entity and no package publishes a contract for one, so a provider is
     * handed a REF the caller resolved — id, uuid and name, which is everything
     * a figure is filed under. That is the same discipline the platform already
     * applies to reading a person's department: walk the mapping, never the type.
     */
    public function testTheContractHandsAProviderARefRatherThanAnEntity(): void
    {
        $method = new \ReflectionMethod(DepartmentKpiProviderInterface::class, 'kpisFor');
        $first = $method->getParameters()[0]->getType();

        self::assertInstanceOf(\ReflectionNamedType::class, $first);
        self::assertSame(DepartmentRef::class, $first->getName());
    }

    public function testARefCarriesWhatAFigureIsFiledUnder(): void
    {
        $ref = new DepartmentRef(id: 7, name: 'Ecology & Range Management');

        self::assertSame(7, $ref->id);
        self::assertSame('Ecology & Range Management', $ref->name);
        self::assertNull($ref->uuid);
    }

    public function testARefWithoutANameIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DepartmentRef(id: 7, name: '');
    }
}
