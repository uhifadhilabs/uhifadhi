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

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Uhifadhi\Bundle\ShellBundle\Model\CorePart;
use Uhifadhi\Bundle\ShellBundle\Service\Installation;
use Uhifadhi\Core\Tests\Application\Kernel;

/**
 * THE WHOLE CORE, AS THE PAGE THAT REPORTS ON AN INSTALLATION READS IT.
 *
 * A bundle's own suite can only ever see the parts its own kernel boots. This
 * one boots the installation — every core bundle in one kernel, which is what a
 * fresh installation is — so it is the only place the full list is a fact
 * rather than a subset, and the order is the one the welcome page prints — the
 * contracts first, because everything else is written against them, then the
 * bundles in the order the application registered them.
 */
final class WelcomeListsTheCoreTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function tearDown(): void
    {
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

    public function testEveryPartOfTheCoreIsListedBeneathTheCoresOwnRow(): void
    {
        self::bootKernel();

        /** @var array<string, string> $bundles */
        $bundles = self::getContainer()->getParameter('kernel.bundles');

        $installation = new Installation();
        $parts = $installation->coreParts($installation->coreInstallPath(), $bundles);

        self::assertSame([
            'uhifadhi/contracts',
            'uhifadhi/registry-bundle',
            'uhifadhi/shell-bundle',
            'uhifadhi/atlas-bundle',
            'uhifadhi/team-bundle',
            'uhifadhi/area-bundle',
        ], array_map(static fn (CorePart $part): string => $part->name, $parts));
    }

    /** Each part says what it is, in the one line its own manifest carries. */
    public function testEveryPartDescribesItself(): void
    {
        self::bootKernel();

        /** @var array<string, string> $bundles */
        $bundles = self::getContainer()->getParameter('kernel.bundles');

        $installation = new Installation();

        foreach ($installation->coreParts($installation->coreInstallPath(), $bundles) as $part) {
            self::assertNotSame('', $part->description, $part->name.' describes itself with nothing.');
        }
    }
}
