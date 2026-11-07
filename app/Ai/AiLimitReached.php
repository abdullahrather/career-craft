<?php

declare(strict_types=1);

namespace App\Ai;

use RuntimeException;

/** The person has used their AI allowance for today. */
final class AiLimitReached extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('You have used today\'s AI allowance. It starts again at midnight.'));
    }
}
