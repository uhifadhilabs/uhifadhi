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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web;

use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Overview\OverviewContributorInterface;
use Uhifadhi\Bundle\AreaBundle\Overview\OverviewStylesheetsInterface;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\Widget;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetGroup;

/**
 * A MODULE PUTTING A CARD ON AN AREA'S OVERVIEW, playing by the published
 * contract and by nothing else.
 *
 * IT READS `by.<slug>`, WHICH IS THE WHOLE POINT OF THE FIXTURE. The
 * contract says a contributed partial is rendered with `with_context:
 * false` and everything it needs in ONE map, its own figures under its own
 * slug. A host that merged those figures flat into the page's context would
 * render its own cells perfectly and every module's cell would fatal — which
 * is exactly what shipped, because the only area the suite rendered had no
 * modules switched on. So the stand-in reads the contract's shape, and the
 * suite renders an area that runs it.
 */
final readonly class FakeOverviewWidgets implements OverviewContributorInterface, OverviewStylesheetsInterface
{
    public function __construct(private string $slug)
    {
    }

    public function moduleSlug(): string
    {
        return $this->slug;
    }

    public function group(): WidgetGroup
    {
        return new WidgetGroup($this->slug, ucfirst($this->slug), 'What the stand-in module puts on this page.');
    }

    /** One card, named after the module, so two stand-ins never collide. */
    public function widgets(): array
    {
        return [new Widget($this->slug.'_now', 'Out right now', $this->slug, 6, [12, 6], true, 'The stand-in module’s own card.')];
    }

    public function partialPattern(): string
    {
        return '@fixtures/overview/_w_%s.html.twig';
    }

    /**
     * THE SHEET THIS MODULE'S CELLS ARE WRITTEN AGAINST. A real module names
     * its own bundle's served path; the stand-in names one that exists, so
     * the suite can see the link the surface writes.
     */
    public function stylesheets(): array
    {
        return ['bundles/'.$this->slug.'/'.$this->slug.'.css'];
    }

    public function context(AreaOfInterest $area, \DateTimeImmutable $now): array
    {
        return ['out' => 3, 'walked' => 96];
    }
}
