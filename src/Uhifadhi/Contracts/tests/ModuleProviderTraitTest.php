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

namespace Uhifadhi\Contracts\Tests;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Contracts\ModulePermission;
use Uhifadhi\Contracts\ModuleProviderInterface;
use Uhifadhi\Contracts\ModuleProviderTrait;

/**
 * The contract's only behaviour is the defaults trait: a provider that defines
 * just the three required methods is a valid ModuleProviderInterface with the
 * documented common-case defaults.
 */
final class ModuleProviderTraitTest extends TestCase
{
    private function minimalProvider(): ModuleProviderInterface
    {
        return new class implements ModuleProviderInterface {
            use ModuleProviderTrait;

            public function slug(): string
            {
                return 'example';
            }

            public function name(): string
            {
                return 'Example';
            }

            public function category(): string
            {
                return 'pressure';
            }
        };
    }

    public function testTraitSuppliesTheCommonCaseDefaults(): void
    {
        $provider = $this->minimalProvider();

        self::assertSame('example', $provider->slug());
        self::assertSame('Example', $provider->name());
        self::assertSame('pressure', $provider->category());
        self::assertSame('live', $provider->status());
        self::assertNull($provider->dataSource());
        self::assertFalse($provider->pinned());
        self::assertFalse($provider->base(), 'A module is installable until it says otherwise — base is the exception.');
        self::assertSame(0, $provider->position());
        self::assertNull($provider->icon());
        self::assertNull($provider->entryRoute(), 'A generically-rendered module has no own entry route.');
        self::assertSame([], $provider->permissions(), 'Most modules gate nothing beyond what the host already does.');
    }

    public function testAModuleDeclaresItsPermissionsWithoutGrantingThem(): void
    {
        $provider = new class implements ModuleProviderInterface {
            use ModuleProviderTrait;

            public function slug(): string
            {
                return 'example';
            }

            public function name(): string
            {
                return 'Example';
            }

            public function category(): string
            {
                return 'pressure';
            }

            public function permissions(): array
            {
                return [new ModulePermission(
                    'example.review',
                    'Example',
                    'Review',
                    'Look at what somebody else recorded and mark it settled.',
                )];
            }
        };

        $permissions = $provider->permissions();
        self::assertCount(1, $permissions);
        self::assertSame('example.review', $permissions[0]->value);
        self::assertSame('Example', $permissions[0]->umbrella);
        self::assertSame('Review', $permissions[0]->action);
        self::assertSame(
            'Look at what somebody else recorded and mark it settled.',
            $permissions[0]->description,
            'A declared permission carries the sentence the matrix prints under its name.',
        );
    }

    /**
     * THE DESCRIPTION IS REQUIRED, and that is the whole of this release's
     * change. An administrator ticking a box in the host's matrix is being
     * asked to grant a power, and "Example · Review" is a label rather than an
     * answer to what it lets somebody do. A default would have made the
     * sentence optional, which is the same as making it absent — so the
     * constructor takes four arguments and a module that has not thought about
     * the sentence does not compile.
     */
    public function testAPermissionCannotBeDeclaredWithoutADescription(): void
    {
        $reflection = new \ReflectionClass(ModulePermission::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertSame(4, $constructor->getNumberOfParameters());
        self::assertSame(4, $constructor->getNumberOfRequiredParameters(), 'No argument of a permission declaration is optional.');
        self::assertSame('description', $constructor->getParameters()[3]->getName());
    }

    /**
     * An empty sentence is refused rather than stored. A blank description is
     * indistinguishable from no description at the point it matters — under
     * the checkbox — and a module that ships one has answered the compiler
     * without answering the administrator.
     */
    public function testAnEmptyDescriptionIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ModulePermission('example.review', 'Example', 'Review', '   ');
    }

    public function testOverridesWinOverDefaults(): void
    {
        $provider = new class implements ModuleProviderInterface {
            use ModuleProviderTrait;

            public function slug(): string
            {
                return 'sightings';
            }

            public function name(): string
            {
                return 'Sightings';
            }

            public function category(): string
            {
                return 'biodiversity';
            }

            public function entryRoute(): ?string // @phpstan-ignore return.unusedType (signature must match the interface)
            {
                return 'sightings_area';
            }

            public function icon(): ?string // @phpstan-ignore return.unusedType (signature must match the interface)
            {
                return 'binoculars';
            }
        };

        self::assertSame('sightings_area', $provider->entryRoute(), 'A module owning its pages returns a route name.');
        self::assertSame('binoculars', $provider->icon());
        self::assertSame('live', $provider->status(), 'Untouched defaults still apply.');
    }

    /**
     * The base tier: platform machinery other surfaces already depend on says
     * so, and the host seeds it active rather than parked.
     */
    public function testABaseModuleSaysSo(): void
    {
        $provider = new class implements ModuleProviderInterface {
            use ModuleProviderTrait;

            public function slug(): string
            {
                return 'map';
            }

            public function name(): string
            {
                return 'Map';
            }

            public function category(): string
            {
                return 'operations';
            }

            public function base(): bool
            {
                return true;
            }
        };

        self::assertTrue($provider->base());
        self::assertFalse($provider->pinned(), 'Base is about the initial state, pinned is about the ordering — they are separate.');
    }
}
