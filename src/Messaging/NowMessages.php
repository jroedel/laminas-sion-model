<?php

declare(strict_types=1);

namespace SionModel\Messaging;

/**
 * Messages for the response being rendered now — the counterpart of
 * {@see FlashMessages} for a page that reports without redirecting (a search that found
 * nothing, a form that failed validation).
 *
 * Plain per-request memory, nothing in the session; the host builds one per request and
 * hands the same instance to whoever writes and to whatever renders. Namespaces are the
 * {@see FlashMessages} constants. A message is a string or a
 * `JTranslate\I18n\TranslatableMessage`; this class renders neither.
 */
final class NowMessages
{
    /** @var array<string, list<mixed>> */
    private array $messages = [];

    public function add(string $namespace, mixed $message): void
    {
        $this->messages[$namespace][] = $message;
    }

    /** @return list<mixed> */
    public function messages(string $namespace): array
    {
        return $this->messages[$namespace] ?? [];
    }

    /** @return array<string, list<mixed>> */
    public function all(): array
    {
        return $this->messages;
    }
}
