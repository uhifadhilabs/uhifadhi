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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Integration\Fixtures;

use Uhifadhi\Bundle\ShellBundle\Contract\NavigationSourceInterface;
use Uhifadhi\Bundle\ShellBundle\Model\AreaTab;
use Uhifadhi\Bundle\ShellBundle\Model\NavItem;
use Uhifadhi\Bundle\ShellBundle\Model\NavSection;

/**
 * A host's nav, in the smallest honest form: something that answers "what goes
 * in the sidebar" and nothing else. A real one folds areas, the viewer's
 * permissions and the contract's per-area ledger into the same answer; that it can
 * be replaced by four lines of fixture is the registry working.
 *
 * Read at call time, never at construction — the same-day promise.
 */
final class FixtureNavigationSource implements NavigationSourceInterface
{
    public function sections(): iterable
    {
        foreach (HostKernel::$navSources as $section) {
            yield $section;
        }

        if (!HostKernel::$mirrorAreaTabsIntoNav || [] === HostKernel::$areaTabs) {
            return;
        }

        // The sidebar's area branch, built from the SAME list the strip draws —
        // which is the point of the assertion that reads it back. Two hand-kept
        // copies is what the host has today.
        yield new NavSection('Observatory', [
            new NavItem(label: 'Areas', url: '/areas', icon: 'lucide:map', children: [
                new NavItem(
                    label: 'Test Area',
                    url: '/areas/x',
                    current: true,
                    open: true,
                    children: array_map(
                        static fn (AreaTab $tab): NavItem => new NavItem(
                            label: $tab->label,
                            url: $tab->url,
                            current: $tab->current,
                        ),
                        HostKernel::$areaTabs,
                    ),
                ),
            ]),
        ]);
    }
}
