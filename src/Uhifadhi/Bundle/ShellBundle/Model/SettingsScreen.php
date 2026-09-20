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
 * ONE RESOLVED SCREEN OF THE SETTINGS SECTION — which template draws it, and
 * what that template is given.
 *
 * THE CONTROLLER CARRIES NO BRANCH, which is the point of resolving a screen
 * into a value object at all. Four tabs behind one address would otherwise be
 * four actions or a match statement, and either one is a second place — beside
 * the tab set itself — where the section's shape is written down.
 */
final readonly class SettingsScreen
{
    /**
     * @param string               $template  the twig that draws this screen
     * @param array<string, mixed> $variables everything it is given
     */
    public function __construct(
        public string $template,
        public array $variables,
    ) {
    }
}
