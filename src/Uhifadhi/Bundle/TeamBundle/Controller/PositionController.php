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
use Uhifadhi\Bundle\TeamBundle\Entity\Department;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Exception\NameNotUniqueException;
use Uhifadhi\Bundle\TeamBundle\Exception\UnknownPermissionException;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\PositionRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;
use Uhifadhi\Bundle\TeamBundle\Security\AreaAuthority;
use Uhifadhi\Bundle\TeamBundle\Service\PermissionCatalogue;
use Uhifadhi\Bundle\TeamBundle\Service\DepartmentMembership;
use Uhifadhi\Bundle\TeamBundle\Service\PositionService;
use Uhifadhi\Bundle\TeamBundle\Widget\PositionWidgets;
use Uhifadhi\Contracts\Entity\UserInterface as ModuleUserInterface;

/**
 * POSITIONS AND PERMISSIONS — the heart of this bundle.
 *
 * A POSITION IS THE ONLY THING THAT GRANTS A STAFF MEMBER ANY CAPABILITY AT
 * ALL, and this is where one is composed. It belongs to a department and its
 * name is unique only inside it, so nothing on this page ever writes a bare
 * name: *Ecology / Analyst* and *Protection Service / Analyst* are two
 * different jobs that share a word.
 *
 * ADMINISTERING THE TEAM IS ITSELF ONE OF THE ROWS. `team.manage`, the seventh
 * core case — which is why the page that grants it is gated on it.
 *
 * THE LIST OF PERMISSIONS IS NOT FIXED, and every direction has to make that
 * visible. Seven are this bundle's and will always be there; the rest arrive
 * when a bundle is installed and leave when it is removed. So the page hands
 * every rendering the same three honest states: an installed module that
 * declares nothing, a value whose module is not installed here, and an ORPHANED
 * GRANT — still held by a position, provided by nothing, drawn muted. The
 * difference between "you no longer have this" and "you cannot see that you
 * still have this" is the whole of the prune-not-purge ruling.
 */
