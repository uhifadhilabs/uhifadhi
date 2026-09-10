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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Uhifadhi\Bundle\ShellBundle\Contract\NavigationSourceInterface;
use Uhifadhi\Bundle\ShellBundle\Widget\Registry\WidgetSurfaceInterface;
use Uhifadhi\Bundle\TeamBundle\ArgumentResolver\AreaValueResolver;
use Uhifadhi\Bundle\TeamBundle\Controller\ApiAuthController;
use Uhifadhi\Bundle\TeamBundle\Controller\DepartmentController;
use Uhifadhi\Bundle\TeamBundle\Controller\InviteController;
use Uhifadhi\Bundle\TeamBundle\Controller\MemberController;
use Uhifadhi\Bundle\TeamBundle\Controller\PasswordResetController;
use Uhifadhi\Bundle\TeamBundle\Controller\PositionController;
use Uhifadhi\Bundle\TeamBundle\Controller\PositionWidgetsController;
use Uhifadhi\Bundle\TeamBundle\Controller\SecurityController;
use Uhifadhi\Bundle\TeamBundle\Controller\TeamController;
use Uhifadhi\Bundle\TeamBundle\Controller\TeamWidgetsController;
use Uhifadhi\Bundle\TeamBundle\Devkit\TeamCommandProvider;
use Uhifadhi\Bundle\TeamBundle\Devkit\TeamContentProvider;
use Uhifadhi\Bundle\TeamBundle\EventListener\ApiErrorListener;
use Uhifadhi\Bundle\TeamBundle\Repository\ApiTokenRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\DepartmentScopeChangeRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\PositionRepository;
use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;
use Uhifadhi\Bundle\TeamBundle\Security\ActiveUserChecker;
use Uhifadhi\Bundle\TeamBundle\Security\ApiTokenAuthenticator;
use Uhifadhi\Bundle\TeamBundle\Security\AreaAuthority;
use Uhifadhi\Bundle\TeamBundle\Security\PermissionVoter;
use Uhifadhi\Bundle\TeamBundle\Service\ApiTokenManager;
use Uhifadhi\Bundle\TeamBundle\Service\DepartmentService;
use Uhifadhi\Bundle\TeamBundle\Service\FieldSignIn;
use Uhifadhi\Bundle\TeamBundle\Service\Mail;
use Uhifadhi\Bundle\TeamBundle\Service\PasswordResetService;
use Uhifadhi\Bundle\TeamBundle\Service\PermissionCatalogue;
use Uhifadhi\Bundle\TeamBundle\Service\PositionService;
use Uhifadhi\Bundle\TeamBundle\Service\SuperAdminInvariant;
use Uhifadhi\Bundle\TeamBundle\Service\TeamOverview;
use Uhifadhi\Bundle\TeamBundle\Service\UserService;
use Uhifadhi\Bundle\TeamBundle\Shell\TeamNavigation;
use Uhifadhi\Bundle\TeamBundle\Shell\UserBadgeSource;
use Uhifadhi\Bundle\TeamBundle\Twig\AreaScopeExtension;
use Uhifadhi\Bundle\TeamBundle\Widget\PositionWidgets;
use Uhifadhi\Bundle\TeamBundle\Widget\TeamWidgets;

