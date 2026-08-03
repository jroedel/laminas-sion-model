<?php

namespace SionModel\Error;

use Laminas\Http\PhpEnvironment\RemoteAddress;
use Laminas\Http\Request as HttpRequest;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\RouteMatch;
use SionModel\Service\ActingUserProviderInterface;
use Throwable;

/**
 * Collects the request state worth keeping alongside a failure, already
 * redacted.
 *
 * What is captured is governed by the `capture` config block, and the shipped
 * defaults are deliberately conservative: the store gets rsynced off the
 * production server and its contents get mailed, so this is the one place where
 * "more debugging detail" and "personal data spill" are the same decision. See
 * Redactor for what each mode does.
 */
class RequestContext
{
    /** @var array */
    private $capture;

    /** @var ActingUserProviderInterface|null */
    private $userProvider;

    /** @var string */
    private $appRoot;

    /** @var string|null|false false until looked up */
    private $revision = false;

    /**
     * @param string                           $appRoot
     * @param array                            $capture keys ip, identity, params
     * @param ActingUserProviderInterface|null $userProvider
     */
    public function __construct($appRoot, array $capture = [], ?ActingUserProviderInterface $userProvider = null)
    {
        $this->appRoot      = rtrim((string) $appRoot, '/');
        $this->userProvider = $userProvider;
        $this->capture      = $capture + [
            'ip'       => 'truncate',
            'identity' => 'id',
            'params'   => 'keys',
        ];
    }

    /**
     * Everything ExceptionRecord needs about the request that failed.
     *
     * @param MvcEvent $event
     * @return array keys route, controller, action, revision, context
     */
    public function attributesFor(MvcEvent $event)
    {
        $attributes = [
            'route'      => Fingerprinter::NO_ROUTE,
            'controller' => null,
            'action'     => null,
            'revision'   => $this->revision(),
            'context'    => [],
        ];

        $routeMatch = $event->getRouteMatch();
        if ($routeMatch instanceof RouteMatch) {
            $name = $routeMatch->getMatchedRouteName();
            if (null !== $name && '' !== $name) {
                $attributes['route'] = $name;
            }
            $attributes['controller'] = $this->stringOrNull($routeMatch->getParam('controller'));
            $attributes['action']     = $this->stringOrNull($routeMatch->getParam('action'));
        }
        //the controller may be known even when routing produced no match
        if (null === $attributes['controller']) {
            $attributes['controller'] = $this->stringOrNull($event->getController());
        }

        $request = $event->getRequest();
        $context = $request instanceof HttpRequest
            ? $this->httpContext($request)
            : $this->globalsContext();

        if ($routeMatch instanceof RouteMatch) {
            $params = $routeMatch->getParams();
            //controller and action are already reported above
            unset($params['controller'], $params['action']);
            if ([] !== $params) {
                //route params come from the URL, which we already record in full
                $context['route_params'] = Redactor::flatten($params);
            }
        }

        $attributes['context'] = $context;

        return $attributes;
    }

    /**
     * The same attributes for a failure with no MvcEvent to consult — a fatal
     * during bootstrap, or a shutdown handler firing after the request object
     * is long gone.
     *
     * @return array
     */
    public function attributesFromGlobals()
    {
        return [
            'route'      => Fingerprinter::NO_ROUTE,
            'controller' => null,
            'action'     => null,
            'revision'   => $this->revision(),
            'context'    => $this->globalsContext(),
        ];
    }

    /**
     * @param HttpRequest $request
     * @return array
     */
    private function httpContext(HttpRequest $request)
    {
        $context = [
            'method'     => $request->getMethod(),
            'uri'        => (string) $request->getUriString(),
            'referer'    => $this->headerValue($request, 'Referer'),
            'user_agent' => $this->headerValue($request, 'User-Agent'),
            'client'     => Redactor::ip($this->clientAddress(), $this->capture['ip']),
            'user_id'    => $this->userId(),
        ];

        $query = $request->getQuery();
        $post  = $request->getPost();
        $context['query'] = Redactor::params(
            is_object($query) && method_exists($query, 'toArray') ? $query->toArray() : (array) $query,
            $this->capture['params']
        );
        $context['post'] = Redactor::params(
            is_object($post) && method_exists($post, 'toArray') ? $post->toArray() : (array) $post,
            $this->capture['params']
        );

        return $context;
    }

    /**
     * @return array
     */
    private function globalsContext()
    {
        $server = isset($_SERVER) && is_array($_SERVER) ? $_SERVER : [];
        $scheme = ! empty($server['HTTPS']) && 'off' !== $server['HTTPS'] ? 'https' : 'http';
        $host   = isset($server['HTTP_HOST']) ? $server['HTTP_HOST'] : null;
        $path   = isset($server['REQUEST_URI']) ? $server['REQUEST_URI'] : null;

        return [
            'method'     => isset($server['REQUEST_METHOD']) ? $server['REQUEST_METHOD'] : php_sapi_name(),
            'uri'        => null === $host || null === $path ? null : $scheme . '://' . $host . $path,
            'referer'    => isset($server['HTTP_REFERER']) ? $server['HTTP_REFERER'] : null,
            'user_agent' => isset($server['HTTP_USER_AGENT']) ? $server['HTTP_USER_AGENT'] : null,
            'client'     => Redactor::ip($this->clientAddress(), $this->capture['ip']),
            'user_id'    => $this->userId(),
            'query'      => Redactor::params(isset($_GET) && is_array($_GET) ? $_GET : [], $this->capture['params']),
            'post'       => Redactor::params(isset($_POST) && is_array($_POST) ? $_POST : [], $this->capture['params']),
        ];
    }

    /**
     * @return string|null
     */
    private function clientAddress()
    {
        if ('none' === $this->capture['ip']) {
            return null;
        }
        try {
            $address = (new RemoteAddress())->getIpAddress();
            return '' === $address ? null : $address;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Resolved at call time, never at construction: an id captured while the
     * container is still wiring itself is what produced the historic
     * UserTable/AuthService dependency cycle.
     *
     * @return int|null
     */
    private function userId()
    {
        if ('id' !== $this->capture['identity'] || null === $this->userProvider) {
            return null;
        }
        try {
            return $this->userProvider->getActingUserId();
        } catch (Throwable $e) {
            //identity resolution is exactly the kind of thing that may be
            //broken when we get here; never let it mask the real exception
            return null;
        }
    }

    /**
     * @param HttpRequest $request
     * @param string      $name
     * @return string|null
     */
    private function headerValue(HttpRequest $request, $name)
    {
        $header = $request->getHeader($name);
        if (false === $header) {
            return null;
        }
        $value = $header->getFieldValue();
        return '' === $value ? null : $value;
    }

    /**
     * Which deployed code produced this failure — phploy leaves the revision it
     * uploaded at the application root.
     *
     * @return string|null
     */
    public function revision()
    {
        if (false !== $this->revision) {
            return $this->revision;
        }
        $this->revision = null;
        $raw = @file_get_contents($this->appRoot . '/.revision');
        if (false !== $raw) {
            $raw = trim($raw);
            if ('' !== $raw) {
                $this->revision = substr($raw, 0, 12);
            }
        }
        return $this->revision;
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private function stringOrNull($value)
    {
        if (! is_string($value) || '' === $value) {
            return null;
        }
        return $value;
    }
}
