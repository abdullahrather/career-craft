<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The candidate's own mail server, so applications go out from their address. The password
 * is encrypted, and is never shown again once saved.
 *
 * @property int $id
 * @property int $user_id
 * @property string $host
 * @property int $port
 * @property string $security
 * @property string $username
 * @property string $password
 * @property string $from_address
 * @property string $from_name
 * @property Carbon|null $verified_at
 */
#[Fillable(['host', 'port', 'security', 'username', 'password', 'from_address', 'from_name', 'verified_at'])]
#[Hidden(['password'])]
final class MailAccount extends Model
{
    /** The ports mail servers take submissions on. */
    public const array PORTS = [25, 465, 587, 2525];

    /**
     * The ways of securing the connection. "none" is only for a test server on this machine,
     * such as Mailpit in development.
     *
     * @return list<string>
     */
    public static function securities(): array
    {
        return app()->environment('local', 'testing') ? ['ssl', 'starttls', 'none'] : ['ssl', 'starttls'];
    }

    /**
     * Why the server can't be used, or null when it can. Outside development it must be a
     * public address on a mail port, so the setting can't be used to reach other machines.
     */
    public static function hostProblem(string $host, int $port): ?string
    {
        if (app()->environment('local', 'testing')) {
            return null;
        }
        if (! in_array($port, self::PORTS, true)) {
            return __('Use your mail server\'s port for sending: 465, 587, 25 or 2525.');
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : (gethostbynamel($host) ?: []);
        if ($addresses === []) {
            return __('This mail server can\'t be found. Check its name.');
        }
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return __('This mail server is on a private network. Use your provider\'s public server.');
            }
        }

        return null;
    }

    /**
     * The mailer settings for Laravel's on-demand mailer.
     *
     * @return array<string, mixed>
     */
    public function transport(): array
    {
        return [
            'transport' => 'smtp',
            'scheme' => $this->security === 'ssl' ? 'smtps' : 'smtp',
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username !== '' ? $this->username : null,
            'password' => $this->password !== '' ? $this->password : null,
            'timeout' => 15,
            'require_tls' => $this->security === 'starttls',
            'auto_tls' => $this->security !== 'none',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['password' => 'encrypted', 'port' => 'integer', 'verified_at' => 'datetime'];
    }
}
