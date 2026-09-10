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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web\Fixtures;

use Uhifadhi\Contracts\Shell\ModuleTab;
use Uhifadhi\Contracts\Shell\ModuleTabsInterface;

/**
 * A MODULE THAT DECLARES ITS DATA PLACES, stood in for.
 *
 * This bundle depends on no module, and the fourth rung of the sidebar is
 * exactly the thing a module contributes to it. So the suite ships one
 * declaration of its own, tagged by hand — which is what a real module has to
 * do anyway, since a reusable bundle's services are not autoconfigured. What is
 * being specified is that the tree ASKS and renders what it is handed, not what
 * any particular module answers.
 */
final class PatrolsModuleTabs implements ModuleTabsInterface
{
    public function slug(): string
    {
        return 'patrols';
    }

    public function tabs(): array
    {
        return [
            new ModuleTab('Overview', 'test_module_entry'),
            new ModuleTab('Patrols', 'test_module_list'),
        ];
    }
}
