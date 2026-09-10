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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Integration\Fixtures;

use Uhifadhi\Contracts\Devkit\CommandIo;

/**
 * THE TERMINAL, WRITTEN DOWN — what a handler said, and what it was told, with
 * no terminal anywhere near it.
 *
 * This is the whole reason {@see CommandIo} exists as a contract rather than as
 * devkit's private detail. A handler that wrote to \STDOUT could only be tested
 * by a suite willing to read its own process's file descriptors, so in practice
 * it was not tested at all — the messages simply appeared in the test run,
 * which is how the leak stayed invisible. Given the channel, the same handler
 * is provable: run it against this, and read back the two streams.
 *
 * The queued lines are standard input. readLine() consumes them in order and
 * returns null once they run out, which is the end-of-stream a piped
 * passphrase reaches after its one line.
 *
 * A SECRET IS SCRIPTED SEPARATELY, because a test wants to say which answer was
 * typed where the screen stayed blank. When none is scripted, readSecret() takes
 * the next ordinary line instead — which is not a shortcut but the thing itself:
 * with no terminal there is no echo to switch off, and the passphrase a pipe
 * offers is read off the same stream the lines come from.
 */
final class RecordingCommandIo implements CommandIo
{
    /** @var list<string> */
    public array $written = [];

    /** @var list<string> */
    public array $errors = [];

    /** @var list<string> */
    private array $pending;

    /** @var list<string> */
    private array $secrets;

    /**
     * @param list<string> $input   the lines standard input will offer, in order
     * @param list<string> $secrets the answers typed where nothing was echoed, in order
     */
    public function __construct(array $input = [], array $secrets = [])
    {
        $this->pending = $input;
        $this->secrets = $secrets;
    }

    public function write(string $line): void
    {
        $this->written[] = $line;
    }

    public function error(string $line): void
    {
        $this->errors[] = $line;
    }

    public function readLine(): ?string
    {
        return array_shift($this->pending);
    }

    public function readSecret(): ?string
    {
        return array_shift($this->secrets) ?? array_shift($this->pending);
    }

    /** Everything said on standard output, as one block to assert against. */
    public function output(): string
    {
        return implode("\n", $this->written);
    }

    /** Everything said on standard error, as one block to assert against. */
    public function diagnostics(): string
    {
        return implode("\n", $this->errors);
    }
}
