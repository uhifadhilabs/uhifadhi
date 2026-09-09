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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web;

use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Overview\PulseEvent;
use Uhifadhi\Bundle\AreaBundle\Overview\PulseProviderInterface;

/**
 * A MODULE'S PULSE CONTRIBUTION, STOOD IN FOR.
 *
 * The register card's "last check-in" and its "last activity" sort read the most
 * recent move any installed module made in the area, through
 * {@see PulseProviderInterface}. This module depends on no real module, so the
 * suite ships its own contributor over the seam: what is tested is that the host
 * learns WHEN an area was last touched where the module is on, not WHAT moved.
 *
 * It returns one move, six minutes before now, so the card's "last check-in"
 * resolves to a recent, readable time.
 */
final readonly class FakePulse implements PulseProviderInterface
{
    public function __construct(private string $slug)
    {
    }

    public function moduleSlug(): string
    {
        return $this->slug;
    }

    public function pulseFor(AreaOfInterest $area, \DateTimeImmutable $since, \DateTimeImmutable $now): array
    {
        return [
            new PulseEvent(
                at: $now->modify('-6 minutes'),
                moduleSlug: $this->slug,
                moduleLabel: 'Patrols',
                recordRef: 'P-0145',
                move: 'patrol opened',
                summary: 'A patrol opened in the northern sector',
                url: '/x',
                swatch: '#3ED9A8',
            ),
        ];
    }
}
