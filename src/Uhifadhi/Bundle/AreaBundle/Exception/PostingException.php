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

namespace Uhifadhi\Bundle\AreaBundle\Exception;

/**
 * A POSTING CANNOT BE WRITTEN THAT WAY.
 *
 * THREE REFUSALS, AND EVERY ONE IS ABOUT A ROW THAT WOULD MEAN NOTHING. The
 * same person standing twice at one post is not two postings, it is one
 * recorded twice; a person standing at two posts at once is a roll nobody can
 * read, because a posting is where somebody WORKS and they work in one place;
 * and a posting that has ended is a fact about the past, which cannot be
 * handed today's lead.
 *
 * APPOINTING A SECOND LEADER IS NOT HERE, deliberately: it is what somebody
 * means when they say "she leads now", so it stands the old one down instead
 * of refusing.
 */
final class PostingException extends \RuntimeException
{
    public static function alreadyPosted(string $person, string $station): self
    {
        return new self(\sprintf('%s is already stationed at %s.', $person, $station));
    }

    /**
     * ONE POSTING A PERSON — ruled: one station, one area. The refusal names
     * where they already stand, because the person asking has almost always
     * forgotten rather than meant it, and "already posted" without a place is
     * a refusal they cannot act on.
     */
    public static function alreadyPostedElsewhere(string $person, string $station): self
    {
        return new self(\sprintf('%s is posted to %s. Somebody stands at one post at a time — end that posting first.', $person, $station));
    }

    public static function alreadyEnded(string $person): self
    {
        return new self(\sprintf('%s\'s posting here has ended, so it cannot be changed.', $person));
    }
}
