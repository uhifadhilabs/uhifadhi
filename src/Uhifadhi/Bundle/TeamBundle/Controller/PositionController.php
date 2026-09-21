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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;
use Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetService;
use Uhifadhi\Bundle\TeamBundle\Access\ConcernCatalogue;
use Uhifadhi\Bundle\TeamBundle\Access\TeamConcerns;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Exception\NameNotUniqueException;
use Uhifadhi\Bundle\TeamBundle\Exception\UnknownGrantException;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\PositionRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;
use Uhifadhi\Bundle\TeamBundle\Security\AreaAuthority;
use Uhifadhi\Bundle\TeamBundle\Service\DepartmentMembership;
use Uhifadhi\Bundle\TeamBundle\Service\PositionService;
use Uhifadhi\Bundle\TeamBundle\Widget\PositionWidgets;
use Uhifadhi\Contracts\Access\Grant;
use Uhifadhi\Contracts\Access\Verb;
use Uhifadhi\Contracts\Entity\UserInterface as ModuleUserInterface;

/**
 * POSITIONS AND GRANTS — the heart of this bundle.
 *
 * A POSITION IS THE ONLY THING THAT GRANTS A STAFF MEMBER ANY CAPABILITY AT
 * ALL, and this is where one is composed. A position belongs to no
 * department, and its name is unique across the whole organization: there is
 * one Analyst, and where each holder works is written on their own record.
 *
 * A GRANT IS A (CONCERN, VERB) PAIR, AND THE MATRIX IS DRAWN FROM THE
 * DECLARATIONS. It used to read a flat catalogue of permission values grouped
 * under an invented umbrella and write `setPermissionValues()`; the ruled
 * model replaced that with one group per DECLARER, a row per CONCERN and a
 * column per VERB, and a cell drawn only where the concern declares that verb
 * — so there is never a checkbox that would mean nothing, and no list of
 * permissions is maintained by hand in the middle of the product.
 *
 * ADMINISTERING THE TEAM IS ITSELF TWO OF THE CELLS. `positions.configure`
 * and `departments.configure` — which is why the page that confers them is
 * gated on the first of them.
 *
 * WHAT THERE IS TO GRANT IS NOT FIXED, and the page has to make that visible.
 * The team's four concerns are this bundle's and will always be there; the
 * rest arrive when a module is installed and leave when it is removed. So the
 * page hands every rendering the honest state that outlives a module: an
 * ORPHANED GRANT — still held by a position, declared by nothing, drawn muted
 * and still revocable. The difference between "you no longer have this" and
 * "you cannot see that you still have this" is the whole of the
 * prune-not-purge ruling.
 */
