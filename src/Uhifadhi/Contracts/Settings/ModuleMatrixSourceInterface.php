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

namespace Uhifadhi\Contracts\Settings;

/**
 * WHOEVER HAS THE AREAS AND THE LEDGER ANSWERS WHAT RUNS WHERE.
 *
 * AN ALIAS, NOT A TAGGED COLLECTION, and for the same reason the user badge
 * is one: two things claiming to know which modules run in which areas is
 * exactly the disagreement this contract exists to prevent. A host or a core
 * bundle points the section at its implementation by aliasing the id the
 * section looks for:
 *
 *     $services->alias('shell.settings.module_matrix', Uhifadhi\Bundle\AreaBundle\Settings\AreaModuleMatrix::class);
 *
 * THE ALIAS IS OPTIONAL. An installation with no areas bundle has no matrix,
 * and the screen says so rather than refusing to boot.
 */
interface ModuleMatrixSourceInterface
{
    /** The service id the settings section asks for, where anybody answers it. */
    public const string SERVICE = 'shell.settings.module_matrix';

    public function moduleMatrix(): ModuleMatrix;
}
