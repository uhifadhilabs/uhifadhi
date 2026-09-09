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
    public const string CSRF_ID = 'team_position';

    public function __construct(
        private Environment $twig,
        private PositionRepository $positions,
        private DepartmentRepository $departments,
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

    #[Route('/team/positions', name: 'team_positions', methods: ['GET'])]
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

        $department = $this->department(trim((string) $request->request->get('department')));

        // §5.6(b): a bounded (area-X) administrator may create a position only
        // under a department their authority reaches — an area-level one in their
        // own area. Filing under an org-level department, another area's, or none
        // at all (a loose position) files work past their boundary. A tier or
        // org-level holder is unbounded and passes.
        $this->assertMayFile($department);

        try {
            $position = $this->positionWrites->create($name, $department);
        } catch (NameNotUniqueException) {
            // The index would have said this in SQL. The person who typed the
            // name wants the sentence — and the sentence has to name the
            // DEPARTMENT, because the same word in another one is fine.
            return $this->back($request, \sprintf(
                '%s already has a position called “%s”. A name is unique inside its department and nowhere else, so the same word in another department is fine.',
                $department?->getName() ?? 'The unassigned group',
                $name,
            ), 'error');
        }

        return $this->back($request, \sprintf('“%s” exists. It grants nothing until you tick something.', $position->getQualifiedName()));
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
            $position->getQualifiedName(),
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

        // §5.6(b): a bounded administrator may rename a position only where they
        // could have created it — under a department their authority reaches.
        $this->assertMayFile($position->getDepartment());

        $name = trim((string) $request->request->get('name'));
        if ('' === $name) {
            return $this->back($request, 'A position needs a name.', 'error', $position);
        }

        try {
            $this->positionWrites->rename($position, $name);
        } catch (NameNotUniqueException) {
            return $this->back($request, \sprintf('That department already has a position called “%s”.', $name), 'error');
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

        return [
            'catalogue' => $this->catalogue->all(),
            'grouped' => $this->catalogue->groupedByUmbrella(),
            'silentModules' => $this->catalogue->silentModules(),
            'moduleNames' => $this->catalogue->moduleNames(),
            'positions' => $positions,
            'groupedPositions' => $this->positions->findAllGroupedByDepartment(),
            'departments' => $this->departments->findAllOrdered(),
            // §5.6(b): what the create picker may file INTO — every department for
            // an unbounded administrator, only the area-level ones in their own
            // area for a bounded (area-X) one. The loose "no department" option is
            // a filing with no scope, so a bounded administrator is not offered it.
            'creatable' => $this->creatableDepartments(),
            'canFileLoose' => $this->authority->reachesDepartment(null),
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

    /**
     * THE DEPARTMENTS THE CREATE PICKER MAY FILE INTO (§5.6(b)) — every department
     * for an unbounded administrator, only the area-level ones the bounded (area-X)
     * administrator's authority reaches. The org-level and other-area departments
     * the whole org chart still draws (the widgets read `departments`) drop out of
     * the picker, so the form offers only real targets.
     *
     * @return list<Department>
     */
    private function creatableDepartments(): array
    {
        return array_values(array_filter(
            $this->departments->findAllOrdered(),
            fn (Department $department): bool => $this->authority->reachesDepartment($department),
        ));
    }

    /**
     * REFUSE A POSITION FILED PAST THE ADMINISTRATOR'S BOUNDARY (§5.6(b)). A tier
     * or org-level administrator is unbounded and may file anywhere; a bounded
     * (area-X) one may create or rename a position only under an area-level
     * department in their own area — never an org-level one, another area's, or no
     * department at all. {@see AreaAuthority::reachesDepartment()} computes the
     * boundary; this is the 403 behind the picker the create form already narrows.
     */
    private function assertMayFile(?Department $department): void
    {
        if (!$this->authority->reachesDepartment($department)) {
            throw new AccessDeniedException('An area administrator may create or rename positions only under an area-level department in their own area.');
        }
    }

    private function department(string $uuid): ?Department
    {
        if ('' === $uuid || !Uuid::isValid($uuid)) {
            // NULLABLE, AND THE NULL IS A STATE. A position created before
            // anybody decided which department owns it is a position that
            // exists, and its holders show in the roster's Unassigned band.
            return null;
        }

        return $this->departments->findOneByUuid(Uuid::fromString($uuid));
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
