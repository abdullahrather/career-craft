<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The candidate's mailbox, read to find employers' replies: over IMAP with an app password,
 * or through their Gmail or Outlook sign-in with read-only permission. The password and tokens are
 * encrypted, and never shown again once saved.
 *
 * @property int $id
 * @property int $user_id
 * @property string $provider imap, gmail or outlook.
 * @property string|null $host
 * @property int|null $port
 * @property string|null $security ssl, starttls or none.
 * @property string $username The login, or for a sign-in its address.
 * @property string|null $password
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property int|null $last_uid
 * @property Carbon|null $checked_at
 * @property string|null $error
 * @property bool $login_failed The server refused the login; scheduled checks wait for new settings.
 */
#[Fillable(['provider', 'host', 'port', 'security', 'username', 'password', 'access_token', 'refresh_token', 'token_expires_at', 'last_uid', 'checked_at', 'error', 'login_failed'])]
#[Hidden(['password', 'access_token', 'refresh_token'])]
final class Mailbox extends Model
{
    /** The database default, so a mailbox has it before it is saved and reloaded. */
    protected $attributes = ['provider' => 'imap', 'login_failed' => false];

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
        return ['password' => 'encrypted', 'access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'token_expires_at' => 'datetime', 'port' => 'integer', 'last_uid' => 'integer', 'checked_at' => 'datetime', 'login_failed' => 'boolean'];
    }
}
