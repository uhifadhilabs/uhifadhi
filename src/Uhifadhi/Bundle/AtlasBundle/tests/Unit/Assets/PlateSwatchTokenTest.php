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

namespace Uhifadhi\Bundle\AtlasBundle\Tests\Unit\Assets;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Contracts\Atlas\PlatePalette;

/**
 * A MODULE NAMES A COLOUR AND THE PLATE PAINTS IT.
 *
 * THE DEFECT THIS EXISTS FOR: a layer published `var(--plate-ok)`, the
 * legend row drew it correctly — a `style` attribute is CSS and resolves
 * a custom property — and the MAP drew nothing at all, because Leaflet
 * takes a colour and a `var(...)` string is not one. The page was right
 * in the half a stylesheet touched and empty in the half JavaScript
 * painted, which is the hardest kind of wrong to see.
 *
 * SO THE NAME IS RESOLVED WHERE IT IS DRAWN, and what is checked here is
 * the whole chain: the palette publishes names, the shell defines them,
 * and the controller resolves them against the plate rather than handing
 * them to Leaflet.
 *
 * WHAT A TEXT CHECK OVER THE SHIPPED ASSET CANNOT SAY: that the browser
 * ends up painting the pixel. There is no JavaScript runner in this
 * package, so the last step — Leaflet receiving `#3ED9A8` — is asserted
 * as the two facts that compose it (the token the module names, and the
 * value the shell gives it after dark) plus the resolution being wired
 * at all. A rendered check belongs to whoever opens the page.
 */
#[CoversNothing]
final class PlateSwatchTokenTest extends TestCase
{
    /**
     * THE CONTRACT, END TO END, in the two halves this package can hold:
     * a module publishing `PlatePalette::OK` has named `--plate-ok`, and
     * `--plate-ok` is `#3ED9A8` on the dark plate.
     */
    public function testALayerColouredWithTheOkTokenPaintsTheJadeTheShellDefines(): void
    {
        self::assertSame('var(--plate-ok)', PlatePalette::OK);

        self::assertMatchesRegularExpression(
            '/--plate-ok:\s*#3ED9A8;/i',
            self::shellStylesheet(),
            'The token a module names must resolve to the plate jade; imagery is dark under both themes.',
        );
    }

    /** Every name the palette publishes is a token the shell actually defines. */
    public function testEveryPublishedNameIsATokenTheShellDefines(): void
    {
        $css = self::shellStylesheet();

        foreach (PlatePalette::NAMES as $name) {
            $token = substr($name, 4, -1);

            self::assertMatchesRegularExpression(
                '/'.preg_quote($token, '/').':\s*[^;]+;/',
                $css,
                \sprintf('%s is published to modules and defined nowhere; a layer naming it paints nothing.', $name),
            );
        }
    }

    /** And a category resolves to the plate's own reading of the nine. */
    public function testACategoryResolvesToThePlatesReadingAndNotTheThemesOne(): void
    {
        self::assertSame('var(--cat-p-3)', PlatePalette::category(3));
        self::assertMatchesRegularExpression('/--cat-p-3:\s*[^;]+;/', self::shellStylesheet());
    }

    /**
     * THE CONTROLLER RESOLVES A NAME RATHER THAN HANDING IT TO LEAFLET,
     * and it resolves against the PLATE, so a token an installation
     * redefined for one surface is the one that surface paints.
     */
    public function testThePlateResolvesATokenAgainstItselfBeforeDrawing(): void
    {
        $controller = self::controller();

        self::assertStringContainsString('getComputedStyle(this.element).getPropertyValue(name)', $controller);
        self::assertStringContainsString('paint(this.colour(', $controller);
    }

    /**
     * AND EVERY COLOUR IN THE MERGED STYLE, not only the layer's own
     * swatch: a per-feature rule carries a token through the same door,
     * and one left unresolved is one feature drawn as nothing.
     */
    public function testAPerFeatureRulesColourIsResolvedToo(): void
    {
        self::assertMatchesRegularExpression(
            "/for \(const key of \['color', 'fillColor', 'fill', 'stroke'\]\)/",
            self::controller(),
        );
    }

    /**
     * AND AGAIN WHEN THE LIGHTS CHANGE. The theme is a class on <html>
     * flipped without a reload, so every colour resolved under the old
     * palette is wrong from that moment until something redraws it.
     */
    public function testEveryLayerIsRepaintedWhenTheThemeFlips(): void
    {
        $controller = self::controller();

        self::assertStringContainsString('new MutationObserver(this.onThemeFlip)', $controller);
        self::assertStringContainsString("attributeFilter: ['class']", $controller);
        self::assertStringContainsString('this.swatches.clear();', $controller);
        self::assertStringContainsString('drawn.setStyle((feature) => this.styleFor(spec, feature));', $controller);
    }

    private static function controller(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 3).'/assets/controllers/map_plate_controller.js');
    }

    private static function shellStylesheet(): string
    {
        $shell = \dirname(new \ReflectionClass(ShellBundle::class)->getFileName() ?: '');

        return (string) file_get_contents($shell.'/public/shell.css');
    }
}
