<?php

declare(strict_types=1);

namespace App\Inbox;

use DirectoryTree\ImapEngine\Connection\Streams\ImapStream;

/**
 * Connects to an address checked beforehand instead of looking the name up again, so a
 * lookup that changes in between can't lead to another machine. TLS still checks the
 * certificate against the server's name.
 */
final class PinnedImapStream extends ImapStream
{
    public function __construct(private string $address, private string $name) {}

    /** @param  array<string, mixed>  $options */
    public function open(string $transport, string $host, int $port, int $timeout, array $options = []): bool
    {
        $address = str_contains($this->address, ':') ? "[{$this->address}]" : $this->address;

        return parent::open($transport, $address, $port, $timeout, array_replace_recursive($options, [
            'ssl' => ['peer_name' => $this->name, 'verify_peer' => true, 'verify_peer_name' => true],
        ]));
    }
}
