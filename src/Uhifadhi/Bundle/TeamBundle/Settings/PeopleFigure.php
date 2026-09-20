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

namespace Uhifadhi\Bundle\TeamBundle\Settings;

use Uhifadhi\Bundle\TeamBundle\Repository\UserRepository;
use Uhifadhi\Contracts\Settings\SettingsFigure;
use Uhifadhi\Contracts\Settings\SettingsFigureSourceInterface;

/**
 * HOW MANY PEOPLE THIS INSTALLATION IS FOR.
 *
 * ACTIVE FIRST, BECAUSE THAT IS THE NUMBER EVERY OTHER FIGURE IS ABOUT. An
 * account that has been closed still has records attached to it and still
 * appears in a history; it does not appear on a watch, in a patrol or in a
 * queue, so a headline count that included it would over-state the size of
 * the organisation the rest of the product is reporting on. The closed ones
 * are the caption's, where they belong: kept, and not counted twice.
 *
 * NOT SPLIT BY DEPARTMENT, though the department is what somebody reading
 * this eventually wants. A caption naming two departments is right for an
 * installation with two and wrong for one with nine, and which two would be
 * this card choosing on somebody's behalf. The department reading has a
 * screen of its own; this card says how big the organisation is.
 */
final readonly class PeopleFigure implements SettingsFigureSourceInterface
{
    /** After the areas: the places first, then who is in them. */
    public const int POSITION = 30;

    public function __construct(private UserRepository $people)
    {
    }

    public function position(): int
    {
        return self::POSITION;
    }

    public function settingsFigures(): iterable
    {
        $active = $this->people->countActive();
        $all = $this->people->countAll();
        $closed = $all - $active;

        yield new SettingsFigure(
            'people',
            'People',
            (string) $active,
            caption: 0 === $active
                ? 'nobody has an account here yet'
                : \sprintf('%d active', $active),
            warning: $closed > 0 ? \sprintf('%d closed', $closed) : null,
        );
    }
}
