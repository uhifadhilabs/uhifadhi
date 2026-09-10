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

namespace Uhifadhi\Contracts\Tests\Shell;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Contracts\Shell\ConfigurationSection;

/**
 * ONE SECTION OF A CONFIGURE PAGE, as a value object — and the point of the two
 * named constructors is that a section is EXACTLY ONE of the two shapes it can
 * take. A section the shell renders inside the configure page names a template;
 * a section that already has an address of its own names the route. A value
 * object that let a caller pass both would be a value object with a fourth
 * state nobody drew.
 */
final class ConfigurationSectionTest extends TestCase
{
    public function testASectionTheShellRendersNamesATemplateAndNoRoute(): void
    {
        $section = ConfigurationSection::page('settings', 'Settings', '@Patrol/configure/_settings.html.twig');

        self::assertSame('settings', $section->id);
        self::assertSame('Settings', $section->label);
        self::assertSame('@Patrol/configure/_settings.html.twig', $section->template);
        self::assertNull($section->routeName);
        self::assertSame([], $section->parameters);
        self::assertTrue($section->isRendered());
    }

    public function testASectionWithAnAddressOfItsOwnNamesTheRouteAndNoTemplate(): void
    {
        $section = ConfigurationSection::screen('widgets', 'Widget library', 'patrol_widgets', ['slug' => 'patrols']);

        self::assertSame('widgets', $section->id);
        self::assertSame('Widget library', $section->label);
        self::assertNull($section->template);
        self::assertSame('patrol_widgets', $section->routeName);
        self::assertSame(['slug' => 'patrols'], $section->parameters);
        self::assertFalse($section->isRendered());
    }

    /**
     * THE TWO IDS THE ORDER IS BUILT ON. The strip runs Widget library first and
     * Settings last on every configure page in the platform, so those two
     * positions are named rather than left to whoever writes the array — a
     * module that files its kinds in the middle gets the ruled order for free.
     */
    public function testTheTwoAnchoringIdsArePublished(): void
    {
        self::assertSame('widgets', ConfigurationSection::WIDGETS);
        self::assertSame('settings', ConfigurationSection::SETTINGS);
    }

    public function testASectionWithNoIdIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigurationSection::page('', 'Settings', '@Patrol/configure/_settings.html.twig');
    }

    public function testASectionWithNoLabelIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigurationSection::screen('widgets', '', 'patrol_widgets');
    }

    public function testASectionWithNoTemplateIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigurationSection::page('settings', 'Settings', '');
    }

    public function testASectionWithNoRouteIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConfigurationSection::screen('widgets', 'Widget library', '');
    }
}
