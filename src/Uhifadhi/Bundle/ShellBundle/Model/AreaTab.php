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

namespace Uhifadhi\Bundle\ShellBundle\Model;

/**
 * ONE SIBLING SCREEN of whatever the viewer is currently inside.
 *
 * NOTE THE NON-NULLABLE URL, and that it is the opposite of {@see NavItem}'s.
 * The difference is real rather than an inconsistency: an inert NAV row is a
 * promise about the future — "this is planned" — while a greyed-out TAB is a
 * statement about the viewer: "a screen exists here and you are not trusted
 * with it", which is a worse product than not mentioning it at all. So a tab
 * the viewer may not have is withheld by the source and never reaches the
 * shell, and the value object has no url-less form for a template to grey out.
 */
final class AreaTab
{
    /**
     * @param string $label   what the tab says
     * @param string $url     where it goes; withhold the tab instead of nulling this
     * @param bool   $current whether this is the screen the viewer is on
     */
    public function __construct(
        public string $label,
        public string $url,
        public bool $current = false,
    ) {
    }
}
