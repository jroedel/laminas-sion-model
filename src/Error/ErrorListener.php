<?php

namespace SionModel\Error;

use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Throwable;

/**
 * Reports exceptions raised during dispatch or rendering.
 *
 * The reporting services are resolved through a callable rather than injected,
 * and only once something has actually failed. That laziness is not incidental:
 * ExceptionsLogger opens a write handle to the log file the moment it is
 * constructed, and RequestContext pulls in the acting-user provider, which
 * builds the authentication service. Constructing either on every healthy
 * request would be pure overhead on the 99.9% of requests that never fail —
 * which is why the listener this replaced also deferred its lookup.
 *
 * The exception is read from the event *parameter*, which is where
 * Laminas\Mvc\DispatchListener puts it and where the framework's own
 * ExceptionStrategy reads it from. The previous implementation read
 * `$event->getResult()->exception`, which only worked by accident: it depended
 * on ExceptionStrategy having already run and having stuffed the exception into
 * a ViewModel whose __get() then exposed it. Disable that strategy, reorder the
 * listeners, or return anything other than a ViewModel, and error reporting
 * would have gone silent without a word. The old path is kept as a fallback so
 * nothing regresses for a project that relied on it.
 *
 * Attached at a low priority so the framework has already built the error
 * response by the time we start writing files.
 */
class ErrorListener
{
    /**
     * Returns [ErrorHandling, RequestContext]. Invoked only on failure.
     *
     * @var callable
     */
    private $resolver;

    /**
     * Object ids of exceptions already reported in this process.
     *
     * One failure can surface twice — a dispatch error whose error page then
     * fails to render triggers render.error with the same exception — and it
     * should count as one occurrence, not two.
     *
     * @var array<int, true>
     */
    private $reported = [];

    /** @var callable[] */
    private $listeners = [];

    /**
     * @param callable $resolver returns [ErrorHandling, RequestContext]
     */
    public function __construct($resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * @param EventManagerInterface $events
     * @param int                   $priority
     * @return void
     */
    public function attach(EventManagerInterface $events, $priority = -1000)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH_ERROR, [$this, 'onError'], $priority);
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER_ERROR, [$this, 'onError'], $priority);
    }

    /**
     * @param MvcEvent $event
     * @return void
     */
    public function onError(MvcEvent $event)
    {
        try {
            $exception = $this->resolveException($event);
            if (null === $exception) {
                //a plain 404 reaches dispatch.error with no exception at all
                return;
            }
            $id = spl_object_id($exception);
            if (isset($this->reported[$id])) {
                return;
            }
            $this->reported[$id] = true;

            [$errorHandling, $requestContext] = call_user_func($this->resolver);
            $errorHandling->handle($exception, $requestContext->attributesFor($event));
        } catch (Throwable $e) {
            //reporting a failure must never turn an error page into a fatal
        }
    }

    /**
     * @param MvcEvent $event
     * @return Throwable|null
     */
    private function resolveException(MvcEvent $event)
    {
        $exception = $event->getParam('exception');
        if ($exception instanceof Throwable) {
            return $exception;
        }

        //legacy path: ExceptionStrategy's ViewModel exposes it via __get()
        $result = $event->getResult();
        if (is_object($result) && isset($result->exception) && $result->exception instanceof Throwable) {
            return $result->exception;
        }

        return null;
    }
}
