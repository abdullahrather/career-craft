<?php

declare(strict_types=1);

namespace App\Import;

use RuntimeException;

/** The file isn't what it says it is, or has no text we can read. */
final class UnreadableFile extends RuntimeException {}
