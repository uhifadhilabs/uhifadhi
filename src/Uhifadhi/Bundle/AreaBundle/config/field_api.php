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

use Uhifadhi\Bundle\AreaBundle\Api\FieldRoster;
use Uhifadhi\Bundle\AreaBundle\Api\State\AreasMineProvider;
use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;

/*
 * WHAT THIS BUNDLE SERVES A FIELD CLIENT: `GET /api/areas/mine`, the offline
 * cache a handset fills at sign-in.
 *
 * IMPORTED ONLY WHERE BOTH HALVES ARE PRESENT — see AreaBundle::loadExtension().
 * Without api-platform there is no `/api` to attach to; without security there is
 * no authorization checker, and an endpoint that hands out an installation's whole
 * estate must never be able to fall back to "grant everything" because the thing
 * that narrows it was missing. An installation lacking either gets the area model
 * and no field API rather than an unnarrowed one.
 *
 * Everything here is defined EXPLICITLY, with ids prefixed by the bundle alias, as
 * everywhere else in this bundle:
 *
 *   area.api.roster          the people a client may name on a record
 *   area.api.areas_provider  the areas the bearer account may work in
 *
 *   — https://symfony.com/doc/current/bundles/best_practices.html
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('area.api.roster', FieldRoster::class)
        ->args([service('doctrine.orm.entity_manager')]);

    /*
     * TAGGED BY HAND, because a reusable bundle is not autoconfigured: an
     * untagged provider is not in the locator API Platform resolves an
     * operation's `provider:` through, and the endpoint would answer 500 with
     * "Provider not found".
     *
     * THE `key` ATTRIBUTE IS WHAT KEEPS THE ID PREFIXED. The locator is built
     * with `tagged_locator('api_platform.state_provider', 'key')`, so a tag
     * without one is indexed by SERVICE ID and the resource's
     * `provider: AreasMineProvider::class` would only resolve if the id were the
     * class name. Naming the class in `key` satisfies the resource and leaves the
     * id under this bundle's alias, as a reusable bundle's ids must be.
     *
     * @see vendor/api-platform/core/src/Symfony/Bundle/Resources/config/state/state.php — the locator and its index attribute
     * @see vendor/api-platform/core/src/State/CallableProvider.php — the lookup that throws when a provider is not in it
     */
    $services->set('area.api.areas_provider', AreasMineProvider::class)
        ->args([
            service(AreaOfInterestRepository::class),
            service('area.api.roster'),
            service('security.authorization_checker'),
        ])
        ->tag('api_platform.state_provider', ['key' => AreasMineProvider::class]);
};
