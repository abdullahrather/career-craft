<?php

declare(strict_types=1);

namespace App\Models;

use Closure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

/**
 * The candidate's own mail account, so applications go out from their address: a mail server
 * (smtp), or a Gmail sign-in (gmail). Passwords and tokens are encrypted, and never shown
 * again once saved.
 *
 * @property int $id
 * @property int $user_id
 * @property string $provider smtp or gmail.
 * @property string|null $host
 * @property int|null $port
 * @property string|null $security
 * @property string|null $username
 * @property string|null $password
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property string $from_address
 * @property string $from_name
 * @property Carbon|null $verified_at
 */
#[Fillable(['provider', 'host', 'port', 'security', 'username', 'password', 'access_token', 'refresh_token', 'token_expires_at', 'from_address', 'from_name', 'verified_at'])]
#[Hidden(['password', 'access_token', 'refresh_token'])]
final class MailAccount extends Model
{
    /**
     * Looks up a name's DNS records instead of dns_get_record(), so tests need no network.
     *
     * @var (Closure(string): list<array<string, mixed>>)|null
     */
    public static ?Closure $lookup = null;

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
        return app()->environment('local', 'testing') ? null : self::problem(self::addresses($host), $port);
    }

    /**
     * A mailer for this server, or why there can't be one. Outside development the name is
     * looked up once, checked, and the connection goes to that very address, so a lookup
     * that changes in between can't lead somewhere else. TLS still checks the server's name.
     */
    public function mailer(): Mailer|string
    {
        if ($this->provider === 'gmail') {
            return Mail::build(['transport' => 'gmail', 'account' => $this->id]);
        }
        $settings = $this->transport();
        $pinned = null;
        if (! app()->environment('local', 'testing')) {
            $addresses = self::addresses((string) $this->host);
            $problem = self::problem($addresses, (int) $this->port);
            if ($problem !== null) {
                return $problem;
            }
            $pinned = $addresses[0];
            $settings['host'] = str_contains($pinned, ':') ? "[{$pinned}]" : $pinned;
        }

        $mailer = Mail::build($settings);
        $transport = $mailer->getSymfonyTransport();
        if ($pinned !== null && $transport instanceof EsmtpTransport && ($stream = $transport->getStream()) instanceof SocketStream) {
            $stream->setStreamOptions(array_replace_recursive($stream->getStreamOptions(), ['ssl' => ['peer_name' => $this->host]]));
        }

        return $mailer;
    }

    /**
     * The mailer settings for Laravel's on-demand mailer, before the address is pinned.
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
            'username' => $this->username ?: null,
            'password' => $this->password ?: null,
            'timeout' => 15,
            'require_tls' => $this->security === 'starttls',
            'auto_tls' => $this->security !== 'none',
        ];
    }

    /**
     * Every address the name points to, IPv4 and IPv6.
     *
     * @return list<string>
     */
    private static function addresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }
        $records = self::$lookup !== null ? (self::$lookup)($host) : (@dns_get_record($host, DNS_A | DNS_AAAA) ?: []);

        $addresses = [];
        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }

    /** @param  list<string>  $addresses */
    private static function problem(array $addresses, int $port): ?string
    {
        if (! in_array($port, self::PORTS, true)) {
            return __('Use your mail server\'s port for sending: 465, 587, 25 or 2525.');
        }
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['password' => 'encrypted', 'access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'port' => 'integer', 'token_expires_at' => 'datetime', 'verified_at' => 'datetime'];
    }
}