final readonly class PositionController
{
    /** The section's third tab: the matrix a position's grant is edited on. */
    public const string REGISTER = 'team_positions';

    /**
     * THE TWO PAIRS THIS SCREEN IS ABOUT, and they are not the same pair.
     * Reading the register is `positions.read`; composing what a position
     * grants is `positions.configure`, which is administering the team.
     */
    public const string READ = TeamConcerns::POSITIONS.'.'.Verb::Read->value;
    public const string CONFIGURE = TeamConcerns::POSITIONS.'.'.Verb::Configure->value;

    public const string CSRF_ID = 'team_position';

    public function __construct(
        private Environment $twig,
        private PositionRepository $positions,
        private DepartmentRepository $departments,
        private DepartmentMembership $membership,
        private UserRepository $users,
        private ConcernCatalogue $catalogue,
        private PositionService $positionWrites,
        private CsrfTokenManagerInterface $csrf,
        private UrlGeneratorInterface $router,
        private TokenStorageInterface $tokens,
        private WidgetService $widgets,
        private AreaAuthority $authority,
    ) {
    }

    #[Route('/team/positions', name: self::REGISTER, defaults: TeamController::SURFACE, methods: ['GET'])]
    #[IsGranted(self::READ)]
    public function index(Request $request): Response
    {
        $catalog = new PositionWidgets()->catalog();

        return new Response($this->twig->render('@Team/positions/index.html.twig', [
            'widgets' => $this->widgets->resolve($catalog, $this->signedIn()),
            ...$this->widgetContext($request),
        ]));
    }

    /**
     * CREATING ONE IS A NAME, and the name is the whole of it. A position
     * belongs to no department, so there is nothing to file it under and the
     * name is unique across the whole organization: there is one Sergeant,
     * not one per department.
     */
    #[Route('/team/positions', name: 'team_position_create', methods: ['POST'])]
    #[IsGranted(self::CONFIGURE)]
    public function create(Request $request): Response
    {
        $this->assertCsrf($request);

        $name = trim((string) $request->request->get('name'));
        if ('' === $name) {
            return $this->back($request, 'A position needs a name.', 'error');
        }

        try {
            $position = $this->positionWrites->create($name);
        } catch (NameNotUniqueException) {
            // The index would have said this in SQL. The person who typed the
            // name wants the sentence — and the sentence says ORGANIZATION,
            // because a position belongs to no department and there is one of
            // each name.
            return $this->back($request, \sprintf(
                'This organization already has a position called “%s”. A position belongs to no department, so its name is unique across the whole organization — rename one of them.',
                $name,
            ), 'error');
        }

        return $this->back($request, \sprintf('“%s” exists. It grants nothing until you tick something.', (string) $position->getName()));
    }

    /**
     * THE MATRIX SAVE. Every ticked cell, as a `<concern>.<verb>` pair,
     * through the position's one validated write path.
     *
     * THE FORM POSTS ONLY WHAT IS TICKED, so what is absent is what was
     * revoked — except for the orphans, which the template draws as ticked
     * boxes of their own precisely so that a save that does not touch them
     * keeps them. Editing a position is not a migration.
     *
     * THE FIELD IS `grants[]`. It was `permissions[]`, posting flat values;
     * the ruling made a grant a pair, and renaming the field is what stops an
     * old form — or an old test — from quietly writing the wrong thing.
     */
    #[Route('/team/positions/{uuid}/permissions', name: 'team_position_permissions', requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(self::CONFIGURE)]
    public function permissions(Request $request, string $uuid): Response
    {
        $position = $this->position($uuid);
        $this->assertCsrf($request);

        /** @var list<string> $granted */
        $granted = array_values(array_filter(
            array_map(
                static fn (mixed $v): string => \is_string($v) ? $v : '',
                (array) $request->request->all('grants'),
            ),
            static fn (string $v): bool => '' !== $v,
        ));

        // §5.6(c): a bounded (area-X) administrator may grant only what their
        // own position holds, and never team administration — anything wider
        // is escalation.
        $granted = $this->withinGrantAuthority($position, $granted);

        try {
            $this->positionWrites->setGrants($position, $granted);
        } catch (UnknownGrantException $refusal) {
            return $this->back($request, $refusal->getMessage(), 'error', $position);
        }

        $reaches = $this->users->countActiveHoldingAnyPosition([$position]);

        return $this->back($request, \sprintf(
            '“%s” now holds %d grant%s, and the change reaches %d %s.',
            (string) $position->getName(),
            \count($granted),
            1 === \count($granted) ? '' : 's',
            $reaches,
            1 === $reaches ? 'person' : 'people',
        ), 'success', $position);
    }

    #[Route('/team/positions/{uuid}/rename', name: 'team_position_rename', requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(self::CONFIGURE)]
    public function rename(Request $request, string $uuid): Response
    {
        $position = $this->position($uuid);
        $this->assertCsrf($request);

        $name = trim((string) $request->request->get('name'));
        if ('' === $name) {
            return $this->back($request, 'A position needs a name.', 'error', $position);
        }

        try {
            $this->positionWrites->rename($position, $name);
        } catch (NameNotUniqueException) {
            return $this->back($request, \sprintf('This organization already has a position called “%s”.', $name), 'error');
        }

        return $this->back($request, 'Renamed.', 'success', $position);
    }

    /**
     * EVERY FACT ANY OF THE THIRTEEN WIDGETS MIGHT WANT, gathered once — the
     * same context the widget library previews on, so what somebody arranges
     * there is exactly what they get here.
     *
     * @return array<string, mixed>
     */
    public function widgetContext(Request $request): array
    {
        $positions = $this->positions->findAllOrdered();

        // WHICH POSITION THE CHECKLIST IS SHOWING. Direction B edits exactly one
        // at a time and says which; the id is in the URL, so the choice is
        // shareable and survives the save's redirect.
        $selectedUuid = trim((string) $request->query->get('position'));
        $selected = '' !== $selectedUuid && Uuid::isValid($selectedUuid)
            ? $this->positions->findOneByUuid(Uuid::fromString($selectedUuid))
            : null;
        $selected ??= $positions[0] ?? null;

        $holders = [];
        foreach ($positions as $position) {
            $holders[$position->getUuidString() ?? ''] = $this->users->countActiveHoldingAnyPosition([$position]);
        }

        $grouped = $this->catalogue->grouped();
        $groupPairs = [];
        foreach ($grouped as $declarer => $concerns) {
            $groupPairs[$declarer] = [];
            foreach ($concerns as $concern) {
                foreach (Verb::cases() as $verb) {
                    if ($concern->supports($verb)) {
                        $groupPairs[$declarer][] = (string) Grant::of($concern->key(), $verb);
                    }
                }
            }
        }

        $departments = $this->departments->findAllOrdered();
        $byDepartment = [];
        foreach ($departments as $department) {
            $byDepartment[$department->getUuidString() ?? ''] = $this->membership->positionsIn($department);
        }

        return [
            // THE MATRIX'S OWN THREE FACTS, and nothing derived from them in
            // a template: every concern, the same concerns grouped under
            // whoever declared them, and the six verbs in their fixed order.
            // A rendering asks the concern whether it supports a verb, so a
            // cell exists only where the declaration put one.
            'concerns' => $this->catalogue->all(),
            'grouped' => $grouped,
            'verbs' => Verb::cases(),
            'pairs' => $this->catalogue->pairs(),
            // THE PAIRS EACH GROUP OFFERS, counted once here rather than in
            // every rendering: a Twig `set` inside a loop does not survive
            // the loop, so a template that tried to total a group would have
            // to be clever about it, and clever is where they drift apart.
            'groupPairs' => $groupPairs,
            'positions' => $positions,
            'departments' => $departments,
            // A DEPARTMENT'S POSITIONS ARE THE ONES ITS MEMBERS HOLD. A
            // position belongs to nobody, so a widget that reads by
            // department reads the derivation rather than a column that no
            // longer exists.
            'positionsByDepartment' => $byDepartment,
            'selected' => $selected,
            'holders' => $holders,
            'orphans' => $this->orphans($positions),
            // §5.6(c): what the signed-in administrator may confer — null when
            // unbounded (everything grantable), a list of pairs for a bounded
            // (area-X) one. The matrix draws the cells outside it disabled,
            // with the guard note.
            'grantable' => $this->authority->grantableGrants(),
            'people' => $this->users->findAllByName(),
            'soleSuperAdmin' => 1 === $this->users->countActiveSuperAdmins(),
            'csrfToken' => $this->csrf->getToken(self::CSRF_ID)->getValue(),
        ];
    }

    /**
     * PAIRS NOTHING INSTALLED DECLARES ANY MORE, and which positions still
     * hold them.
     *
     * They stay in the JSON and stop resolving — pruned, not purged — because
     * removing them on the module's way out would silently rewrite what an
     * administrator granted. Drawing them muted is how somebody finds out.
     *
     * @param list<Position> $positions
     *
     * @return array<string, list<Position>> pair => the positions still holding it
     */
    private function orphans(array $positions): array
    {
        $declared = $this->catalogue->pairs();
        $orphans = [];

        foreach ($positions as $position) {
            foreach ($position->getGrantValues() as $pair) {
                if (!\in_array($pair, $declared, true)) {
                    $orphans[$pair][] = $position;
                }
            }
        }

        return $orphans;
    }

    /**
     * REFUSE A GRANT PAST THE ADMINISTRATOR'S OWN AUTHORITY (§5.6(c)), and freeze
     * what is beyond it. An unbounded administrator (a tier or org-level holder)
     * grants exactly what was posted. A bounded (area-X) one may grant only the
     * pairs their own position holds, never team administration:
     *
     *   · a posted pair that is neither grantable NOR already on the position is
     *     a crafted grant past the boundary — refused with a 403, defence in depth
     *     behind the disabled control the matrix draws;
     *   · a pair the position already held beyond the administrator's reach
     *     is FROZEN to what it was, so a bounded save can neither strip it (an
     *     unrelated edit must not silently revoke it) nor is it a way around the
     *     fence.
     *
     * @param list<string> $granted
     *
     * @return list<string> the pairs to persist
     */
    private function withinGrantAuthority(Position $position, array $granted): array
    {
        $grantable = $this->authority->grantableGrants();
        if (null === $grantable) {
            return $granted;
        }

        $existing = $position->getGrantValues();

        foreach ($granted as $pair) {
            if (!\in_array($pair, $grantable, true) && !\in_array($pair, $existing, true)) {
                throw new AccessDeniedException('An area administrator may grant only what their own position holds.');
            }
        }

        $frozen = array_values(array_filter($existing, static fn (string $value): bool => !\in_array($value, $grantable, true)));
        $chosen = array_values(array_filter($granted, static fn (string $value): bool => \in_array($value, $grantable, true)));

        return array_values(array_unique([...$chosen, ...$frozen]));
    }

    private function position(string $uuid): Position
    {
        return $this->positions->findOneByUuid(Uuid::fromString($uuid))
            ?? throw new NotFoundHttpException('No such position on this installation.');
    }

    private function signedIn(): ?ModuleUserInterface
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof ModuleUserInterface ? $user : null;
    }

    private function assertCsrf(Request $request): void
    {
        if (!$this->csrf->isTokenValid(new CsrfToken(self::CSRF_ID, (string) $request->request->get('_token')))) {
            throw new NotFoundHttpException('Invalid CSRF token.');
        }
    }

    private function back(Request $request, string $message, string $kind = 'success', ?Position $position = null): RedirectResponse
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($kind, $message);
        }

        // Back to the position that was being edited, so the checklist is still
        // showing what the sentence is about.
        return new RedirectResponse($this->router->generate(
            'team_positions',
            null !== $position ? ['position' => $position->getUuidString()] : [],
        ));
    }
}
