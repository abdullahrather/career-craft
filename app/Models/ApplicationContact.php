<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A person behind an application, such as the recruiter or the hiring manager.
 *
 * @property int $id
 * @property int $application_id
 * @property string $name
 * @property string $role
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $linkedin
 */
#[Fillable(['name', 'role', 'email', 'phone', 'linkedin'])]
final class ApplicationContact extends Model {}
