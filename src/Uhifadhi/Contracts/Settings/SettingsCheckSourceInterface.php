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
 * WHOEVER CAN ANSWER A HEALTH QUESTION ANSWERS IT HERE.
 *
 * The installation screen counts the verdicts and draws the list; it runs no
 * check of its own beyond what it can read about itself, and it knows no
 * module. A module that can say whether its own scheduled work ran publishes
 * that here and appears in the list the day it is installed.
 *
 * A CHECK IS READ AT REQUEST TIME AND MUST BE CHEAP. This list is drawn on a
 * page somebody opens to find out whether anything is wrong; a source that
 * took a second to answer would make the screen itself the slowest thing in
 * the installation. Anything expensive is measured on a schedule and reported
 * here from what was stored.
 *
 * IT NEVER THROWS. A source that cannot reach the thing it checks returns
 * {@see CheckVerdict::Check} with the reason in the detail — an exception
 * would take down the one page that exists to report trouble.
 *
 * Tag it by hand; see {@see SettingsFigureSourceInterface} on why.
 */
interface SettingsCheckSourceInterface
{
    public const string TAG = 'shell.settings_check';

    /** Lower first among the rows; registration order breaks a tie. */
    public function position(): int;

    /**
     * @return iterable<SettingsCheck>
     */
    public function settingsChecks(): iterable;
}
