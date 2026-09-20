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

namespace Uhifadhi\Contracts\Settings;

/**
 * ONE THING THAT CHANGED ABOUT THIS INSTALLATION, AND WHO CHANGED IT.
 *
 * The Settings overview's last card answers "what happened here recently" —
 * an upgrade, an import, a module switched on in an area, the installation
 * itself being created. It is a reading, not an audit trail: the trail is the
 * workflow-and-audit roadmap's, and a card that pretended to be one would be
 * trusted as one.
 *
 * THE INSTANT IS AN INSTANT, not a formatted string, so the page renders it
 * in the reader's own zone through the shell's `<time>` contract. A source
 * that formatted it would be deciding somebody else's timezone.
 */
final readonly class SettingsChange
{
    /**
     * @param string      $key  stable, and what a test names this row by
     * @param string      $what the change, in one fragment
     * @param string|null $who  the person, or null where nobody is recorded
     */
    public function __construct(
        public string $key,
        public \DateTimeImmutable $when,
        public string $what,
        public ?string $who = null,
    ) {
        if ('' === trim($key)) {
            throw new \InvalidArgumentException('A change is named by its key: it cannot be empty.');
        }

        if ('' === trim($what)) {
            throw new \InvalidArgumentException(\sprintf('The "%s" change says nothing: it cannot have an empty description.', $key));
        }
    }
}
