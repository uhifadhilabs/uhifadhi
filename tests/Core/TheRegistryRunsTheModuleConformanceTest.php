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

use PHPUnit\Framework\Attributes\CoversNothing;
use Uhifadhi\Bundle\RegistryBundle\Access\RegistryConcerns;
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;
use Uhifadhi\Bundle\TeamBundle\Test\AccessConformanceTestCase;
use Uhifadhi\Contracts\Access\ConcernSourceInterface;

/**
 * THE CATALOGUE'S ONE CONCERN, through the base a module extends.
 *
 * IT IS ITS OWN FILE, AND THAT IS NOT TIDINESS. PHPUnit collects the one
 * class whose name matches the file; three conformance classes in one file
 * meant two of them never ran, and a conformance nobody runs asserts
 * nothing.
 */
#[CoversNothing]
final class TheRegistryRunsTheModuleConformanceTest extends AccessConformanceTestCase
{
    protected static function source(): ConcernSourceInterface
    {
        return new RegistryConcerns();
    }

    protected static function bundlePath(): string
    {
        return \dirname((string) (new \ReflectionClass(RegistryBundle::class))->getFileName());
    }
}
