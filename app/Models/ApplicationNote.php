<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A note on an application.
 *
 * @property int $id
 * @property int $application_id
 * @property string $body
 * @property Carbon $created_at
 */
#[Fillable(['body'])]
final class ApplicationNote extends Model {}
