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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures;

use Uhifadhi\Contracts\Area\DirectoryArea;
use Uhifadhi\Contracts\Area\PostedStation;
use Uhifadhi\Contracts\Area\StationDirectoryInterface;

/**
 * WHOEVER OWNS THE GROUND, PLAYED BY A FIXTURE.
 *
 * This kernel deliberately does not install an area package — Team must hold
 * a person without one — so the station-shaped seam is answered here instead,
 * exactly as an installation answers it with its own. The suite then proves
 * the join: the ground hands over uuids, this bundle says who they are.
 *
 * THE ROWS ARE STATIC BECAUSE THE SERVICE IS SHARED. A test arranges the
 * ground before it asks for a page, and the container hands the same instance
 * to the board; a constructor argument could not be changed per test.
 */
final class FakeStationDirectory implements StationDirectoryInterface
{
    /** @var list<PostedStation> */
    public static array $stations = [];

    /** @var list<DirectoryArea> */
    public static array $areas = [];

    /** Between tests, the installation has no ground at all. */
    public static function clear(): void
    {
        self::$stations = [];
        self::$areas = [];
    }

    public function stations(): array
    {
        return self::$stations;
    }

    public function areas(): array
    {
        return self::$areas;
    }
}
