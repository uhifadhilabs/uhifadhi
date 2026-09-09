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
use Uhifadhi\Bundle\AreaBundle\Overview\AttentionItem;
use Uhifadhi\Bundle\AreaBundle\Overview\AttentionProviderInterface;
use Uhifadhi\Bundle\AreaBundle\Overview\AttentionSeverity;

/**
 * A MODULE'S ATTENTION CONTRIBUTION, STOOD IN FOR.
 *
 * The register card's alert flag and the "With alerts" pill both count what is
 * asking for attention in an area, gathered through {@see AttentionProviderInterface}.
 * This module depends on no real module, so the suite ships its own contributor
 * over the seam: what is tested is that the host COUNTS a contributed item where
 * the module is switched on, not what any module raises.
 *
 * It raises two items, so the flag reads a plural count.
 */
final readonly class FakeAttention implements AttentionProviderInterface
{
    public function __construct(private string $slug)
    {
    }

    public function moduleSlug(): string
    {
        return $this->slug;
    }

    public function attentionFor(AreaOfInterest $area, \DateTimeImmutable $now): array
    {
        return [
            new AttentionItem(
                severity: AttentionSeverity::Now,
                moduleSlug: $this->slug,
                moduleLabel: 'Patrols',
                headline: 'A patrol has stopped pinging',
                kind: 'live position',
                ageLabel: '2 h 10',
                ageSeconds: 7800,
                url: '/x',
            ),
            new AttentionItem(
                severity: AttentionSeverity::Soon,
                moduleSlug: $this->slug,
                moduleLabel: 'Patrols',
                headline: 'A track was never filed',
                kind: 'unfiled',
                ageLabel: '1 d',
                ageSeconds: 86400,
                url: '/y',
            ),
        ];
    }
}
