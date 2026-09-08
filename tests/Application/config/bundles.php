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

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;

/*
 * What the throwaway application has installed. It grows one line per core
 * bundle as each lands, which is exactly what an installation's own
 * config/bundles.php does — Flex writes those lines from the core's recipe.
 */
return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    DoctrineMigrationsBundle::class => ['all' => true],
    RegistryBundle::class => ['all' => true],
];
