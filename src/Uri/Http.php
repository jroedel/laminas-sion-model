<?php

declare(strict_types=1);

namespace SionModel\Uri;

use SionModel\Uri\Exception\InvalidUriException;
use SionModel\Uri\Exception\InvalidUriPartException;

use function array_pop;
use function explode;
use function filter_var;
use function implode;
use function in_array;
use function preg_match;
use function preg_replace_callback;
use function rawurlencode;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strpos;
use function strtolower;
use function substr;

use const FILTER_FLAG_IPV4;
use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

/**
 * An http or https URL: parsed, judged, and rendered back.
 *
 * ## Why this is not a wrapper around `parse_url()`
 *
 * Because it is not a parser here, it is a **normaliser**, and its output is stored.
 * `SionModel\Db\Model\SionTable::filterUrl()` and its two siblings build one of these and
 * keep `toString()`, so every URL in seven tables has been through this code. What it does
 * to a value is therefore a fact about the data, not a detail:
 *
 * - `http://example.com` gains a path and becomes `http://example.com/`;
 * - the host is lowercased and **the scheme is not** — `HTTPS://Example.COM/Path` becomes
 *   `HTTPS://example.com/Path`;
 * - an empty `?` or `#` is dropped;
 * - a space becomes `%20`, a raw UTF-8 path is percent-encoded, and a `%` that is not
 *   followed by two hex digits is encoded as `%25`;
 * - `mailto:`, `javascript:` and `ftp:` **throw**, which is the only rejection there is —
 *   `this is not a url` is accepted and stored as `this%20is%20not%20a%20url`.
 *
 * `test/Rules/uri-surface.php` records all of it over forty shapes, and the `stored` half
 * of that file is what a URL column receives.
 *
 * ## What is not reproduced
 *
 * `Laminas\Uri\Uri` is 1,392 lines. Gone with it: relative-reference resolution (`resolve`,
 * `merge`, `removeDotSegments`), `normalize`, `fromParts`, `makeRelative`, the query-array
 * accessors, the `File` and `Mailto` subclasses, and the host-type flags — this accepts
 * what `Http` accepted, which is DNS, IPv4, IPv6 and reg-name, all through one shape check.
 *
 * The shape check is where this deliberately differs: laminas validated a DNS hostname
 * through `Laminas\Validator\Hostname`, whose 2,279 lines are mostly a table of top-level
 * domains. The table is not carried (see `SionModel\Validator\EmailAddress` for the same
 * decision and the same reasoning), so a well-formed host at an unlisted TLD is accepted
 * here and would have been rejected there. Strictly more permissive, and no stored URL in
 * the corpus changes.
 */
final class Http
{
    private const VALID_SCHEMES = ['http', 'https'];

    private const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';
    private const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';

    private ?string $scheme = null;
    private ?string $userInfo = null;
    private ?string $host = null;
    private ?int $port = null;
    private ?string $path = null;
    private ?string $query = null;
    private ?string $fragment = null;

    /** @throws InvalidUriPartException */
    public function __construct(?string $uri = null)
    {
        if (null !== $uri) {
            $this->parse($uri);
        }
    }

    /**
     * Take a URI apart, in the order RFC 3986 puts it together.
     *
     * @throws InvalidUriPartException
     */
    public function parse(string $uri): self
    {
        $this->scheme   = null;
        $this->userInfo = null;
        $this->host     = null;
        $this->port     = null;
        $this->path     = null;
        $this->query    = null;
        $this->fragment = null;

        if (preg_match('/^([A-Za-z][A-Za-z0-9\-\.+]*):/', $uri, $match)) {
            $this->setScheme($match[1]);
            $uri = substr($uri, strlen($match[1]) + 1);
        }

        if (preg_match('|^//([^/\?#]*)|', $uri, $match)) {
            $authority = $match[1];
            $uri       = substr($uri, strlen($match[0]));

            if (str_contains($authority, '@')) {
                //The user-info may itself contain '@', so the **last** segment is the host.
                $segments       = explode('@', $authority);
                $authority      = array_pop($segments);
                $this->userInfo = implode('@', $segments);
            }

            if (1 === preg_match('/:[\d]{0,5}$/', $authority, $matches)) {
                $port = substr($matches[0], 1);

                //An authority ending in a bare colon loses the colon and keeps a null port.
                if ('' !== $port) {
                    $this->port = (int) $port;
                }

                $authority = substr($authority, 0, -strlen($matches[0]));
            }

            $this->setHost($authority);
        }

        if ('' !== $uri && preg_match('|^[^\?#]*|', $uri, $match)) {
            $this->path = $match[0];
            $uri        = substr($uri, strlen($match[0]));
        }

        if ('' !== $uri && preg_match('|^\?([^#]*)|', $uri, $match)) {
            $this->query = $match[1];
            $uri         = substr($uri, strlen($match[0]));
        }

        if ('' !== $uri && str_starts_with($uri, '#')) {
            $this->fragment = substr($uri, 1);
        }

        //`SionModel\Uri\Http::parse()`'s one addition to its parent, and the reason
        //`http://example.com` is stored with a trailing slash.
        if (null === $this->path || '' === $this->path) {
            $this->path = '/';
        }

        return $this;
    }

    /** @throws InvalidUriPartException */
    public function setScheme(?string $scheme): self
    {
        if (null !== $scheme && ! in_array(strtolower($scheme), self::VALID_SCHEMES, true)) {
            throw new InvalidUriPartException(
                sprintf('Scheme "%s" is not valid or is not accepted by %s', $scheme, self::class),
                InvalidUriPartException::INVALID_SCHEME
            );
        }

        $this->scheme = $scheme;

        return $this;
    }

