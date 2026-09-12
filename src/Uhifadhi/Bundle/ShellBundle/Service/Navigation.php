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

namespace Uhifadhi\Bundle\ShellBundle\Service;

use Uhifadhi\Bundle\ShellBundle\Contract\NavigationSourceInterface;
use Uhifadhi\Bundle\ShellBundle\Model\NavItem;
use Uhifadhi\Bundle\ShellBundle\Model\NavSection;

/**
 * THE SIDEBAR'S CONTENT, COLLECTED — and nothing else.
 *
 * This class asks every registered source what goes in the nav, orders the
 * answers, checks the one invariant a nav has, and hands the result to a
 * template. It decides nothing about who may see a row: the shell holds no
 * authorization service and asks nothing about the viewer, because a renderer
 * with opinions about the team model would be the second place in the platform
 * where a permission is interpreted. A row the viewer may not have never
 * arrives here.
 *
 * READ LIVE. The sources are iterated on every call and nothing between them
 * and the sidebar caches, which is what makes "switch a module off" take effect
 * the same day rather than after a deploy.
 */
final class Navigation
{
    /**
     * @param iterable<NavigationSourceInterface> $sources the tagged contributors, in registration order
     */
    public function __construct(private readonly iterable $sources)
    {
    }

    /**
     * Every section, in declared-position order with registration as the
     * tie-break.
     *
     * ONE HEADING PER LABEL. A section label is a PLACE in the sidebar, not a
     * thing a source owns: two modules that both file a row under "System" have
     * named the same place, so the heading is drawn once with every contributed
     * row under it. A heading drawn twice would tell a reader there are two
     * Systems, which is a sidebar answering "where am I" with a lie.
     *
     * The merged section sits at the EARLIEST position any of its contributors
     * asked for, and its rows are ordered by the position each contribution
     * declared — so a module that files a row low in the System group stays low
     * whichever order the container happened to register the bundles in, which
     * is what the field is for at both levels.
     *
     * @return list<NavSection>
     */
    public function sections(): array
    {
        /** @var array<string, list<NavSection>> $contributions */
        $contributions = [];
        foreach ($this->sources as $source) {
            foreach ($source->sections() as $section) {
                $contributions[$section->label][] = $section;
            }
        }

        $sections = [];
        foreach ($contributions as $label => $contributed) {
            // Stable since PHP 8.0, which is what makes registration the
            // tie-break rather than an accident of the sort implementation.
            usort($contributed, static fn (NavSection $a, NavSection $b): int => $a->position <=> $b->position);

            $items = [];
            foreach ($contributed as $section) {
                $items = [...$items, ...$section->items];
            }

            $merged = new NavSection((string) $label, $items, $contributed[0]->position);
            $this->assertOneCurrent($merged->items, \sprintf('the "%s" section', $merged->label));
            $sections[] = $merged;
        }

        usort($sections, static fn (NavSection $a, NavSection $b): int => $a->position <=> $b->position);

        return $sections;
    }

    /**
     * EXACTLY ONE ROW IS CURRENT — among siblings, at every level of the tree.
     *
     * "Where am I" is the sidebar's whole job and two lit rows answer it worse
     * than none, so the shell refuses rather than rendering a nav that cannot
     * be read. The check is per sibling list rather than per sidebar, and the
     * distinction is the tree's: the place you are inside and the row you are on
     * within it are both marked, on purpose — that is one PATH, drawn, and the
     * template spends the accent on the deeper of the two. Two marked rows side
     * by side is a contradiction; a marked row inside a marked branch is a
     * location.
     *
     * Zero is allowed and always will be: a viewer can be somewhere the nav
     * does not list, and a shell that refused to draw a sidebar on a sign-in
     * page would be a shell nobody could sign in to.
     *
     * @param list<NavItem> $items
     */
    private function assertOneCurrent(array $items, string $where): void
    {
        $lit = [];
        foreach ($items as $item) {
            if ($item->current) {
                $lit[] = $item->label;
            }
            $this->assertOneCurrent($item->children, \sprintf('the children of "%s"', $item->label));
        }

        if (\count($lit) > 1) {
            throw new \LogicException(\sprintf('The sidebar must light exactly one row among siblings, and %s lights %d of them (%s). Two lit rows answer "where am I" worse than none — the source decides which one it is.', $where, \count($lit), implode(', ', $lit)));
        }
    }
}
