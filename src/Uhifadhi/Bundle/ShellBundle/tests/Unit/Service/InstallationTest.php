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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\ShellBundle\Model\InstalledPackage;
use Uhifadhi\Bundle\ShellBundle\Service\Installation;

/**
 * WHAT IS INSTALLED IS WHAT IS ON DISK — not every name Composer can resolve.
 *
 * A package that `replace`s other names makes Composer report all of them as
 * installed, so a list built from `getInstalledPackages()` prints one row per
 * name a single directory answers to. That is not what an operator is being
 * shown: they are being shown what this installation is made of, and it is made
 * of directories.
 *
 * Composer's raw data tells the two apart plainly. A real install carries an
 * `install_path`; a replaced name carries a `replaced` list and no path at all,
 * because there is nothing to point at.
 *
 * @see https://getcomposer.org/doc/07-runtime.md#installed-versions
 */
#[CoversClass(Installation::class)]
final class InstallationTest extends TestCase
{
    /**
     * Composer's own shape, trimmed to what is read: a real install, a name it
     * replaces, and a package from another vendor.
     *
     * @return list<array{versions: array<string, array<string, mixed>>}>
     */
    private static function rawData(): array
    {
        return [[
            'versions' => [
                'uhifadhi/uhifadhi' => [
                    'pretty_version' => '1.0.0',
                    'version' => '1.0.0.0',
                    'type' => 'library',
                    'install_path' => '/app/vendor/composer/../../',
                    'dev_requirement' => false,
                ],
                'uhifadhi/team-bundle' => [
                    'dev_requirement' => false,
                    'replaced' => ['1.0.0'],
                ],
                'uhifadhi/patrol-module' => [
                    'pretty_version' => '0.9.3',
                    'version' => '0.9.3.0',
                    'type' => 'symfony-bundle',
                    'install_path' => '/app/vendor/uhifadhi/patrol-module',
                    'dev_requirement' => false,
                ],
                'symfony/framework-bundle' => [
                    'pretty_version' => 'v8.1.0',
                    'version' => '8.1.0.0',
                    'type' => 'symfony-bundle',
                    'install_path' => '/app/vendor/symfony/framework-bundle',
                    'dev_requirement' => false,
                ],
            ],
        ]];
    }

    public function testANameThePackageOnlyReplacesIsNotAnInstall(): void
    {
        self::assertNotContains('uhifadhi/team-bundle', self::names(self::rawData()));
    }

    public function testTheCoreIsOneRowAtItsRealVersion(): void
    {
        $core = self::packages(self::rawData())[0];

        self::assertSame('uhifadhi/uhifadhi', $core->name);
        self::assertSame('1.0.0', $core->version);
        self::assertNotNull($core->note, 'the core is the one package the shell can speak for');
    }

    public function testEveryRealPackageUnderTheVendorIsListed(): void
    {
        self::assertSame(
            ['uhifadhi/uhifadhi', 'uhifadhi/patrol-module'],
            self::names(self::rawData()),
        );
    }

    /**
     * A MODULE IS PRINTED, NEVER DESCRIBED. The shell knows no module by name,
     * so the only row it can say anything about is the one it ships in.
     */
    public function testAModuleIsPrintedWithNoDescription(): void
    {
        self::assertNull(self::packages(self::rawData())[1]->note);
    }

    /**
     * An installation whose Composer data is the real thing still reads
     * cleanly — and reports the core exactly once, however many names it
     * answers to.
     */
    public function testTheRealInstallationReportsTheCoreExactlyOnce(): void
    {
        $names = array_map(static fn (InstalledPackage $p): string => $p->name, new Installation()->packages());

        self::assertSame(['uhifadhi/uhifadhi'], array_values(array_filter($names, static fn (string $n): bool => 'uhifadhi/uhifadhi' === $n)));
    }

    /**
     * @param list<array{versions: array<string, array<string, mixed>>}> $rawData
     *
     * @return list<InstalledPackage>
     */
    private static function packages(array $rawData): array
    {
        return new Installation()->packagesIn($rawData);
    }

    /**
     * @param list<array{versions: array<string, array<string, mixed>>}> $rawData
     *
     * @return list<string>
     */
    private static function names(array $rawData): array
    {
        return array_map(static fn (InstalledPackage $p): string => $p->name, self::packages($rawData));
    }
}
