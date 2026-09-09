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
use Uhifadhi\Bundle\ShellBundle\Model\CorePart;
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

    /** The one package whose insides the shell may open, because it is inside it. */
    public const string CORE = 'uhifadhi/uhifadhi';

    /**
     * The namespace a core bundle's class sits under. It is the test for
     * whether a registered bundle is part of the core: everything else in a
     * kernel belongs to somebody the shell knows nothing about.
     */
    private const string CORE_NAMESPACE = 'Uhifadhi\\Bundle\\';

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

    /**
     * Where composer put the core, or null on an installation where it cannot
     * say — a package resolved through a `replace` has no directory to point
     * at, and neither does one nobody installed.
     */
    public function coreInstallPath(): ?string
    {
        return InstalledVersions::isInstalled(self::CORE) ? InstalledVersions::getInstallPath(self::CORE) : null;
    }

    /**
     * WHAT THE CORE IS MADE OF — one entry per part of it this installation
     * actually runs.
     *
     * The core is one install and one row, and one row says nothing about the
     * parts inside it. Each part carries its own manifest, so what is printed
     * is what that part says about itself: the package name it would answer to
     * on its own, and its own one-line description. No version: they ride with
     * the core, and a version beside each would invite somebody to update one
     * alone.
     *
     * INSTALLED MEANS REGISTERED. A part is listed only when the kernel boots
     * it — a directory nothing registers is a directory, and telling an
     * operator otherwise is telling them a screen exists where none does. The
     * contracts are the exception and always lead the list: they are what every
     * other part is written against rather than a bundle anything registers.
     *
     * @param string|null           $installPath where composer put the core, or null when it cannot say
     * @param array<string, string> $bundles     the kernel's registered bundles, name => class name
     *
     * @return list<CorePart>
     */
    public function coreParts(?string $installPath, array $bundles): array
    {
        if (null === $installPath) {
            return [];
        }

        $parts = [];
        $contracts = $this->partAt($installPath.'/src/Uhifadhi/Contracts/composer.json');
        if (null !== $contracts) {
            $parts[] = $contracts;
        }

        foreach ($bundles as $class) {
            if (!str_starts_with($class, self::CORE_NAMESPACE)) {
                continue;
            }

            $directory = substr($class, \strlen(self::CORE_NAMESPACE), strrpos($class, '\\') - \strlen(self::CORE_NAMESPACE));
            $part = $this->partAt($installPath.'/src/Uhifadhi/Bundle/'.$directory.'/composer.json');
            if (null !== $part) {
                $parts[] = $part;
            }
        }

        return $parts;
    }

    /**
     * One manifest, read. Anything unreadable or unnamed is left out rather
     * than printed half-known: a page that reports on an installation reports
     * what it can read.
     */
    private function partAt(string $manifest): ?CorePart
    {
        if (!is_file($manifest)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($manifest), true);
        if (!\is_array($json) || !\is_string($json['name'] ?? null)) {
            return null;
        }

        return new CorePart(
            name: $json['name'],
            description: \is_string($json['description'] ?? null) ? $json['description'] : '',
        );
    }
}
