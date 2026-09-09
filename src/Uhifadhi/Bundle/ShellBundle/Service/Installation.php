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

use Composer\InstalledVersions;
use Uhifadhi\Bundle\ShellBundle\Model\InstalledPackage;

/**
 * WHAT THIS INSTALLATION IS MADE OF, asked of Composer rather than remembered.
 *
 * Naming the installed packages in prose is accurate for exactly one
 * installation and wrong the first time anybody follows the instruction the
 * welcome screen itself gives. A screen that reports on an installation has to
 * read the installation.
 *
 * IT IS STILL NOT A CATALOGUE. This reads the vendor directory, not the registry:
 * it knows which packages are on disk and nothing whatever about what they can
 * do, which areas they serve or whether they have pages at all. A module's
 * capabilities reach the shell through the contracts, as data, exactly as before —
 * and nothing here is compared to a name, so no slug is ever recognised.
 *
 * The runtime API is a composer-runtime-api requirement, so there is no
 * class_exists dance: an installation of this package has it by definition.
 */
final class Installation
{
    /**
     * THE ONE PACKAGE THE SHELL CAN SPEAK FOR, and it can speak for no others.
     *
     * The core is what an installation starts as, and the sentence the whole
     * platform is built on names it: a module registers with the registry and
     * renders in the shell, and both ship here. Every other package is printed
     * by name and version with no description at all — a shell that described a
     * module would be a shell that knew what modules are.
     */
    private const array NOTES = [
        'uhifadhi/uhifadhi' => 'the core: where every module registers, and the frame around this very page',
    ];

    /** Everything the platform ships is a composer package under this vendor. */
    private const string VENDOR = 'uhifadhi/';

    /**
     * Every uhifadhi package on disk, the one the shell can describe first and
     * the rest in composer's own order — a reading of the vendor directory, and
     * therefore an answer that changes the same day an installation does.
     *
     * @return list<InstalledPackage>
     */
    public function packages(): array
    {
        return $this->packagesIn(InstalledVersions::getAllRawData());
    }

    /**
     * THE READING, GIVEN WHAT COMPOSER KNOWS — the raw data rather than the
     * convenient list, because the convenient list is the wrong list.
     *
     * `getInstalledPackages()` reports every name Composer can RESOLVE, and a
     * package that `replace`s others answers to all of them: the core alone
     * would print a row for each of the names it can be split into, all at the
     * core's own version, none of them a directory anybody has. What an
     * operator is being shown is what this installation is MADE of, and it is
     * made of directories.
     *
     * The raw data tells the two apart plainly: a real install carries an
     * `install_path`, and a merely-replaced name carries a `replaced` list and
     * no path, because there is nothing to point at.
     *
     * @see https://getcomposer.org/doc/07-runtime.md#installed-versions
     *
     * @param list<array{versions?: array<string, array<string, mixed>>}> $rawData
     *
     * @return list<InstalledPackage>
     */
    public function packagesIn(array $rawData): array
    {
        $described = [];
        $rest = [];
        $seen = [];

        foreach ($rawData as $set) {
            foreach ($set['versions'] ?? [] as $name => $entry) {
                // One directory, one row: a package can appear in more than one
                // raw-data set when several autoloaders are in play.
                if (!str_starts_with($name, self::VENDOR) || isset($seen[$name])) {
                    continue;
                }

                // No path is no install — the name is one this installation's
                // packages answer to, not a thing it has.
                if (!\is_string($entry['install_path'] ?? null)) {
                    continue;
                }

                $seen[$name] = true;

                $package = new InstalledPackage(
                    name: $name,
                    version: \is_string($entry['pretty_version'] ?? null) ? $entry['pretty_version'] : 'dev',
                    note: self::NOTES[$name] ?? null,
                );

                if (null !== $package->note) {
                    $described[] = $package;
                } else {
                    $rest[] = $package;
                }
            }
        }

        return array_merge($described, $rest);
    }
}
