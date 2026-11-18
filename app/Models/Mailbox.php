<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The candidate's mailbox, read over IMAP with an app password to find employers' replies.
 * The password is encrypted, and never shown again once saved.
 *
 * @property int $id
 * @property int $user_id
 * @property string $host
 * @property int $port
 * @property string $security ssl, starttls or none.
 * @property string $username
 * @property string $password
 * @property int|null $last_uid
 * @property Carbon|null $checked_at
 * @property string|null $error
 */
#[Fillable(['host', 'port', 'security', 'username', 'password', 'last_uid', 'checked_at', 'error'])]
#[Hidden(['password'])]
final class Mailbox extends Model
{
    /** The ports IMAP servers listen on: 993 with SSL/TLS, 143 with STARTTLS. */
    public const array PORTS = [993, 143];

    /** The first check reads this many days back; later checks read only what's new. */
    public const int FIRST_DAYS = 30;

    /** At most this many emails are looked at in one check; the rest wait for the next. */
    public const int BATCH = 200;

    /**
     * Why the server can't be used, or null when it can. Outside development it must be a
     * public address on an IMAP port, so the setting can't be used to reach other machines.
     */
    public static function hostProblem(string $host, int $port): ?string
    {
        return app()->environment('local', 'testing') ? null : self::problem(MailAccount::addresses($host), $port);
    }

    /**
     * Why a server at these addresses, on this port, can't be used.
     *
     * @param  list<string>  $addresses
     */
    public static function problem(array $addresses, int $port): ?string
    {
        if (! in_array($port, self::PORTS, true)) {
            return __('Use your mail server\'s port for reading: 993 or 143.');
        }

        return MailAccount::networkProblem($addresses);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['password' => 'encrypted', 'port' => 'integer', 'last_uid' => 'integer', 'checked_at' => 'datetime'];
    }
}