/*
 * The bundle's static service wiring.
 *
 * PHP (not YAML) on purpose: a reusable bundle must not force symfony/yaml onto
 * an installation, and FQCN references stay refactor-safe and phpstan-checked. Imported by
 * TeamBundle::loadExtension(), which keeps only the config-DRIVEN bits.
 *
 * Everything below is defined EXPLICITLY — no autowire(), no autoconfigure(),
 * and ids prefixed with the bundle alias — because this bundle is installed by
 * other projects via Composer, which is what Symfony calls a reusable bundle:
 *
 *   "Services should not use autowiring or autoconfiguration. Instead, all
 *    services should be defined explicitly."
 *   "If the bundle defines services, they must be prefixed with the bundle alias."
 *   — https://symfony.com/doc/current/bundles/best_practices.html
 *
 * The ids are the published surface. They are private, as a reusable bundle's
 * should be; anything that wants one aliases it.
 *
 *   team.api_token.manager      the credential a field client carries: issue, find, note, withdraw
 *   team.api_token.authenticator  the bearer token a field client presents, and the 401 for none
 *   team.api_error_listener     one failure document for everything under /api
 *   team.field_sign_in          identifier + passcode -> the person, or nobody
 *   team.controller.api_auth    where a field client signs in
 *   team.permissions            the catalogue: this bundle's seven + what modules declared
 *   team.permission_voter       who holds which of them
 *   team.super_admin_invariant  the refusal that keeps one active Super Admin
 *   team.accounts               every way an account comes into being or changes
 *   team.positions              what a position is, and what it grants
 *   team.departments            the org chart's shape, and its audited scope changes
 *   team.password_reset         a recovery link issued, spent, or an invitation accepted
 *   team.user_checker           the sign-in refusal for a deactivated account
 *   team.overview               the roster's counts and its attention rows
 *   team.widget_surface.*       the roster and the matrix, as dashboard surfaces
 *   team.devkit.commands        what devkit materialises into commands in a dev install
 *   team.devkit.content         the demo organisation devkit seeds in a dev install
 *   team.controller.security    the sign-in screen
 *   team.controller.team        the roster
 *   team.controller.team_widgets  its widget library
 *   team.controller.member      one person's record
 *   team.controller.position    the permission matrix
 *   team.controller.position_widgets  its widget library
 *   team.controller.invite      both ways of adding somebody
 *   team.controller.reset       forgot / reset / accept, on the document rung
 *   team.mail                   the two letters, and whether they can be sent
 *   team.navigation             the Team row in the shell's sidebar, where there is a shell
 *   team.user_badge_source      who the shell's top bar names — aliased to shell.user_badge_source
 *
 * Controllers extend nothing and take their collaborators explicitly, patterned
 * on FrameworkBundle's own TemplateController (see
 * vendor/symfony/framework-bundle/Controller/TemplateController.php).
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    /*
     * Repositories keep FQCN ids — the one place the bundle-alias prefix cannot
     * be used: ServiceRepositoryCompilerPass keys its locator by SERVICE ID over
     * findTaggedServiceIds(), while ContainerRepositoryFactory looks a repository
     * up by CLASS NAME; tagged-id lookup never sees aliases.
     *
     * @see vendor/doctrine/doctrine-bundle/src/DependencyInjection/Compiler/ServiceRepositoryCompilerPass.php
     */
    $services->set(UserRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    $services->set(PositionRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    $services->set(DepartmentRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    $services->set(DepartmentScopeChangeRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    $services->set(ApiTokenRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    /*
     * THE CREDENTIAL A FIELD CLIENT CARRIES. It lives here because a token is a
     * credential OF A PERSON, kept, rotated and withdrawn beside the account it
     * belongs to — exactly as a password is.
     *
     * THE AUTHENTICATOR IS ITS NEIGHBOUR, not a stranger reaching through an
     * interface: a credential and the thing that reads it belong in one bundle,
     * so the store is injected directly.
     */
    $services->set('team.api_token.manager', ApiTokenManager::class)
        ->args([service(ApiTokenRepository::class), service('doctrine.orm.entity_manager')]);

    /*
     * THE FIRST ADMINISTRATOR, OFFERED RATHER THAN SHIPPED. The core ships no
     * console command; devkit — dev-only, installed through require-dev — is
     * what turns this inert declaration into one. In a production build devkit
     * is absent, nothing collects this service, and it is never asked anything.
     *
     * THE TAG IS A LITERAL STRING, not a constant of devkit's. Reading
     * UhifadhiDevkitBundle::COMMAND_PROVIDER_TAG would load a class that is not
     * installed in production, which is the whole arrangement inverted: the
     * always-installed side names the promise, never the tool.
     */
    $services->set('team.devkit.commands', TeamCommandProvider::class)
        ->args([service('team.accounts')])
        ->tag('uhifadhi.devkit.command_provider');

    /*
     * A SMALL ORGANISATION TO LOOK AT, offered the same way and collected by
     * the same absent tool. It writes through this bundle's own services, so
     * the content it leaves is content somebody could have built by clicking.
     */
    $services->set('team.devkit.content', TeamContentProvider::class)
        ->args([
            service('team.accounts'),
            service('team.positions'),
            service('team.departments'),
            service(UserRepository::class),
        ])
        ->tag('uhifadhi.devkit.content_provider');

    /*
     * ONE FAILURE DOCUMENT FOR EVERYTHING UNDER `/api`, whoever refused.
     *
     * Two listeners on one object. The exception pass answers a thrown
     * ApiProblemException verbatim and stops there, at a priority above the
     * firewall's exception listener and the framework's own, so nothing
     * downstream reshapes a code a client switches on. The response pass is the
     * safety net for every other failure — the firewall's 401, routing's 404, a
     * 500 from anywhere — and runs last, once the status is settled, replacing
     * the body and never the status.
     *
     * TAGGED BY HAND, twice: a reusable bundle is not autoconfigured, so the
     * #[AsEventListener] attribute would never be read and the document would
     * silently be whatever each layer felt like.
     */
    $services->set('team.api_error_listener', ApiErrorListener::class)
        ->tag('kernel.event_listener', ['event' => 'kernel.exception', 'method' => 'onException', 'priority' => 512])
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onResponse', 'priority' => -1024]);

    /*
     * THE BEARER TOKEN A FIELD CLIENT PRESENTS — the machine door's
     * authenticator and its entry point in one class, so a request with NO
     * token is answered 401 rather than the 403 an access listener would give.
     *
     * PUBLIC, AND ALIASED FROM THE CLASS NAME, because the thing that names it
     * is the installation's own security file: a firewall's
     * `custom_authenticators` and `entry_point` are written as class names, and
     * a bundle's services are private by default.
     *
     * nullOnInvalid() KEEPS THE DENY-BY-DEFAULT READING. Where nothing in the
     * container keeps API tokens the store is null, the authenticator claims
     * nothing, and every request falls to the entry point and 401 — which is
     * the safe reading of "nobody can say who this is".
     */
    $services->set('team.api_token.authenticator', ApiTokenAuthenticator::class)
        ->args([service('team.api_token.manager')->nullOnInvalid()]);
    $services->alias(ApiTokenAuthenticator::class, 'team.api_token.authenticator')->public();

    /*
     * WHETHER AN IDENTIFIER AND A PASSCODE NAME SOMEBODY WHO MAY SIGN IN — the
     * three refusals a firewall would make separately, made together, because
     * the field door is reached before any firewall.
     */
    $services->set('team.field_sign_in', FieldSignIn::class)
        ->args([service(UserRepository::class), service('security.user_password_hasher')]);

    /*
     * WHERE A FIELD CLIENT SIGNS IN. The one endpoint that answers without a
     * token, so the installation's security file leaves its path unguarded and
     * the endpoint checks the credentials itself.
     */
    $services->set('team.controller.api_auth', ApiAuthController::class)
        ->args([
            service('team.field_sign_in'),
            service('team.api_token.manager'),
            service('team.permissions'),
            // The two budgets the endpoint spends before it weighs a
            // credential. The ids are the framework's own naming of the
            // limiters this bundle prepends — see TeamBundle::prependExtension.
            service('limiter.team_token_id'),
            service('limiter.team_token_ip'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(ApiAuthController::class, 'team.controller.api_auth')->public();

    /*
     * THE CATALOGUE, reading the module providers LIVE from the container in
     * registration order — which is what makes uninstalling a bundle take its
     * declared permissions with it on the next request rather than the next
     * deploy. The tag string is the registry's, written out here rather than
     * imported: this bundle must work in an installation that has no registry (a
     * deployment with no modules still has people), and a class constant would
     * have made RegistryBundle a hard dependency of signing in.
     */
    $services->set('team.permissions', PermissionCatalogue::class)
        ->args([tagged_iterator('uhifadhi.module')]);

    /*
     * The voter, tagged by hand. A reusable bundle is not autoconfigured, and a
     * voter that missed this tag would deny nothing and grant nothing — it would
     * simply never be asked, which looks exactly like a permission model that
     * does not work.
     */
    $services->set('team.permission_voter', PermissionVoter::class)
        ->args([service('team.permissions')])
        ->tag('security.voter');

    /*
     * THE CONVENIENCE ARGUMENT RESOLVER — turns a `{uuid}` route param into the
     * platform's area so a controller can pass it to the voter
     * (isGranted('patrols.record', $area)). Tagged by hand like everything here;
     * a reusable bundle is not autoconfigured. Priority above the default
     * resolvers so an AreaInterface-typed argument is filled from the route
     * before a generic resolver tries and fails.
     */
    $services->set('team.area_value_resolver', AreaValueResolver::class)
        ->args([service('doctrine.orm.entity_manager')])
        ->tag('controller.argument_value_resolver', ['priority' => 150]);

    /*
     * THE READ SIDE OF AREA-SCOPED team.manage — whether the signed-in
     * administrator is unbounded (a tier or org-level holder) or confined to one
     * area. The department controller refuses the escalation acts (minting an org
     * department, changing scope, reaching another area) by asking this.
     */
    $services->set('team.area_authority', AreaAuthority::class)
        ->args([service('security.token_storage')]);

    /*
     * THE "SCOPED TO <AREA>" BANNER'S ONE INPUT — the area a bounded
     * administrator is confined to, exposed to Twig so the shared partial can be
     * fed from one place rather than every management controller threading the
     * same value. Tagged by hand: a reusable bundle is not autoconfigured, and an
     * untagged Twig extension is one Twig never loads.
     */
    $services->set('team.twig.area_scope', AreaScopeExtension::class)
        ->args([service('team.area_authority')])
        ->tag('twig.extension');

    /*
     * THE ROW IN THE SIDEBAR — the half of "a module registers with the registry and
     * renders in the shell" that is rendering, and the one thing a module can
     * only do by hand.
     *
     * REGISTERED ONLY WHERE THERE IS A SHELL. ShellBundle is a
     * suggestion of this bundle rather than a requirement, and a service whose
     * class implements an interface nobody installed is a container that will
     * not compile. The guard costs nothing — neither ::class constant loads a
     * class — and it is what keeps the shell soft.
     *
     * THE TAG STRING IS WRITTEN OUT, exactly as the registry's is a few lines above,
     * and for the same reason: reading ShellBundle::NAV_TAG would load
     * the shell's bundle class, and this file has to be readable in an
     * installation that has no shell at all.
     */
    if (interface_exists(NavigationSourceInterface::class)) {
        $services->set('team.navigation', TeamNavigation::class)
            ->args([
                service('router'),
                service('security.token_storage'),
                service('security.authorization_checker'),
                service('request_stack'),
            ])
            ->tag('shell.nav_section');
    }

    /*
     * WHO THE TOP BAR NAMES — team's answer to the shell's user-badge contract.
     *
     * Team is the core bundle that owns the account, the position and the tier,
     * so it is the bundle that fills the card the shell draws. The interface lives
     * in the contracts (a hard dependency of this bundle), NOT in the shell,
     * which is why this needs no interface_exists guard the way the nav row does
     * and why it is registered unconditionally: implementing the contract costs
     * a contracts dependency this bundle already carries and a security service
     * it already has, never a dependency on the shell.
     *
     * IT TAKES `security.token_storage`, NOT `security.helper`. The helper is a
     * SecurityBundle class; the storage is security-core's, and it is the whole
     * question this source asks — who does the current token name. The service
     * id is SecurityBundle's either way, and this bundle's screens are
     * registered only where a firewall exists.
     *
     * ALIASED, NOT TAGGED. The shell reads one id, `shell.user_badge_source`,
     * and two sources claiming to know who is signed in is the disagreement the
     * contract exists to prevent — so this is an alias, single-claimant by design.
     * A host that wants a different card overrides the alias in its own config,
     * which beats a bundle's; where there is no shell, nothing reads the alias
     * and it is harmlessly inert.
     */
    $services->set('team.user_badge_source', UserBadgeSource::class)
        ->args([service('security.token_storage')]);
    $services->alias('shell.user_badge_source', 'team.user_badge_source');

    /*
     * THE SOLE-ACTIVE-SUPER-ADMIN INVARIANT. Every write path that lowers a
     * tier or deactivates an account asks this first, so the refusal happens
     * before anything is stored rather than after.
     */
    $services->set('team.super_admin_invariant', SuperAdminInvariant::class)
        ->args([service(UserRepository::class)]);

    /*
     * EVERY WAY AN ACCOUNT COMES INTO BEING OR CHANGES. Four callers — the two
     * add-somebody forms, the record page and the console an installation is
     * bootstrapped from — share one set of rules rather than each keeping a
     * copy. It asks nothing about who is signed in, which is what lets the
     * console call it when there is nobody to be.
     */
    $services->set('team.accounts', UserService::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('security.user_password_hasher'),
            service('team.super_admin_invariant'),
        ]);

    /*
     * WHAT A POSITION IS AND WHAT IT GRANTS. The catalogue is a collaborator
     * rather than an argument, because what may be granted is a fact about the
     * installation's installed modules and not about the screen that is asking.
     */
    $services->set('team.positions', PositionService::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('team.permissions'),
        ]);

    /*
     * THE ORG CHART'S SHAPE. The audit line a scope change leaves is the
     * entity's own doing; this supplies who and why, and stores the result.
     */
    $services->set('team.departments', DepartmentService::class)
        ->args([service('doctrine.orm.entity_manager')]);

    /*
     * THE THREE WRITES BEHIND THE DOORS A STRANGER REACHES. It knows nothing
     * about the request: signing every OTHER session out is a fact about the
     * browser in hand and stays with the screen.
     */
    $services->set('team.password_reset', PasswordResetService::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('security.user_password_hasher'),
        ]);

    /*
     * The sign-in refusal for a deactivated account. NOT tagged: a user checker
     * is named by the FIREWALL (`user_checker:`), which is the installation's
     * file — so the service is registered and public here, and the README's
     * security block names its id. A tag would have been this bundle deciding
     * a firewall's shape for every installation that has one.
     */
    $services->set('team.user_checker', ActiveUserChecker::class)->public();

    /*
     * WHAT THE TEAM PAGE KNOWS BEFORE IT DRAWS A ROW — the counts and the
     * decisions waiting on a person, asked once for the whole surface rather
     * than once per widget.
     */
    $services->set('team.overview', TeamOverview::class)
        ->args([
            service(UserRepository::class),
            service(PositionRepository::class),
            service(DepartmentRepository::class),
        ]);

    /*
     * THE TWO DASHBOARD SURFACES. Both team screens ride the widget framework
     * rather than a copy of it, which is why ShellBundle is a hard
     * requirement of this bundle and not a suggestion: the roster and the matrix
     * are widget surfaces, and a surface with no framework under it is a page
     * whose six drawn directions can never be adopted.
     *
     * The tag goes on BY HAND. A reusable bundle is not autoconfigured, and a
     * surface that missed the tag has a working dashboard and an unreachable
     * registry entry — nothing renders differently until the day somebody runs
     * `widget:prune` and it reads their stored layouts as orphans.
     */
    $services->set('team.widget_surface.roster', TeamWidgets::class)
        ->tag(WidgetSurfaceInterface::TAG);

    $services->set('team.widget_surface.positions', PositionWidgets::class)
        ->tag(WidgetSurfaceInterface::TAG);

    /*
     * THE TEAM PAGE — the roster surface. Gated on team.manage by an attribute
     * on the action, so the gate is a permission this installation's matrix can
     * grant and revoke rather than a tier nobody can audit.
     */
    $services->set('team.controller.team', TeamController::class)
        ->args([
            service('twig'),
            service(UserRepository::class),
            service(PositionRepository::class),
            service(DepartmentRepository::class),
            service('team.overview'),
            service('shell.widget.service'),
            service('security.token_storage'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(TeamController::class, 'team.controller.team')->public();

    /*
     * THE ROSTER'S WIDGET LIBRARY — chrome around the shell's shared
     * preset component. It takes the roster controller itself, because the
     * library previews the REAL widgets on REAL data and a second, thinner
     * context for the preview would be the one place the two screens could
     * disagree about what a widget shows.
     */
    $services->set('team.controller.team_widgets', TeamWidgetsController::class)
        ->args([
            service('twig'),
            service('router'),
            service('shell.widget.service'),
            service('shell.widget.endpoint'),
            service('team.controller.team'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(TeamWidgetsController::class, 'team.controller.team_widgets')->public();

    /*
     * ONE PERSON'S RECORD, and the writes that change it. There is no delete
     * route and there will not be one: accounts are deactivated, never removed.
     */
    $services->set('team.controller.member', MemberController::class)
        ->args([
            service('twig'),
            service(UserRepository::class),
            service(PositionRepository::class),
            service('team.permissions'),
            service('team.super_admin_invariant'),
            service('team.accounts'),
            service('security.csrf.token_manager'),
            service('router'),
            service('security.token_storage'),
            service('team.area_authority'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(MemberController::class, 'team.controller.member')->public();

    /*
     * POSITIONS AND PERMISSIONS — the matrix surface, and the one screen that
     * writes a grant.
     */
    $services->set('team.controller.position', PositionController::class)
        ->args([
            service('twig'),
            service(PositionRepository::class),
            service(DepartmentRepository::class),
            service(UserRepository::class),
            service('team.permissions'),
            service('team.positions'),
            service('security.csrf.token_manager'),
            service('router'),
            service('security.token_storage'),
            service('shell.widget.service'),
            service('team.area_authority'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(PositionController::class, 'team.controller.position')->public();

    /*
     * THE ORG CHART'S HOME — departments, and the three writes that shape them.
     * The matrix groups by a department and the roster bands by one, so this is
     * the screen where a department is made in the first place.
     *
     * NOT A WIDGET SURFACE, so no widget service and no surface tag. The roster
     * and the matrix ride the framework because directions were DRAWN for them;
     * nothing was drawn for this one, and six invented renderings would be a
     * design made by the implementation.
     */
    $services->set('team.controller.department', DepartmentController::class)
        ->args([
            service('twig'),
            service(DepartmentRepository::class),
            service(PositionRepository::class),
            service(UserRepository::class),
            service('doctrine.orm.entity_manager'),
            service('team.departments'),
            service('team.positions'),
            service('security.csrf.token_manager'),
            service('router'),
            service('security.token_storage'),
            service('team.area_authority'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(DepartmentController::class, 'team.controller.department')->public();

    $services->set('team.controller.position_widgets', PositionWidgetsController::class)
        ->args([
            service('twig'),
            service('router'),
            service('shell.widget.service'),
            service('shell.widget.endpoint'),
            service('team.controller.position'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(PositionWidgetsController::class, 'team.controller.position_widgets')->public();

    /*
     * THE TWO LETTERS THIS BUNDLE SENDS, and the one question every screen that
     * offers to send one asks first.
     *
     * THE MAILER IS OPTIONAL AND nullOnInvalid() IS THE WHOLE MECHANISM:
     * symfony/mailer is a suggestion rather than a requirement, so an
     * installation that never sends mail does not carry it — and where the
     * package is absent, OR present with no transport configured, the service
     * simply is not in the container and this is constructed with null. One
     * check covers both, which is right, because from a screen's point of view
     * they are the same fact.
     */
    $services->set('team.mail', Mail::class)
        ->args([
            service('mailer.mailer')->nullOnInvalid(),
            param('team.mail_from'),
            param('team.installation_name'),
        ]);

    /*
     * ADDING SOMEBODY — both ways, side by side. One needs nothing from the
     * deployment; the other is offered and refused where there is no mailer.
     */
    $services->set('team.controller.invite', InviteController::class)
        ->args([
            service('twig'),
            service(UserRepository::class),
            service(PositionRepository::class),
            service('team.accounts'),
            service('security.csrf.token_manager'),
            service('router'),
            service('security.token_storage'),
            service('team.mail'),
            service('team.area_authority'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(InviteController::class, 'team.controller.invite')->public();

    /*
     * THE SELF-SERVICE SCREENS. Public by route and by design: they are the
     * three a stranger reaches with nobody to ask, so they are the one part of
     * this bundle that must work with no session at all.
     */
    $services->set('team.controller.reset', PasswordResetController::class)
        ->args([
            service('twig'),
            service(UserRepository::class),
            service('team.password_reset'),
            service('security.csrf.token_manager'),
            service('router'),
            service('security.token_storage'),
            service('team.mail'),
            param('team.after_sign_in_path'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(PasswordResetController::class, 'team.controller.reset')->public();

    /*
     * The sign-in screen. Registered unconditionally: this bundle requires
     * symfony/security-bundle outright, unlike a module that merely benefits
     * from one — a team bundle in an installation with no firewall would be a
     * user table nobody can ever become.
     *
     * The alias is what makes `SecurityController::login` resolvable from the
     * attribute route: Symfony's controller resolver looks the class name up in
     * the container, and a bundle's own services are private by default.
     */
    $services->set('team.controller.security', SecurityController::class)
        ->args([
            service('twig'),
            service('security.authentication_utils'),
            service('security.token_storage'),
            param('team.after_sign_in_path'),
            param('team.sign_in_lede'),
        ])
        ->tag('controller.service_arguments');
    $services->alias(SecurityController::class, 'team.controller.security')->public();
};