final readonly class PositionController
{
    /** The section's third tab: the matrix a permission set is edited on. */
    public const string REGISTER = 'team_positions';

    public const string CSRF_ID = 'team_position';

    public function __construct(
        private Environment $twig,
        private PositionRepository $positions,
        private DepartmentRepository $departments,
        private DepartmentMembership $membership,
        private UserRepository $users,
        private PermissionCatalogue $catalogue,
        private PositionService $positionWrites,
        private CsrfTokenManagerInterface $csrf,
        private UrlGeneratorInterface $router,
        private TokenStorageInterface $tokens,
        private WidgetService $widgets,
        private AreaAuthority $authority,
    ) {
    }

    #[Route('/team/positions', name: self::REGISTER, defaults: TeamController::SURFACE, methods: ['GET'])]
    #[IsGranted(PermissionEnum::TeamManage->value)]
    public function index(Request $request): Response
    {
        $catalog = new PositionWidgets()->catalog();

        return new Response($this->twig->render('@Team/positions/index.html.twig', [
            'widgets' => $this->widgets->resolve($catalog, $this->signedIn()),
            ...$this->widgetContext($request),
        ]));
    }

    /**
     * CREATING ONE IS TWO FIELDS, AND THE DEPARTMENT IS THE FIRST OF THEM. The
     * name is unique inside that department and nowhere else.
     */
    #[Route('/team/positions', name: 'team_position_create', methods: ['POST'])]
    #[IsGranted(PermissionEnum::TeamManage->value)]
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
     * THE MATRIX SAVE. Every ticked box, as a value string, through the
     * position's one validated write path.
     *
     * THE FORM POSTS ONLY WHAT IS TICKED, so what is absent is what was
     * revoked — except for the orphans, which are posted back as hidden fields
     * by the template precisely so that a save that does not touch them keeps
     * them. Editing a position is not a migration.
     */
    #[Route('/team/positions/{uuid}/permissions', name: 'team_position_permissions', requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(PermissionEnum::TeamManage->value)]
    public function permissions(Request $request, string $uuid): Response
    {
        $position = $this->position($uuid);
        $this->assertCsrf($request);

        /** @var list<string> $granted */
        $granted = array_values(array_filter(
            array_map(
                static fn (mixed $v): string => \is_string($v) ? $v : '',
                (array) $request->request->all('permissions'),
            ),
            static fn (string $v): bool => '' !== $v,
        ));

        // §5.6(c): a bounded (area-X) administrator may grant only what their own
        // position holds, and never team.manage — anything wider is escalation.
        $granted = $this->withinGrantAuthority($position, $granted);

        try {
            $this->positionWrites->setPermissions($position, $granted);
        } catch (UnknownPermissionException $refusal) {
            return $this->back($request, $refusal->getMessage(), 'error', $position);
        }

        $reaches = $this->users->countActiveHoldingAnyPosition([$position]);

        return $this->back($request, \sprintf(
            '“%s” now holds %d permission%s, and the change reaches %d %s.',
            (string) $position->getName(),
            \count($granted),
            1 === \count($granted) ? '' : 's',
            $reaches,
            1 === $reaches ? 'person' : 'people',
        ), 'success', $position);
    }

    #[Route('/team/positions/{uuid}/rename', name: 'team_position_rename', requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(PermissionEnum::TeamManage->value)]
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

        $departments = $this->departments->findAllOrdered();
        $byDepartment = [];
        foreach ($departments as $department) {
            $byDepartment[$department->getUuidString() ?? ''] = $this->membership->positionsIn($department);
        }

        return [
            'catalogue' => $this->catalogue->all(),
            'grouped' => $this->catalogue->groupedByUmbrella(),
            'silentModules' => $this->catalogue->silentModules(),
            'moduleNames' => $this->catalogue->moduleNames(),
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
            // unbounded (everything grantable), a list for a bounded (area-X) one.
            // The matrix draws the rows outside it disabled, with the guard note.
            'grantable' => $this->authority->grantablePermissions(),
            'people' => $this->users->findAllByName(),
            'soleSuperAdmin' => 1 === $this->users->countActiveSuperAdmins(),
            'csrfToken' => $this->csrf->getToken(self::CSRF_ID)->getValue(),
        ];
    }

    /**
     * GRANTS NO INSTALLED MODULE PROVIDES ANY MORE, and which positions still
     * hold them.
     *
     * They stay in the JSON and stop resolving — pruned, not purged — because
     * removing them on the module's way out would silently rewrite what an
     * administrator granted. Drawing them muted is how somebody finds out.
     *
     * @param list<Position> $positions
     *
     * @return array<string, list<Position>> value => the positions still holding it
     */
    private function orphans(array $positions): array
    {
        $known = $this->catalogue->values();
        $orphans = [];

        foreach ($positions as $position) {
            foreach ($position->getPermissionValues() as $value) {
                if (!\in_array($value, $known, true)) {
                    $orphans[$value][] = $position;
                }
            }
        }

        return $orphans;
    }

    /**
     * REFUSE A GRANT PAST THE ADMINISTRATOR'S OWN AUTHORITY (§5.6(c)), and freeze
     * what is beyond it. An unbounded administrator (a tier or org-level holder)
     * grants exactly what was posted. A bounded (area-X) one may grant only the
     * permissions their own position holds, never team.manage:
     *
     *   · a posted value that is neither grantable NOR already on the position is
     *     a crafted grant past the boundary — refused with a 403, defence in depth
     *     behind the disabled control the matrix draws;
     *   · a permission the position already held beyond the administrator's reach
     *     is FROZEN to what it was, so a bounded save can neither strip it (an
     *     unrelated edit must not silently revoke it) nor is it a way around the
     *     fence.
     *
     * @param list<string> $granted
     *
     * @return list<string> the values to persist
     */
    private function withinGrantAuthority(Position $position, array $granted): array
    {
        $grantable = $this->authority->grantablePermissions();
        if (null === $grantable) {
            return $granted;
        }

        $existing = $position->getPermissionValues();

        foreach ($granted as $value) {
            if (!\in_array($value, $grantable, true) && !\in_array($value, $existing, true)) {
                throw new AccessDeniedException('An area administrator may grant only the permissions their own position holds.');
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
