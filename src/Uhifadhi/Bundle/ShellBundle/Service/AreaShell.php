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

use Uhifadhi\Bundle\ShellBundle\Contract\AreaShellSourceInterface;
use Uhifadhi\Bundle\ShellBundle\Model\AreaTab;

/**
 * THE TAB STRIP'S MODEL: where the viewer is, and its sibling screens.
 *
 * One source, two renderings — the strip above the page body and the branch in
 * the sidebar's location tree read this same list, which is the defect this
 * fixes. The host kept two hand-written copies of it and they had to be edited
 * together, which is the tell.
 *
 * The source is OPTIONAL. A fresh installation has no places to have
 * sibling screens of, and it gets pages with no strip rather than a container
 * that will not compile.
 */
final class AreaShell
{
    public function __construct(private readonly ?AreaShellSourceInterface $source = null)
    {
    }

    /**
     * The strip, as it should render — which is sometimes not at all.
     *
     * ONE TAB IS NOT A CHOICE: a lone underlined word is furniture pretending
     * to be navigation, so a place whose viewer can reach exactly one of its
     * screens gets no strip. The list is still validated first, because "you
     * gave me two current tabs" is worth hearing even on the render where the
     * strip would have been dropped anyway.
     *
     * @return list<AreaTab>
     */
    public function tabs(): array
    {
        $tabs = [];
        foreach ($this->source?->tabs() ?? [] as $tab) {
            $tabs[] = $tab;
        }

        $this->assertOneCurrent($tabs);

        return \count($tabs) < 2 ? [] : $tabs;
    }

    /**
     * What the viewer is inside, in words, for the page title. Null when the
     * viewer is not inside anything — which is most of a fresh installation.
     */
    public function place(): ?string
    {
        $place = $this->source?->place();

        return '' === $place ? null : $place;
    }

    /**
     * EXACTLY ONE TAB IS LIT. A strip's only job is to say which of these
     * sibling screens you are on; it cannot say two, and if it says none the
     * viewer is looking at a row of links that does not include the page they
     * are reading.
     *
     * @param list<AreaTab> $tabs
     */
    private function assertOneCurrent(array $tabs): void
    {
        if ([] === $tabs) {
            return;
        }

        $lit = [];
        foreach ($tabs as $tab) {
            if ($tab->current) {
                $lit[] = $tab->label;
            }
        }

        if (1 !== \count($lit)) {
            throw new \LogicException(\sprintf('A tab strip lights exactly one tab, and this one lights %d of %d. The source decides which; the shell will not guess.', \count($lit), \count($tabs)));
        }
    }
}
