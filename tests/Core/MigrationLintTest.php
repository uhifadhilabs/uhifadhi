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

namespace Uhifadhi\Core\Tests\Core;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\AbstractMigration;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Uhifadhi\Core\Tests\Application\Kernel;
use Uhifadhi\Core\Tests\Core\Fixtures\Migrations\VersionDropsWhatNobodySignedFor;
use Uhifadhi\Core\Tests\Core\Fixtures\Migrations\VersionExpandsBackfillsAndContracts;
use Uhifadhi\Core\Tests\Core\Fixtures\Migrations\VersionRequiresAColumnNobodyFilled;

/**
 * THE RELEASE-SAFETY LOCK: every shipped version is read against the two rules
 * before it is anybody's upgrade.
 *
 * The initial versions pass both trivially — they create tables that were not
 * there and drop nothing — so the lint would be worth nothing if that were all
 * it was ever shown. Three fixtures under `tests/Core/Fixtures/migrations` break
 * one rule each and keep both, and they are asserted here, which is what makes
 * the pass over the shipped versions mean something.
 */
#[CoversClass(MigrationLinter::class)]
final class MigrationLintTest extends KernelTestCase
{
    private Connection $connection;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        parent::tearDown();

        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }

    public function testEveryShippedVersionKeepsBothRules(): void
    {
        $linter = new MigrationLinter();
        $files = glob(\dirname(__DIR__, 2).'/src/Uhifadhi/Bundle/*/migrations/Version*.php') ?: [];

        self::assertNotSame([], $files, 'the core ships migrations, so there is something to lint');

        foreach ($files as $file) {
            self::assertSame(
                [],
                $linter->violations($this->classIn($file), $file, $this->connection),
                basename($file),
            );
        }
    }

    public function testAColumnMadeRequiredOnAnExistingTableWithNoBackfillIsRefused(): void
    {
        $violations = $this->lint(VersionRequiresAColumnNobodyFilled::class);

        self::assertCount(1, $violations);
        self::assertStringContainsString('team_user.staff_number', $violations[0]);
        self::assertStringContainsString('only with a DEFAULT, or after an UPDATE in this same version', $violations[0]);
    }

    public function testADropWithNoSignedReleaseIsRefused(): void
    {
        $violations = $this->lint(VersionDropsWhatNobodySignedFor::class);

        self::assertCount(2, $violations, 'the column and the table are two separate decisions');
        self::assertStringContainsString('ALTER TABLE widget_preference DROP active_kind', $violations[0]);
        self::assertStringContainsString('DROP TABLE widget_custom_preset', $violations[1]);

        foreach ($violations as $violation) {
            self::assertStringContainsString('carries no @destructive marker', $violation);
        }
    }

    public function testExpandBackfillContractInOneVersionPasses(): void
    {
        self::assertSame([], $this->lint(VersionExpandsBackfillsAndContracts::class));
    }

    /**
     * @param class-string<AbstractMigration> $version
     *
     * @return list<string>
     */
    private function lint(string $version): array
    {
        $file = (new \ReflectionClass($version))->getFileName();
        self::assertIsString($file);

        return (new MigrationLinter())->violations($version, $file, $this->connection);
    }

    /**
     * The path IS the class name: `<bundle>/migrations/VersionN.php` is
     * `Uhifadhi\\Bundle\\<Bundle>\\Migrations\\VersionN`, which is exactly what the
     * psr-4 prefix in both manifests promises.
     *
     * @return class-string<AbstractMigration>
     */
    private function classIn(string $file): string
    {
        $bundle = basename(\dirname($file, 2));

        /** @var class-string<AbstractMigration> $fqcn */
        $fqcn = \sprintf('Uhifadhi\\Bundle\\%s\\Migrations\\%s', $bundle, basename($file, '.php'));

        self::assertTrue(class_exists($fqcn), $fqcn.' is not autoloadable');

        return $fqcn;
    }
}
