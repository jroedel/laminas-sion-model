<?php

declare(strict_types=1);

namespace SionModel\Messaging;

use Laminas\Session\Container;
use Laminas\Session\ManagerInterface;
use SplQueue;

use function is_array;
use function iterator_to_array;

/**
 * Messages that survive one redirect: written by the request that redirects, read by
 * the page rendered next in the same session.
 *
 * The storage is deliberately the one laminas-mvc's `FlashMessenger` plugin used — the
 * session container named `FlashMessenger`, one `SplQueue` per namespace, one hop of
 * expiration set on the container the first time something is written — so a message
 * written by a release that still ran the plugin renders under the release that runs
 * this, and the other way round if a deploy is rolled back. The five namespaces are the
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

    private ?Container $container = null;

    /** @var array<string, list<mixed>>|null what the previous request left; null until read */
    private ?array $received = null;

    private bool $written = false;

    /**
     * @param ManagerInterface|null $manager null means the session container's default
     *        manager, which is what the plugin used
     */
    public function __construct(private readonly ?ManagerInterface $manager = null)
    {
    }

    /**
     * Queue a message for the next request.
     *
     * @param string $namespace one of the NAMESPACE_* constants
     */
    public function add(string $namespace, mixed $message): void
    {
        $container = $this->container();
        $this->receive();
        if (! $this->written) {
            //one hop: readable by the next request and gone after it, whether or not it
            //was read. Set once per request, as the plugin did.
            $container->setExpirationHops(1, null);
            $this->written = true;
        }
        if (! isset($container->{$namespace}) || ! $container->{$namespace} instanceof SplQueue) {
            $container->{$namespace} = new SplQueue();
        }
        $container->{$namespace}->push($message);
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
        $container      = $this->container();
        $namespaces     = [];
        foreach ($container as $namespace => $messages) {
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
            unset($container->{$namespace});
        }
    }

    private function container(): Container
    {
        return $this->container ??= new Container(self::CONTAINER, $this->manager);
    }
}
