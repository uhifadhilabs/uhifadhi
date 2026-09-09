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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Unit\Overview;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Bundle\AreaBundle\Overview\AttentionProviderInterface;
use Uhifadhi\Bundle\AreaBundle\Overview\MapLayerProviderInterface;
use Uhifadhi\Bundle\AreaBundle\Overview\NowTileProviderInterface;
use Uhifadhi\Bundle\AreaBundle\Overview\OverviewContributorInterface;
use Uhifadhi\Bundle\AreaBundle\Overview\OverviewCopyProviderInterface;
use Uhifadhi\Bundle\AreaBundle\Overview\PulseProviderInterface;

/**
 * THE TAG STRINGS ARE THE CONTRACT, and they are pinned here because a module
 * bundle CANNOT READ THESE CONSTANTS.
 *
 * A reusable bundle is not autoconfigured, so every contributor is tagged by
 * hand in its own extension — and it writes the tag as a STRING LITERAL, because
 * reading `OverviewContributorInterface::TAG` there would load a class from a
 * package that may not be on its build classpath. The literal and the constant
 * therefore have to be kept equal by something, and that something is this test.
 *
 * Change a string below and every module contributes into a tag
 * nobody collects: the widgets simply stop appearing, with no error anywhere.
 * That is precisely the failure this file exists to make loud.
 */
final class ContributionContractTest extends TestCase
{
    /**
     * The literals a module bundle writes by hand, copied from those bundles'
     * own extensions rather than from the constants — the whole point is that
     * the two are written down independently and compared here.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function tags(): iterable
    {
        yield 'widget provider' => [OverviewContributorInterface::TAG, 'uhifadhi.overview.widget_provider'];
        yield 'now tile' => [NowTileProviderInterface::TAG, 'uhifadhi.overview.now_tile'];
        yield 'attention' => [AttentionProviderInterface::TAG, 'uhifadhi.overview.attention'];
        yield 'map layer' => [MapLayerProviderInterface::TAG, 'uhifadhi.map.layer'];
        yield 'pulse' => [PulseProviderInterface::TAG, 'uhifadhi.overview.pulse'];
        yield 'copy' => [OverviewCopyProviderInterface::TAG, 'uhifadhi.overview.copy'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('tags')]
    public function testTheTagIsTheOneTheFleetAlreadyWrites(string $constant, string $literal): void
    {
        self::assertSame($literal, $constant);
    }

    /**
     * EVERY CONTRIBUTION IS ASKED PER MODULE. A provider names the module it
     * speaks for, and the area page asks it only where that module is switched on —
     * which is what makes an uninstalled module's widgets DISAPPEAR rather than
     * go blank.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providers')]
    public function testEveryProviderNamesTheModuleItSpeaksFor(string $interface): void
    {
        self::assertTrue(
            method_exists($interface, 'moduleSlug'),
            $interface.' must say which module its contribution belongs to.',
        );
    }

    /** @return iterable<string, array{class-string}> */
    public static function providers(): iterable
    {
        yield 'widget provider' => [OverviewContributorInterface::class];
        yield 'now tile' => [NowTileProviderInterface::class];
        yield 'attention' => [AttentionProviderInterface::class];
        yield 'map layer' => [MapLayerProviderInterface::class];
        yield 'pulse' => [PulseProviderInterface::class];
        yield 'copy' => [OverviewCopyProviderInterface::class];
    }
}
