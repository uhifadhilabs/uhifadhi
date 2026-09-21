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
namespace Uhifadhi\Bundle\TeamBundle\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * THE MATRIX WIDGET LIBRARY, RETIRED — every route answers a redirect to the
 * positions register, for one release.
 *
 * WHY IT IS GONE. The register was a widget canvas: thirteen widgets and
 * seven presets, five of which were five renderings of one matrix and five
 * more of which made the department the structure of the page. The ruled
 * register is one collapsible card per position, and the matrix itself lives
 * once, on the position record. A surface whose whole content has been
 * replaced has nothing left to arrange.
 *
 * WHY IT IS A REDIRECT AND NOT A DELETION. Deleting a shipped route 404s
 * every bookmark and every link an installation wrote against it, in the same
 * release that changed the page. So the names stay for one release and go to
 * the page that replaced them; {@see \Uhifadhi\Bundle\TeamBundle\Widget\PositionWidgets},
 * its seven presets and the `positions/_w_*` partials are deleted in the next
 * one.
 *
 * @deprecated since 1.0, to be removed in 1.1. The positions register is
 *             `team_positions`; what a position grants is edited on
 *             `team_position_configure`.
 */
final readonly class PositionWidgetsController
{
    public function __construct(private UrlGeneratorInterface $router)
    {
    }

    #[Route('/team/positions/widgets', name: 'team_position_widgets', methods: ['GET'])]
    #[IsGranted(PositionController::READ)]
    public function library(): Response
    {
        return $this->register();
    }

    #[Route('/team/positions/widgets/save', name: 'team_position_widgets_save', methods: ['POST'])]
    #[IsGranted(PositionController::READ)]
    public function save(): Response
    {
        return $this->register();
    }

    #[Route('/team/positions/widgets/reset', name: 'team_position_widgets_reset', methods: ['POST'])]
    #[IsGranted(PositionController::READ)]
    public function reset(): Response
    {
        return $this->register();
    }

    #[Route('/team/positions/widgets/preset/{presetId}', name: 'team_position_widgets_preset', requirements: ['presetId' => '[a-z0-9_-]+'], methods: ['POST'])]
    #[IsGranted(PositionController::READ)]
    public function applyPreset(): Response
    {
        return $this->register();
    }

    #[Route('/team/positions/widgets/preset/{presetId}/copy', name: 'team_position_widgets_preset_copy', requirements: ['presetId' => '[a-z0-9_-]+'], methods: ['POST'], priority: 1)]
    #[IsGranted(PositionController::READ)]
    public function copyPreset(): Response
    {
        return $this->register();
    }

    #[Route('/team/positions/widgets/presets', name: 'team_position_widgets_preset_create', methods: ['POST'])]
    #[IsGranted(PositionController::READ)]
    public function createPreset(): Response
    {
        return $this->register();
    }

    #[Route('/team/positions/widgets/presets/{presetUuid}/apply', name: 'team_position_widgets_preset_apply', requirements: ['presetUuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(PositionController::READ)]
    public function applyCustomPreset(): Response
    {
        return $this->register();
    }

    #[Route('/team/positions/widgets/presets/{presetUuid}/rename', name: 'team_position_widgets_preset_rename', requirements: ['presetUuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(PositionController::READ)]
    public function renameCustomPreset(): Response
    {
        return $this->register();
    }

    #[Route('/team/positions/widgets/presets/{presetUuid}/delete', name: 'team_position_widgets_preset_delete', requirements: ['presetUuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(PositionController::READ)]
    public function deleteCustomPreset(): Response
    {
        return $this->register();
    }

    /**
     * 302 rather than 301: a permanent redirect is cached by the browser, and
     * this one is withdrawn next release rather than kept forever.
     */
    private function register(): RedirectResponse
    {
        return new RedirectResponse($this->router->generate('team_positions'));
    }
}
