<?php

declare(strict_types=1);

namespace SionModel\Validator;

use Laminas\Session\Container as SessionContainer;

use function explode;
use function is_string;
use function md5;
use function random_bytes;
use function sprintf;
use function strtr;

/**
 * The submitted token matches one this session issued.
 *
 * 35 specification entries — one per form, through `SionModel\Form\CsrfSpec` — and the only
 * thing standing between the site and an unprotected form.
 *
 * ## The session container name is a literal and must stay one
 *
 * laminas derived it from its own class name: `str_replace('\\', '_', self::class)` gives
 * `Laminas_Validator_Csrf`, and every token in every open session is filed under
 * `Laminas_Validator_Csrf_salt_<form>`. Deriving it from **this** class would file the next
 * release's tokens somewhere else, and every form a visitor had open at the moment of the
 * deploy would be rejected on submit — with the message "The form submitted did not
 * originate from the expected site", which is exactly what a person seeing it would not
 * report as a deploy problem.
 *
 * So the string is frozen here, for the same reason `Laminas_Auth` is frozen in JUser and
 * `FlashMessenger` in `SionModel\Messaging\FlashMessages`. It is a storage key, not a name.
 *
 * ## The shape of a hash
 *
 * `<token>-<tokenId>`. The id indexes a list in the session, so several forms open at once
 * each keep their own token and submitting one does not invalidate the others. `timeout`
 * expires the container: five forms pass 900 seconds, the rest take laminas' 300.
 *
 * `laminas-session` is still what holds it. That is iteration B's problem, not this one's;
 * what matters here is that the container name and the stored shape do not move when it
 * changes.
 */
final class Csrf extends AbstractValidator
{
    public const NOT_SAME = 'notSame';

    /**
     * The session container prefix, frozen at the value laminas computed.
     *
     * Do not derive this from `self::class`. See the class docblock: it is what a live
     * session's tokens are already filed under.
     */
    private const CONTAINER_PREFIX = 'Laminas_Validator_Csrf';

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::NOT_SAME => 'The form submitted did not originate from the expected site',
    ];

    private ?string $hash = null;

    private string $name = 'csrf';

    private string $salt = 'salt';

    private ?SessionContainer $session = null;

    private ?int $timeout = 300;

    public function setName(mixed $name): static
    {
        $this->name = (string) $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setSalt(mixed $salt): static
    {
        $this->salt = (string) $salt;

        return $this;
    }

    public function getSalt(): string
    {
        return $this->salt;
    }

    public function setTimeout(mixed $timeout): static
    {
        $this->timeout = null === $timeout ? null : (int) $timeout;

        return $this;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function setSession(SessionContainer $session): static
    {
        $this->session = $session;

        if (null !== $this->hash) {
            $this->registerToken();
        }

        return $this;
    }

    public function getSession(): SessionContainer
    {
        return $this->session ??= new SessionContainer($this->getSessionName());
    }

    public function getSessionName(): string
    {
        return self::CONTAINER_PREFIX . '_'
            . $this->getSalt() . '_'
            . strtr($this->getName(), ['[' => '_', ']' => '']);
    }

    /** The token to render, minted on first ask. */
    public function getHash(bool $regenerate = false): string
    {
        if (null === $this->hash || $regenerate) {
            $this->generateHash();
        }

        return (string) $this->hash;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     */
    public function isValid($value, $context = null): bool
    {
        if (! is_string($value)) {
            //No message, and laminas set none either: a non-string token is not a thing a
            //browser produces, so there is nobody to tell.
            return false;
        }

        $this->setValue($value);

        $tokenId  = self::tokenIdFromHash($value);
        $expected = $this->storedHashFor($tokenId);

        $submitted = self::tokenFromHash($value);
        $known     = self::tokenFromHash($expected);

        if (null === $submitted || null === $known || $submitted !== $known) {
            $this->error(self::NOT_SAME);

            return false;
        }

        return true;
    }

    private function generateHash(): void
    {
        $token   = md5($this->getSalt() . random_bytes(32) . $this->getName());
        $tokenId = md5(random_bytes(32));

        $this->hash = sprintf('%s-%s', $token, $tokenId);
        $this->setValue($this->hash);
        $this->registerToken();
    }

    private function registerToken(): void
    {
        $session = $this->getSession();

        if (null !== $this->timeout) {
            $session->setExpirationSeconds($this->timeout);
        }

        $hash    = $this->getHash();
        $tokenId = self::tokenIdFromHash($hash);

        /** @var array<string, string> $list */
        $list = $session->tokenList ?? [];

        if (null !== $tokenId) {
            $list[$tokenId] = (string) self::tokenFromHash($hash);
        }

        $session->tokenList = $list;
        //Kept for a session written by the release being replaced, which read `hash` when
        //a submission carried no token id.
        $session->hash = $hash;
    }

    private function storedHashFor(?string $tokenId): ?string
    {
        $session = $this->getSession();

        if (null === $tokenId) {
            return is_string($session->hash) ? $session->hash : null;
        }

        /** @var array<string, string> $list */
        $list = $session->tokenList ?? [];

        return isset($list[$tokenId]) ? sprintf('%s-%s', $list[$tokenId], $tokenId) : null;
    }

    private static function tokenFromHash(?string $hash): ?string
    {
        if (null === $hash) {
            return null;
        }

        $parts = explode('-', $hash);

        return '' === $parts[0] ? null : $parts[0];
    }

    private static function tokenIdFromHash(string $hash): ?string
    {
        $parts = explode('-', $hash);

        return $parts[1] ?? null;
    }
}
