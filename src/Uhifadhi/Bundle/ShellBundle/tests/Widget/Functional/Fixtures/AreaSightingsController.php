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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Widget\Functional\Fixtures;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;
use Uhifadhi\Bundle\ShellBundle\Tests\Widget\Integration\Fixtures\SightingsSurface;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetCatalog;
use Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetDom;
use Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetEndpoint;
use Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetService;

/**
 * A MODULE'S AREA-SCOPED DASHBOARD AND ITS LIBRARY, STOOD IN FOR.
 *
 * Not a stub: it is an ordinary consumer of the published seam, written exactly
 * as `docs/widget/declaring-a-surface.md` tells a module to write one — a plain
 * class extending nothing, the catalogue handed to the endpoint, the layout
 * resolved for the area in the URL. This bundle ships no surface of its own, so
 * a suite that wants to drive a library through real HTTP has to bring a module
 * with it, and this is the smallest honest one.
 *
 * IT IS AREA-SCOPED, which is the whole reason it exists beside the team
 * screens: the layout a person adopts on a module's dashboard is remembered per
 * AREA, and every write below carries the area off the URL.
 */
final readonly class AreaSightingsController
{
    public function __construct(
        private Environment $twig,
        private UrlGeneratorInterface $router,
        private WidgetService $widgets,
        private WidgetEndpoint $endpoint,
    ) {
    }

    /** The dashboard: exactly the composition the person has adopted here. */
    public function dashboard(string $uuid): Response
    {
        $catalog = self::catalog();

        return new Response($this->twig->render('@Fixture/dashboard.html.twig', [
            'widgets' => $this->widgets->resolve($catalog, $this->endpoint->user(), Uuid::fromString($uuid)),
        ]));
    }

    /** The library page, the one every surface renders through one include. */
    public function library(string $uuid): Response
    {
        $catalog = self::catalog();
        $area = Uuid::fromString($uuid);
        $user = $this->endpoint->user();

        return new Response($this->twig->render('@Fixture/library.html.twig', [
            'catalog' => $catalog,
            'builtins' => $catalog->builtins(),
            'customPresets' => $this->widgets->customPresets($catalog, $user, $area),
            'active' => $this->widgets->activeRef($catalog, $user, $area),
            'widgets' => $this->widgets->resolve($catalog, $user, $area),
            'partial' => '@Fixture/_w_%s.html.twig',
            'widgetContext' => [],
            'urls' => $this->urls($uuid),
            'csrfToken' => $this->endpoint->csrfToken($catalog, $area),
        ]));
    }

    public function save(Request $request, string $uuid): Response
    {
        return $this->endpoint->save($request, self::catalog(), Uuid::fromString($uuid));
    }

    public function reset(Request $request, string $uuid): Response
    {
        return $this->after($this->endpoint->reset($request, self::catalog(), Uuid::fromString($uuid)), $uuid);
    }

    public function applyPreset(Request $request, string $uuid, string $presetId): Response
    {
        return $this->after(
            $this->endpoint->applyPreset($request, self::catalog(), $presetId, Uuid::fromString($uuid)),
            $uuid,
        );
    }

    public function copyPreset(Request $request, string $uuid, string $presetId): Response
    {
        return $this->after(
            $this->endpoint->copyPreset($request, self::catalog(), $presetId, Uuid::fromString($uuid)),
            $uuid,
        );
    }

    public function applyCustomPreset(Request $request, string $uuid, string $presetUuid): Response
    {
        return $this->after(
            $this->endpoint->applyCustomPreset($request, self::catalog(), Uuid::fromString($presetUuid), Uuid::fromString($uuid)),
            $uuid,
        );
    }

    public function renameCustomPreset(Request $request, string $uuid, string $presetUuid): Response
    {
        return $this->after(
            $this->endpoint->renameCustomPreset($request, self::catalog(), Uuid::fromString($presetUuid), Uuid::fromString($uuid)),
            $uuid,
        );
    }

    public function deleteCustomPreset(Request $request, string $uuid, string $presetUuid): Response
    {
        return $this->after(
            $this->endpoint->deleteCustomPreset($request, self::catalog(), Uuid::fromString($presetUuid), Uuid::fromString($uuid)),
            $uuid,
        );
    }

    /**
     * The library's action URLs, with the placeholders the browser substitutes:
     * `__ID__` for a design's id and a valid uuid for a saved preset's, because
     * the routes constrain that parameter and a router refuses to generate a URL
     * from a value the requirement rejects.
     *
     * @return array<string, string>
     */
    private function urls(string $uuid): array
    {
        $area = ['uuid' => $uuid];

        return [
            'save' => $this->router->generate('sightings_widgets_save', $area),
            'reset' => $this->router->generate('sightings_widgets_reset', $area),
            'preset' => $this->router->generate('sightings_widgets_preset', $area + ['presetId' => WidgetDom::ID_PLACEHOLDER]),
            'copy' => $this->router->generate('sightings_widgets_preset_copy', $area + ['presetId' => WidgetDom::ID_PLACEHOLDER]),
            'presets' => $this->router->generate('sightings_widgets_presets', $area),
            'apply' => str_replace(
                self::PLACEHOLDER_UUID,
                WidgetDom::ID_PLACEHOLDER,
                $this->router->generate('sightings_widgets_preset_apply', $area + ['presetUuid' => self::PLACEHOLDER_UUID]),
            ),
            'rename' => str_replace(
                self::PLACEHOLDER_UUID,
                WidgetDom::ID_PLACEHOLDER,
                $this->router->generate('sightings_widgets_preset_rename', $area + ['presetUuid' => self::PLACEHOLDER_UUID]),
            ),
            'delete' => str_replace(
                self::PLACEHOLDER_UUID,
                WidgetDom::ID_PLACEHOLDER,
                $this->router->generate('sightings_widgets_preset_delete', $area + ['presetUuid' => self::PLACEHOLDER_UUID]),
            ),
            'dashboard' => $this->router->generate('sightings_dashboard', $area),
        ];
    }

    /** A valid uuid that stands in for one, so the router will generate the URL. */
    private const string PLACEHOLDER_UUID = '00000000-0000-4000-8000-000000000000';

    /** Every write answers 204; the browser reloads, and so does the plain form. */
    private function after(Response $written, string $uuid): Response
    {
        if (Response::HTTP_NO_CONTENT !== $written->getStatusCode()) {
            return $written;
        }

        return new Response('', Response::HTTP_FOUND, [
            'Location' => $this->router->generate('sightings_widgets', ['uuid' => $uuid]),
        ]);
    }

    private static function catalog(): WidgetCatalog
    {
        return new SightingsSurface()->catalog();
    }
}
