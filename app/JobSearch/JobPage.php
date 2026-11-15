<?php

declare(strict_types=1);

namespace App\JobSearch;

use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads a job post from its web page. Many job boards describe the post as structured
 * data (schema.org JobPosting); that's used when it's there, and the page's text otherwise.
 *
 * The address comes from the person, so the server only fetches public web pages: http or
 * https on the usual ports, never a private or internal address, with each address checked
 * and pinned before connecting and every redirect checked again.
 */
final class JobPage
{
    private const int MAX_BYTES = 2_000_000;

    private const int MAX_REDIRECTS = 3;

    private const int TIMEOUT = 10;

    /** The most text kept: enough for any job post. */
    public const int MAX_TEXT = 30_000;

    /**
     * @return array{text: string, title: string, company: string, location: string, remote: bool}
     *
     * @throws UnreadablePage
     */
    public static function fetch(string $url): array
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            [$host, $port, $ip] = self::check($url);
            try {
                $response = Http::timeout(self::TIMEOUT)
                    ->withHeaders(['User-Agent' => 'CareerCraft job post reader', 'Accept' => 'text/html,application/xhtml+xml'])
                    ->withOptions([
                        'allow_redirects' => false,
                        // Connect to the address that was checked, not one a second lookup returns.
                        'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]],
                        'progress' => function (int $total, int $downloaded): void {
                            if ($downloaded > self::MAX_BYTES) {
                                throw new RuntimeException('too-large');
                            }
                        },
                    ])
                    ->get($url);
            } catch (ConnectionException) {
                throw new UnreadablePage('unreachable');
            } catch (RuntimeException $exception) {
                throw new UnreadablePage(str_contains($exception->getMessage(), 'too-large') ? 'too-large' : 'unreachable');
            }

            if ($response->redirect()) {
                $url = self::follow($url, (string) $response->header('Location'));

                continue;
            }
            if (! $response->successful()) {
                throw new UnreadablePage('unreachable');
            }
            if (! str_contains(strtolower((string) $response->header('Content-Type')), 'html')) {
                throw new UnreadablePage('not-a-page');
            }
            if (strlen($response->body()) > self::MAX_BYTES) {
                throw new UnreadablePage('too-large');
            }

            return self::read($response->body());
        }

        throw new UnreadablePage('unreachable');
    }

    /**
     * The post in a page's HTML: from its JobPosting data when it has one, else its text.
     *
     * @return array{text: string, title: string, company: string, location: string, remote: bool}
     *
     * @throws UnreadablePage
     */
    public static function read(string $html): array
    {
        $posting = self::posting($html);
        $text = $posting !== null ? self::text(self::string($posting['description'] ?? '')) : self::pageText($html);
        if ($posting !== null && mb_strlen($text) < 200) {
            $text = self::pageText($html);
        }
        if (mb_strlen($text) < 200) {
            // A page built in the browser by JavaScript has no text for us to read.
            throw new UnreadablePage('no-text');
        }

        return [
            'text' => mb_substr($text, 0, self::MAX_TEXT),
            'title' => self::string($posting['title'] ?? ''),
            'company' => self::company($posting['hiringOrganization'] ?? null),
            'location' => self::location($posting['jobLocation'] ?? null),
            'remote' => strtoupper(self::string($posting['jobLocationType'] ?? '')) === 'TELECOMMUTE',
        ];
    }

    /**
     * Plain text from HTML, with a line for each paragraph, heading and list item.
     */
    public static function text(string $html): string
    {
        $html = (string) preg_replace('#<\s*(br|/p|/div|/h[1-6]|/tr|/ul|/ol)\b[^>]*>#i', "\n", $html);
        $html = (string) preg_replace('#<\s*li\b[^>]*>#i', "\n- ", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = array_map(fn (string $line): string => trim((string) preg_replace('/[^\S\n]+/u', ' ', $line)), explode("\n", $text));

        return trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }

    /**
     * The host, port and checked address to connect to.
     *
     * @return array{string, int, string}
     *
     * @throws UnreadablePage
     */
    private static function check(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new UnreadablePage('bad-url');
        }
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (! in_array($port, [80, 443], true)) {
            throw new UnreadablePage('blocked-address');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : (gethostbynamel($host) ?: []);
        if ($addresses === []) {
            throw new UnreadablePage('unreachable');
        }
        foreach ($addresses as $address) {
            // Only addresses on the public internet: no private, loopback, link-local or shared ranges.
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                throw new UnreadablePage('blocked-address');
            }
        }

        return [$host, $port, $addresses[0]];
    }

    /** The absolute address a redirect points to. */
    private static function follow(string $from, string $location): string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }
        $parts = parse_url($from);
        if (! str_starts_with($location, '/') || ! isset($parts['scheme'], $parts['host'])) {
            throw new UnreadablePage('unreachable');
        }
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return "{$parts['scheme']}://{$parts['host']}{$port}{$location}";
    }

    /**
     * The page's schema.org JobPosting, from its JSON-LD scripts.
     *
     * @return array<string, mixed>|null
     */
    private static function posting(string $html): ?array
    {
        preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $scripts);
        foreach ($scripts[1] as $json) {
            $found = self::find(json_decode(trim($json), true));
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private static function find(mixed $data): ?array
    {
        if (! is_array($data)) {
            return null;
        }
        $type = $data['@type'] ?? null;
        if ($type === 'JobPosting' || (is_array($type) && in_array('JobPosting', $type, true))) {
            /** @var array<string, mixed> $data */
            return $data;
        }
        foreach ($data as $value) {
            $found = self::find($value);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private static function company(mixed $organisation): string
    {
        return is_array($organisation) ? self::string($organisation['name'] ?? '') : self::string($organisation);
    }

    private static function location(mixed $location): string
    {
        $first = is_array($location) && array_is_list($location) ? ($location[0] ?? null) : $location;
        $address = is_array($first) ? ($first['address'] ?? null) : null;
        if (! is_array($address)) {
            return '';
        }
        $country = $address['addressCountry'] ?? '';
        $country = is_array($country) ? ($country['name'] ?? '') : $country;

        return implode(', ', array_filter([self::string($address['addressLocality'] ?? ''), self::string($country)]));
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
    }

    /** The visible text of a page, without scripts, styles, navigation, header and footer. */
    private static function pageText(string $html): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $remove = $xpath->query('//script|//style|//noscript|//nav|//header|//footer|//svg|//form');
        foreach ($remove === false ? [] : iterator_to_array($remove) as $node) {
            if ($node instanceof DOMNode) {
                $node->parentNode?->removeChild($node);
            }
        }
        $main = $xpath->query('//main|//article');
        $root = $main !== false && $main->length > 0 ? $main->item(0) : $document->getElementsByTagName('body')->item(0);

        return $root instanceof DOMNode ? self::text((string) $document->saveHTML($root)) : '';
    }
}