    /** @throws InvalidUriPartException */
    public function setHost(?string $host): self
    {
        if (null !== $host && '' !== $host && ! self::isValidHost($host)) {
            throw new InvalidUriPartException(
                sprintf('Host "%s" is not valid or is not accepted by %s', $host, self::class),
                InvalidUriPartException::INVALID_HOSTNAME
            );
        }

        $this->host = null === $host ? null : strtolower($host);

        return $this;
    }

    public function getScheme(): ?string
    {
        return $this->scheme;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getQuery(): ?string
    {
        return $this->query;
    }

    public function getFragment(): ?string
    {
        return $this->fragment;
    }

    public function getUserInfo(): ?string
    {
        return $this->userInfo;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    /** A URI with a scheme. */
    public function isAbsolute(): bool
    {
        return null !== $this->scheme;
    }

    /**
     * Enough of a URI to mean something.
     *
     * With a host: the path must be empty or start with `/`. Without one: no user-info and
     * no port, and either a path that does not start with `//`, or a query, or a fragment.
     */
    public function isValid(): bool
    {
        if (null !== $this->host && '' !== $this->host) {
            return null === $this->path || '' === $this->path || str_starts_with($this->path, '/');
        }

        if (
            (null !== $this->userInfo && '' !== $this->userInfo)
            || (null !== $this->port && 0 !== $this->port)
        ) {
            return false;
        }

        if (null !== $this->path && '' !== $this->path) {
            return ! str_starts_with($this->path, '//');
        }

        return (null !== $this->query && '' !== $this->query)
            || (null !== $this->fragment && '' !== $this->fragment);
    }

    /** Valid, and carrying nothing that only an absolute URI may carry. */
    public function isValidRelative(): bool
    {
        if (
            null !== $this->scheme
            || (null !== $this->host && '' !== $this->host)
            || (null !== $this->userInfo && '' !== $this->userInfo)
            || (null !== $this->port && 0 !== $this->port)
        ) {
            return false;
        }

        if (null !== $this->path && '' !== $this->path) {
            return ! str_starts_with($this->path, '//');
        }

        return (null !== $this->query && '' !== $this->query)
            || (null !== $this->fragment && '' !== $this->fragment);
    }

    /**
     * The URI as it will be stored.
     *
     * @throws InvalidUriException
     */
    public function toString(): string
    {
        if (! $this->isValid() && ($this->isAbsolute() || ! $this->isValidRelative())) {
            throw new InvalidUriException('URI is not valid and cannot be converted into a string');
        }

        $uri = '';

        if (null !== $this->scheme && '' !== $this->scheme) {
            $uri .= $this->scheme . ':';
        }

        if (null !== $this->host) {
            $uri .= '//';

            if (null !== $this->userInfo && '' !== $this->userInfo) {
                $uri .= $this->userInfo . '@';
            }

            $uri .= $this->host;

            if (null !== $this->port && 0 !== $this->port) {
                $uri .= ':' . $this->port;
            }
        }

        if (null !== $this->path && '' !== $this->path) {
            $uri .= self::encodePath($this->path);
        } elseif (
            null !== $this->host && '' !== $this->host
            && ((null !== $this->query && '' !== $this->query) || (null !== $this->fragment && '' !== $this->fragment))
        ) {
            $uri .= '/';
        }

        if (null !== $this->query && '' !== $this->query) {
            $uri .= '?' . self::encodeQueryFragment($this->query);
        }

        if (null !== $this->fragment && '' !== $this->fragment) {
            $uri .= '#' . self::encodeQueryFragment($this->fragment);
        }

        return $uri;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Percent-encode everything a path may not carry literally.
     *
     * The allowed set is unreserved plus `)( : @ & = + $ , / ; %`, transcribed from
     * `Laminas\Uri\Uri::encodePath()` including the stray `)(` its character class has
     * carried since Zend Framework. A `%` not introducing a valid escape is itself encoded,
     * so `100%` stays readable as `100%25` rather than becoming a broken escape.
     */
    public static function encodePath(string $path): string
    {
        return preg_replace_callback(
            '/(?:[^' . self::CHAR_UNRESERVED . ')(:@&=\+\$,\/;%]+|%(?![A-Fa-f0-9]{2}))/',
            static fn(array $match): string => rawurlencode($match[0]),
            $path
        ) ?? $path;
    }

    /** The same, for a query string or a fragment, which may also carry `? / : @`. */
    public static function encodeQueryFragment(string $input): string
    {
        return preg_replace_callback(
            '/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/\?]+|%(?![A-Fa-f0-9]{2}))/',
            static fn(array $match): string => rawurlencode($match[0]),
            $input
        ) ?? $input;
    }

    /**
     * An IPv4 literal, a bracketed IPv6 literal, or a name.
     *
     * The name test is a shape: reg-name characters, percent escapes, or any character
     * outside ASCII — which is what admits `exämple.de` without a table of the world's
     * top-level domains. What it refuses is a host carrying a space, a control character or
     * a delimiter that belongs to another part of the URI.
     */
    private static function isValidHost(string $host): bool
    {
        if (str_starts_with($host, '[') && str_contains($host, ']')) {
            $inner = substr($host, 1, strpos($host, ']') - 1);

            return false !== filter_var($inner, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        }

        if (false !== filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true;
        }

        return 1 === preg_match(
            '/^(?:[' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . ':]|%[A-Fa-f0-9]{2}|[^\x00-\x7F])+$/u',
            $host
        );
    }
}
