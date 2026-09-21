<?php

declare(strict_types=1);

namespace SionModel\Messaging;

use SionModel\Session\SessionBagInterface;
use SionModel\Session\Sessions;
use SplQueue;

use function is_array;
use function iterator_to_array;

/**
 * Messages that survive one redirect: written by the request that redirects, read by
 * the page rendered next in the same session.
 *
 * The storage is deliberately the one laminas-mvc's `FlashMessenger` plugin used — the
 * session namespace `FlashMessenger`, one `SplQueue` per severity, one hop of expiration
 * set on the namespace the first time something is written — so a message written by a
 * release that still ran the plugin renders under the release that runs this, and the
 * other way round if a deploy is rolled back. The `SplQueue` is part of that format and
 * not an implementation choice: it is what is already sitting in every live session. The five namespaces are the
 * plugin's constants, kept as literals: `JUser\Host\Severity` and
 * `JTranslate\Host\Severity` carry the same five strings by value.
 *
 * **One instance per request.** The first use, read or write, moves every namespace out
 * of the session into this object and unsets it from the container, exactly as the
 * plugin did. A second instance doing the same after the first has written takes the
 * first's messages out of the session and drops them at the end of the request, so two
 * messages become one. Hosts memoise; schoenstatt.link measured the loss on sign-in
 * redemption before it did.
 *
 * A message is a string or a `JTranslate\I18n\TranslatableMessage`; this class stores
 * either and renders neither. Rendering, and translation, is the host's.
 */
final class FlashMessages
{
    public const CONTAINER = 'FlashMessenger';

    public const NAMESPACE_DEFAULT = 'default';
    public const NAMESPACE_SUCCESS = 'success';
    public const NAMESPACE_WARNING = 'warning';
    public const NAMESPACE_ERROR   = 'error';
    public const NAMESPACE_INFO    = 'info';

    private ?SessionBagInterface $bag = null;

    /** @var array<string, list<mixed>>|null what the previous request left; null until read */
    private ?array $received = null;

    private bool $written = false;

    /**
     * @param SessionBagInterface|null $session null means the default session, which is
     *        what the plugin's container did with no manager
     */
    public function __construct(private readonly ?SessionBagInterface $session = null)
    {
    }

    /**
     * Queue a message for the next request.
     *
     * @param string $namespace one of the NAMESPACE_* constants
     */
    public function add(string $namespace, mixed $message): void
    {
        $bag = $this->bag();
        $this->receive();
        if (! $this->written) {
            //one hop: readable by the next request and gone after it, whether or not it
            //was read. Set once per request, as the plugin did.
            $bag->expireAfterHops(1);
            $this->written = true;
        }

        $queue = $bag->get($namespace);
        if (! $queue instanceof SplQueue) {
            $queue = new SplQueue();
        }
        $queue->push($message);
        //Written back rather than mutated in place: the bag reads the session on every
        //call, so an object pulled out of it is a copy as far as storage is concerned.
        $bag->set($namespace, $queue);
    }

    /**
     * The previous request's messages in one namespace, in the order they were added.
     *
     * @return list<mixed>
     */
    public function messages(string $namespace): array
    {
        $this->receive();

        return $this->received[$namespace] ?? [];
    }

    /**
     * The previous request's messages, keyed by namespace.
     *
     * @return array<string, list<mixed>>
     */
    public function all(): array
    {
        $this->receive();

        return $this->received ?? [];
    }

    /**
     * Move everything the previous request left out of the session, once.
     */
    private function receive(): void
    {
        if (null !== $this->received) {
            return;
        }
        $this->received = [];
        $bag            = $this->bag();
        $namespaces     = [];
        foreach ($bag->all() as $namespace => $messages) {
            $namespace = (string) $namespace;
            if ($messages instanceof SplQueue) {
                $this->received[$namespace] = iterator_to_array($messages, false);
            } elseif (is_array($messages)) {
                $this->received[$namespace] = array_values($messages);
            } else {
                $this->received[$namespace] = [$messages];
            }
            $namespaces[] = $namespace;
        }
        foreach ($namespaces as $namespace) {
            $bag->remove($namespace);
        }
    }

    private function bag(): SessionBagInterface
    {
        return $this->bag ??= $this->session ?? Sessions::default()->bag(self::CONTAINER);
    }
}
