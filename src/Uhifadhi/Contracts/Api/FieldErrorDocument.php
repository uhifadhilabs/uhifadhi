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

namespace Uhifadhi\Contracts\Api;

/**
 * THE ONE SHAPE EVERY `/api` FAILURE TAKES: `{code, message, retryable,
 * details}`, which is a field client's whole failure policy.
 *
 * WHY A CONTRACT AND NOT A BUNDLE'S CONSTANT. The URL space owns the
 * shape, and the safety net that gives it to every unwritten refusal
 * lives with the machine door — but the bundles serving that space are
 * not the bundle the door is in, and one of them may be installed
 * without the other. A bundle that writes the document itself has to be
 * able to SAY SO, and a string it copied would drift the day the header
 * was renamed.
 *
 * NOTHING HERE IS A MECHANISM. This is a name, so that two packages
 * that must agree on one can do it without depending on each other.
 */
final class FieldErrorDocument
{
    /**
     * Set on a refusal whose body is ALREADY this document, so that the
     * net leaves it alone. Without it a well-formed refusal is replaced
     * by the generic one its status maps to, and every code and detail
     * the client branches on is lost on the way out.
     */
    public const string HANDLED_HEADER = 'X-Uhifadhi-Api-Error';

    private function __construct()
    {
    }
}
