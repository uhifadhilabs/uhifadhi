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

namespace Uhifadhi\Bundle\AreaBundle\Shell;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;
use Uhifadhi\Bundle\AreaBundle\Repository\ZoneRepository;
use Uhifadhi\Bundle\AreaBundle\Service\AreaRegister;
use Uhifadhi\Contracts\Shell\ConfigurationSection;
use Uhifadhi\Contracts\Shell\ConfigurationSectionsInterface;

/**
 * WHAT IS ON AN AREA'S CONFIGURE PAGE — declared through exactly the contract a
 * module declares its own through.
 *
 * THE AREA IS NOT A SPECIAL CASE, and that is the whole reason it goes through
 * here. One `Configure` action, one page, one strip: if the area had its own
 * arrangement, the rule would already have an exception on the day it shipped,
 * and the second exception is always easier than the first.
 *
 * IT RESOLVES THE REQUEST ITSELF, like every other source in the frame. The
 * shell passes nothing — it has a slug, not an area — so the heading is composed
 * here, from the area the viewer is actually configuring.
 *
 * THE SETTINGS SECTION IS WHERE THE OLD `/settings` SCREEN WENT. Its content is
 * unchanged and its address is a permanent redirect; what it lost is a page
 * frame of its own and a tab in the area's strip, because reading what an area
 * IS is configuration and configuration is not a data place.
 */
final readonly class AreaConfigurationSections implements ConfigurationSectionsInterface
{
    public function __construct(
        private RequestStack $requests,
        private AreaOfInterestRepository $areas,
        private AreaRegister $register,
        private ZoneRepository $zones,
    ) {
    }

    public function slug(): string
    {
        return self::AREA;
    }

    /**
     * The area's own name, to which the shell adds " · configure". Empty when
     * the request names no area — a page the configure route never serves, but
     * a source that answers only on the pages it expects is a source that
     * throws on the one it did not.
     */
    public function heading(): string
    {
        return (string) $this->currentArea()?->getName();
    }

    public function summary(): string
    {
        return 'Everything this area is set up with, in one place: how its dashboard is composed, and the area’s own identity, access and zones.';
    }

    public function sections(): array
    {
        $area = $this->currentArea();

        $sections = [
            ConfigurationSection::page(
                ConfigurationSection::WIDGETS,
                'Widget library',
                '@Area/area/configure/_widgets.html.twig',
            ),
        ];

        /*
         * A SECTION WITH NO AREA TO BE ABOUT IS WITHHELD rather than rendered
         * over nothing. It can only happen on a request the configure route does
         * not serve, and answering it with an empty record would be worse than
         * answering it with a shorter strip.
         */
        if (null !== $area) {
            $sections[] = ConfigurationSection::page(
                ConfigurationSection::SETTINGS,
                'Area settings',
                '@Area/area/configure/_settings.html.twig',
                [
                    'area' => $area,
                    'areaKm2' => $this->register->areaKm2($area),
                    'zoneCount' => $this->zones->countFor($area),
                ],
            );
        }

        return $sections;
    }

    private function currentArea(): ?AreaOfInterest
    {
        $uuid = $this->requests->getCurrentRequest()?->attributes->get('uuid');

        if (!\is_string($uuid) || !Uuid::isValid($uuid)) {
            return null;
        }

        return $this->areas->findOneBy(['uuid' => Uuid::fromString($uuid)]);
    }
}
