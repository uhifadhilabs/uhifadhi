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
 * ONE LINE OF "WHAT THIS INSTALLATION HAS": a composer package and its version.
 *
 * A package, not a module. The shell recognises nothing here — it prints the
 * name the vendor directory reports and compares it to nothing — which is what
 * keeps a live list of what is installed on the right side of the rule that the
 * shell knows no module by name.
 *
 * `note` is the one line the shell can say about a package — and it can say one
 * about exactly two of them, itself and the registry it renders beside. It is null
 * for everything else, on purpose: a shell that described a module would be a
 * shell that knew what modules are, and the description it invented would be
 * out of date in the repository it was guessing about.
 */
final class InstalledPackage
{
    /**
     * @param string      $name    the composer name, e.g. "uhifadhi/<name>-module"
     * @param string      $version what composer reports it as, pretty
     * @param string|null $note    what this package is, if the shell can say
     */
    public function __construct(
        public string $name,
        public string $version,
        public ?string $note = null,
    ) {
    }
}
