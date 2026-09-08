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

namespace Uhifadhi\Bundle\ShellBundle\Contract;

use Uhifadhi\Bundle\ShellBundle\Model\AreaTab;

/**
 * WHERE THE VIEWER IS, AND WHICH SIBLING SCREENS IT HAS.
 *
 * Note the shape of the question: the shell passes nothing. It does not know
 * what an area is and has no entity to hand over, so the source resolves the
 * current request itself — which the host is already doing anyway, and which
 * keeps the area's type out of the shell entirely.
 *
 * The shell owns that sibling screens are an underlined strip, that the strip
 * sits between the page head and the body, that exactly one tab is lit, that a
 * tab the viewer may not have is ABSENT rather than disabled, and that one tab
 * is no strip at all. It owns not one tab's name: which screens a place has is
 * the host's model, and a shell carrying that list would need a release every
 * time the host grew a screen.
 *
 * A host points the shell at its implementation by aliasing the id the shell
 * looks for — an ALIAS, not a tagged collection, because two things claiming to
 * know where you are is exactly the disagreement this bundle exists to prevent:
 *
 *     $services->alias('shell.area_shell_source', App\Shell\AreaShell::class);
 *
 * The alias is OPTIONAL. An installation that has no such places at all — a
 * fresh installation — simply does not declare it, and the shell renders
 * pages with no strip rather than refusing to boot.
 */
interface AreaShellSourceInterface
{
    /**
     * The sibling screens of whatever the viewer is currently inside, in the
     * order they should render, with exactly one marked current. Withhold a
     * screen the viewer may not have; do not disable it.
     *
     * @return iterable<AreaTab>
     */
    public function tabs(): iterable;

    /**
     * WHAT THE VIEWER IS INSIDE, in words — the middle segment of the page
     * title, as in "Zones — Northern Reserve — Uhifadhi".
     *
     * It lives on this contract rather than on a third one because it is the same
     * question the tabs answer, asked for the title bar instead of the strip:
     * a source that can say which sibling screens a place has can say the
     * place's name, and a host that had to implement two interfaces to answer
     * one question would keep them in step by hand.
     *
     * Null when the viewer is not inside anything — a settings screen, a sign-in
     * page, an installation with no places yet. The shell then composes the
     * title out of what it does have.
     */
    public function place(): ?string;
}
