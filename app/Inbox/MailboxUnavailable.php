<?php

declare(strict_types=1);

namespace App\Inbox;

use RuntimeException;

/** The mailbox couldn't be read; the message says why, for the candidate. */
final class MailboxUnavailable extends RuntimeException {}
