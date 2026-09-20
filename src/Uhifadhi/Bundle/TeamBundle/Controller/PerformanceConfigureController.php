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

namespace Uhifadhi\Bundle\TeamBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Performance\Comparison;
use Uhifadhi\Bundle\TeamBundle\Performance\MatrixPlacing;
use Uhifadhi\Bundle\TeamBundle\Performance\PeriodKind;
use Uhifadhi\Bundle\TeamBundle\Service\PerformanceTopics;
use Uhifadhi\Contracts\Performance\PerformanceScope;
use Uhifadhi\Contracts\Performance\PerformanceTopicProviderInterface;

/**
 * HOW THE PERFORMANCE SECTION IS SET UP — its one configure screen.
 *
 * READ-ONLY, AND THAT IS THE DESIGN, exactly as the departments
 * section's settings are. Every line here is a fact about how the
 * section reads: the window it opens on, what a period is compared
 * with, where the shades come from and which topics this installation
 * carries. A control that let one of them be changed here would be a
 * second place the rule lived — the window and the comparison are
 * chosen in the header, ON the page they are about, and that choice is
 * in the address so it can be sent to somebody.
 *
 * WHAT IT IS FOR, THEN. It is where a reader finds out WHY the page
 * says what it says: that a placing is made inside one column and one
 * band, that fewer than three figures place nothing, that a topic
 * arrives with a module and leaves with it. Those are the rules the
 * figures are read under, and until now they were only in the code.
 */
final readonly class PerformanceConfigureController
{
    /** The section's settings — what `Configure` opens, and the only entry in its strip. */
    public const string SETTINGS = 'team_performance_configure';

    public function __construct(
        private Environment $twig,
        private PerformanceTopics $topics,
    ) {
    }

    #[Route('/departments/performance/settings', name: self::SETTINGS, defaults: PerformanceController::SURFACE, methods: ['GET'])]
    #[IsGranted(PermissionEnum::TeamManage->value)]
    public function settings(): Response
    {
        $period = PeriodKind::Month->period(new \DateTimeImmutable());
        $topics = $this->topics->forScope(PerformanceScope::organisation(), $period);

        return new Response($this->twig->render('@Team/performance/configure.html.twig', [
            'kinds' => PeriodKind::labels(),
            // THE RULE'S OWN NUMBER, read from the one place that holds
            // it: a settings page that typed "3" would be the second
            // place the rule lived, and the two would part company.
            'fewest' => MatrixPlacing::FEWEST,
            'comparisons' => array_map(
                static fn (Comparison $case): string => $case->label($period),
                Comparison::cases(),
            ),
            'topics' => array_map(
                static fn (PerformanceTopicProviderInterface $topic): array => [
                    'title' => $topic->title(),
                    'key' => $topic->key(),
                    'publisher' => PerformanceTopicProviderInterface::HOST === $topic->moduleSlug()
                        ? 'the host'
                        : $topic->moduleSlug().' module',
                ],
                $topics,
            ),
        ]));
    }
}
