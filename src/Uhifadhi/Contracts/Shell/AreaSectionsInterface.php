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

namespace Uhifadhi\Contracts\Shell;

/**
 * HOW A BUNDLE PUTS A SECTION ON AN AREA'S CONFIGURE PAGE.
 *
 * THE AREA DOES NOT KNOW WHAT ELSE IS CONFIGURED ABOUT IT. An area has zones,
 * stations and settings of its own, and the bundle that owns areas can name
 * those. Departments belong to the team bundle, and an area naming them would
 * be the area bundle depending on a bundle it does not require — so the strip
 * is contributed instead, the same way a module contributes anything else.
 *
 * ONE STRIP, RULED ORDER. What comes back is inserted between the area's own
 * sections and Area settings, which stays last; Widget library stays first.
 * An installation without the contributing bundle simply reads a shorter
 * strip, and nothing is missing that is not also absent.
 *
 * A REUSABLE BUNDLE IS NOT AUTOCONFIGURED, so the tag goes on by hand:
 *
 *     $services->set('team.area_sections', DepartmentAreaSections::class)
 *         ->tag(AreaSectionsInterface::TAG);
 */
interface AreaSectionsInterface
{
    /**
     * The tag that puts a contribution on the strip.
     */
    public const string TAG = 'uhifadhi.area_sections';

    /**
     * THE SECTIONS THIS BUNDLE ADDS TO ONE AREA'S CONFIGURE PAGE.
     *
     * The area is passed as the identifier and the name it is known by, never
     * as an entity: the contributor addresses its own routes with the uuid and
     * writes the name into its own words, and neither bundle learns the other's
     * classes.
     *
     * @return list<ConfigurationSection>
     */
    public function sectionsFor(string $areaUuid, string $areaName): array;
}
