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
     * Every uhifadhi package on disk, the two the shell can describe first and
     * the rest in composer's own order — a reading of the vendor directory, and
     * therefore an answer that changes the same day an installation does.
     *
     * @return list<InstalledPackage>
     */
    public function packages(): array
    {
        $described = [];
        $rest = [];

        foreach (InstalledVersions::getInstalledPackages() as $name) {
            if (!str_starts_with($name, self::VENDOR)) {
                continue;
            }

            $package = new InstalledPackage(
                name: $name,
                version: InstalledVersions::getPrettyVersion($name) ?? 'dev',
                note: self::NOTES[$name] ?? null,
            );

            if (null !== $package->note) {
                $described[] = $package;
            } else {
                $rest[] = $package;
            }
        }

        return array_merge($described, $rest);
    }
}
