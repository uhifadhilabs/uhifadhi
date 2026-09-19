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

namespace Uhifadhi\Bundle\TeamBundle\Performance;

use Uhifadhi\Contracts\Kpi\FigurePeriod;
use Uhifadhi\Contracts\Performance\PerformanceScope;
use Uhifadhi\Contracts\Performance\PerformanceTopicProviderInterface;

/**
 * THE OVERVIEW'S TOP ROW: one card a topic, and where its headline
 * figure moved.
 *
 * THE TOPIC'S OWN FIRST FIGURE, the same rule the overview's matrix
 * uses for its columns — a topic decided which of its five leads, and
 * the host does not get a second opinion about somebody else's figures.
 *
 * A TOPIC WITH NO FIGURE YET IS STILL A CARD. Dropping it would make
 * the row shorter on a quiet month, and a reader cannot tell a short
 * row from a missing topic; the card says "no figure yet" in words.
 */
final readonly class TopicCards
{
    /**
     * @param list<PerformanceTopicProviderInterface> $topics in the page's order
     * @param (\Closure(string): ?string)|null        $urlFor the way into one topic, by its key
     *
     * @return list<TopicCard>
     */
    public function build(
        array $topics,
        PerformanceScope $scope,
        FigurePeriod $period,
        ?\Closure $urlFor = null,
    ): array {
        $cards = [];
        foreach ($topics as $topic) {
            $kpi = $topic->kpis($scope, $period)[0] ?? null;
            $byModule = PerformanceTopicProviderInterface::HOST !== $topic->moduleSlug();
            $url = null === $urlFor ? null : $urlFor($topic->key());

            if (null === $kpi) {
                $cards[] = new TopicCard(
                    key: $topic->key(),
                    title: $topic->title(),
                    label: '',
                    figure: '',
                    word: 'publishes no figure',
                    byModule: $byModule,
                    url: $url,
                );

                continue;
            }

            $cards[] = new TopicCard(
                key: $topic->key(),
                title: $topic->title(),
                label: $kpi->label,
                figure: null === $kpi->value ? '' : Figures::figure($kpi->value),
                unit: $kpi->unit,
                delta: Figures::delta($kpi->delta),
                deltaTone: Figures::tone($kpi->delta, $kpi->polarity),
                spark: Figures::spark($kpi->history, Figures::CARD_WIDTH, Figures::CARD_HEIGHT),
                sparkTone: Figures::sparkTone($kpi->delta, $kpi->polarity),
                // A FIGURE NOBODY PUBLISHED SAYS SO IN WORDS. A blank
                // card reads as a nought, and a nought is a measurement.
                word: null === $kpi->value ? 'no figure yet' : '',
                caption: $kpi->caption,
                byModule: $byModule,
                url: $url,
            );
        }

        return $cards;
    }
}
