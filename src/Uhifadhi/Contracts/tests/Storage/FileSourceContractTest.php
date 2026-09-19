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

namespace Uhifadhi\Contracts\Tests\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Contracts\Storage\FileSourceInterface;

/**
 * THE SHAPE A MODULE IMPLEMENTS TO SAY THAT IT STORES FILES.
 *
 * TWO VERBS AND NO MORE. Everything else the files hub prints about a source
 * — how many files, how many bytes, which record each belongs to, whether it
 * may be removed — is already on the file rows or is the owning record's
 * answer. The two facts here are the only two nothing anywhere states.
 *
 * THE WORD IS THE MODULE'S. The hub has no word of its own for somebody
 * else's files, and a hub that invented one would be a hub that named a
 * module's records for it.
 */
#[CoversClass(FileSourceInterface::class)]
final class FileSourceContractTest extends TestCase
{
    public function testTheTagIsPublishedOnTheInterfaceSoNobodySpellsIt(): void
    {
        self::assertSame('uhifadhi.file_source', FileSourceInterface::TAG);
    }

    /**
     * IT STARTS WITH `moduleSlug()`, like every other seam, and that is how a
     * source disappears when an installation removes the module.
     */
    public function testASourceNamesItsModuleAndItsWordForAFile(): void
    {
        $source = new class implements FileSourceInterface {
            public function moduleSlug(): string
            {
                return 'patrol-module';
            }

            public function fileWord(): string
            {
                return 'an observation’s photographs';
            }
        };

        self::assertSame('patrol-module', $source->moduleSlug());
        self::assertSame('an observation’s photographs', $source->fileWord());
    }

    /** The contract asks for those two and nothing else. */
    public function testTheContractAsksForNothingElse(): void
    {
        self::assertSame(
            ['moduleSlug', 'fileWord'],
            array_map(
                static fn (\ReflectionMethod $m): string => $m->getName(),
                new \ReflectionClass(FileSourceInterface::class)->getMethods(),
            ),
        );
    }
}
