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
 * WHOEVER OWNS A STEP OF SETTING AN INSTALLATION UP PUBLISHES IT HERE.
 *
 * The list is assembled, never authored: adding an area is the area bundle's
 * step, composing a position is the team's, and a module with a step of its
 * own publishes it the day it is installed. A hardcoded five would go stale
 * the first time the product grew a sixth, and nothing would say so.
 *
 * IT IS READ AT REQUEST TIME, so a step that was outstanding this morning is
 * done this morning. A step that cached its standing would be a checklist
 * disagreeing with the pages it links to.
 *
 * Tag it by hand; see {@see SettingsFigureSourceInterface} on why.
 */
interface SettingsStepSourceInterface
{
    public const string TAG = 'shell.settings_step';

    /** Lower first — the order somebody would actually do them in. */
    public function position(): int;

    /**
     * @return iterable<SettingsStep>
     */
    public function settingsSteps(): iterable;
}
