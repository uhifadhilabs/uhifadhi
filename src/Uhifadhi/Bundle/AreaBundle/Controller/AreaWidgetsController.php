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

namespace Uhifadhi\Bundle\AreaBundle\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Uhifadhi\Bundle\AreaBundle\Service\AreaPresetLibrary;
use Uhifadhi\Bundle\AreaBundle\Widget\AreaIndexWidgets;
use Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetEndpoint;
use Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetService;
use Uhifadhi\Contracts\Entity\UserInterface as ModuleUserInterface;

/**
 * THE AREAS-INDEX WIDGET LIBRARY — the "Widget library" button on the register
 * opens here, onto the five whole-page layouts the areas landing ships.
 *
 * A PLAIN CLASS, extending nothing, its collaborators handed in — the
 * reusable-bundle rule, because a bundle installed by other projects must not
 * reach a container through a base class. See config/screens.php.
 *
 * ADOPT-ONLY, AND SELF-CONTAINED. The five layouts are rendered whole and inline,
 * from the same real rows the register reads; previewing one swaps the inline
 * layout on this page, and adopting one makes it the landing. There is no
 * compose-your-own here and nothing links out to the design scratchboard the
 * layouts were graduated from.
 *
 * THE ADOPTION IS A STORED PREFERENCE, not a note in the browser. Applying goes
 * through {@see WidgetEndpoint} against this surface's catalogue, exactly as it
 * does on every other widget surface — which is what lets the register read back
 * what was adopted here. This controller validates nothing itself, mints no token
 * and chooses no status code: it names the catalogue and turns a 204 into a
 * redirect with a sentence, so the plain-form path works with no JavaScript.
 *
 * GATED ON `area.view` — reading which layouts the landing can wear is for anyone
 * who may see the register at all.
 */
final readonly class AreaWidgetsController
{
    public function __construct(
        private Environment $twig,
        private AreaPresetLibrary $library,
        private WidgetService $widgets,
        private WidgetEndpoint $endpoint,
        private UrlGeneratorInterface $urls,
        private TokenStorageInterface $tokens,
    ) {
    }

    /**
     * Mounted with a priority so a future module page under `/areas/{slug}` can
     * never shadow it; `/areas/{uuid}` cannot, since "widgets" is not a UUID.
     */
    #[Route('/areas/widgets', name: 'area_widgets', methods: ['GET'], priority: 1)]
    #[IsGranted('area.view')]
    public function index(): Response
    {
        $catalog = new AreaIndexWidgets()->catalog();

        return new Response($this->twig->render('@Area/area/widgets.html.twig', [
            'presets' => $catalog->builtins(),
            'active' => $this->widgets->activeRef($catalog, $this->signedIn()),
            'csrfToken' => $this->endpoint->csrfToken($catalog),
            ...$this->library->landing(new \DateTimeImmutable()),
        ]));
    }

    /** Adopt one of the five as the landing. */
    #[Route('/areas/widgets/preset/{presetId}', name: 'area_widgets_preset', requirements: ['presetId' => '[a-z0-9_-]+'], methods: ['POST'], priority: 1)]
    #[IsGranted('area.view')]
    public function applyPreset(Request $request, string $presetId): Response
    {
        $catalog = new AreaIndexWidgets()->catalog();
        // A layout the surface does not ship is refused by the endpoint; naming
        // it in the flash is only for the case where it IS shipped.
        $adopted = $catalog->preset($presetId);

        return $this->afterWrite(
            $request,
            $this->endpoint->applyPreset($request, $catalog, $presetId),
            \sprintf('The areas landing now shows “%s”.', null !== $adopted ? $adopted->label : $presetId),
        );
    }

    /** Back to the layout this surface ships with. */
    #[Route('/areas/widgets/reset', name: 'area_widgets_reset', methods: ['POST'], priority: 1)]
    #[IsGranted('area.view')]
    public function reset(Request $request): Response
    {
        $catalog = new AreaIndexWidgets()->catalog();
        $shipped = $catalog->preset($catalog->defaultPresetId());

        return $this->afterWrite(
            $request,
            $this->endpoint->reset($request, $catalog),
            \sprintf('The areas landing is back to “%s”.', null !== $shipped ? $shipped->label : 'the shipped default'),
        );
    }

    /**
     * A refused write is returned as it came, so the reason reaches whoever asked;
     * a successful one says so and goes back to the library, which is what makes
     * the plain-form path work with no JavaScript at all.
     */
    private function afterWrite(Request $request, Response $response, string $flash): Response
    {
        if (Response::HTTP_NO_CONTENT !== $response->getStatusCode()) {
            return $response;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('success', $flash);
        }

        return new RedirectResponse($this->urls->generate('area_widgets'));
    }

    /**
     * The signed-in person as the CONTRACT sees them, which is what the widget
     * framework keeps a layout against. Null is a real answer rather than a
     * guard: the framework hands an anonymous read the catalogue's own layout,
     * which is exactly right for a page nobody is signed in to, and the WRITES
     * ask for a person themselves.
     */
    private function signedIn(): ?ModuleUserInterface
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof ModuleUserInterface ? $user : null;
    }
}
