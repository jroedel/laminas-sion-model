<?php

declare(strict_types=1);

namespace SionModel\Session;

use function array_key_exists;

/**
 * One namespace, read and written through {@see PhpSession}.
 *
 * Deliberately holds no copy of the data: every call reads `$_SESSION` back through the
 * session, so a value written by one part of a request is visible to the next. laminas'
 * `Container` was an `ArrayObject` *over* the storage for the same reason.
 */
final class Bag implements SessionBagInterface
{
    public function __construct(
        private readonly PhpSession $session,
        private readonly string $namespace,
    ) {
    }

    public function get(string $key): mixed
    {
        $data = $this->session->namespaceData($this->namespace);

        return array_key_exists($key, $data) ? $data[$key] : null;
    }

    public function set(string $key, mixed $value): void
    {
        $data       = $this->session->namespaceData($this->namespace);
        $data[$key] = $value;
        $this->session->writeNamespace($this->namespace, $data);
    }

    public function remove(string $key): void
    {
        $data = $this->session->namespaceData($this->namespace);
        unset($data[$key]);
        $this->session->writeNamespace($this->namespace, $data);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->session->namespaceData($this->namespace);
    }

    public function expireAfterHops(int $hops): void
    {
        $metadata = $this->session->namespaceMetadata($this->namespace);
        //`ts` is this request's access time: the hop is spent by a *later* request, never
        //by this one. Writing `time()` here instead would expire the message before the
        //redirect it was written for.
        $metadata['EXPIRE_HOPS'] = ['hops' => $hops, 'ts' => $this->session->accessTime()];
        $this->session->writeNamespaceMetadata($this->namespace, $metadata);
    }

    public function expireAfterSeconds(int $seconds): void
    {
        $metadata           = $this->session->namespaceMetadata($this->namespace);
        $metadata['EXPIRE'] = (int) $this->session->accessTime() + $seconds;
        $this->session->writeNamespaceMetadata($this->namespace, $metadata);
    }
}
