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
 * This bundle depends on no real module, so the suite ships its own contributor
 * over the registry: what is tested is that the area page COUNTS a contributed item where
 * the module is switched on, not what any module raises.
 *
 * It raises two items, so the flag reads a plural count.
 */
final readonly class FakeAttention implements AttentionProviderInterface
{
    /**
     * @param int $items how many it raises — two by default, and as many as a
     *                   test needs to prove a card is bounded
     */
    public function __construct(private string $slug, private int $items = 2)
    {
    }

    public function moduleSlug(): string
    {
        return $this->slug;
    }

    public function attentionFor(AreaOfInterest $area, \DateTimeImmutable $now): array
    {
        $raised = [];
        for ($i = 0; $i < $this->items; ++$i) {
            $raised[] = new AttentionItem(
                severity: 0 === $i % 2 ? AttentionSeverity::Now : AttentionSeverity::Soon,
                moduleSlug: $this->slug,
                moduleLabel: 'Patrols',
                headline: 0 === $i % 2 ? 'A patrol has stopped pinging' : 'A track was never filed',
                kind: 0 === $i % 2 ? 'live position' : 'unfiled',
                ageLabel: '2 h 10',
                ageSeconds: 7800 + $i,
                url: '/x/'.$i,
            );
        }

        return $raised;
    }
}
