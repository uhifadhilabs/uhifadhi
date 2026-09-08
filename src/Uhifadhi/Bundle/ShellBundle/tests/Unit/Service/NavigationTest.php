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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\ShellBundle\Contract\NavigationSourceInterface;
use Uhifadhi\Bundle\ShellBundle\Model\NavItem;
use Uhifadhi\Bundle\ShellBundle\Model\NavSection;
use Uhifadhi\Bundle\ShellBundle\Service\Navigation;

/**
 * THE COLLECTOR, ON ITS OWN — and the rule it exists to keep: a heading is a
 * place in the sidebar, not a thing a source owns. Two modules that both file a
 * row under "System" have named the same place, and the drawing has one of it.
 */
final class NavigationTest extends TestCase
{
    public function testTwoSourcesFilingUnderTheSameHeadingProduceOneSection(): void
    {
        $navigation = new Navigation([
            self::source(new NavSection('System', [new NavItem(label: 'Files', url: '/files')], position: 30)),
            self::source(new NavSection('System', [new NavItem(label: 'Telemetry', url: '/telemetry')], position: 90)),
        ]);

        $sections = $navigation->sections();

        self::assertCount(1, $sections, 'One heading, both rows — a second "System" heading is the same place drawn twice.');
        self::assertSame('System', $sections[0]->label);
        self::assertSame(
            ['Files', 'Telemetry'],
            array_map(static fn (NavItem $item): string => $item->label, $sections[0]->items),
        );
    }

    public function testAMergedSectionSitsWhereItsEarliestContributorAsked(): void
    {
        $navigation = new Navigation([
            self::source(new NavSection('System', [new NavItem(label: 'Telemetry', url: '/telemetry')], position: 90)),
            self::source(new NavSection('System', [new NavItem(label: 'Files', url: '/files')], position: 30)),
            self::source(new NavSection('Organization', [new NavItem(label: 'Team', url: '/team')], position: 40)),
        ]);

        self::assertSame(
            ['System', 'Organization'],
            array_map(static fn (NavSection $section): string => $section->label, $navigation->sections()),
        );
    }

    public function testRowsInsideAMergedSectionFollowTheDeclaredPositionsNotRegistrationOrder(): void
    {
        $navigation = new Navigation([
            self::source(new NavSection('System', [new NavItem(label: 'Telemetry', url: '/telemetry')], position: 90)),
            self::source(new NavSection('System', [new NavItem(label: 'Files', url: '/files')], position: 30)),
        ]);

        self::assertSame(
            ['Files', 'Telemetry'],
            array_map(static fn (NavItem $item): string => $item->label, $navigation->sections()[0]->items),
        );
    }

    public function testTheOneLitRowRuleReadsTheMergedSection(): void
    {
        $navigation = new Navigation([
            self::source(new NavSection('System', [new NavItem(label: 'Files', url: '/files', current: true)])),
            self::source(new NavSection('System', [new NavItem(label: 'Telemetry', url: '/telemetry', current: true)])),
        ]);

        $this->expectException(\LogicException::class);

        $navigation->sections();
    }

    private static function source(NavSection ...$sections): NavigationSourceInterface
    {
        return new class(array_values($sections)) implements NavigationSourceInterface {
            /** @param list<NavSection> $sections */
            public function __construct(private readonly array $sections)
            {
            }

            public function sections(): iterable
            {
                yield from $this->sections;
            }
        };
    }
}
