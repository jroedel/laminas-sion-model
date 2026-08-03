<?php

namespace SionModel\Service;

use Interop\Container\ContainerInterface;
use SionModel\Error\RequestContext;

/**
 * Hands out a callable that pulls the reporting services out of the container on
 * demand.
 *
 * Both consumers — the MVC ErrorListener and the static FatalErrorHandler — are
 * wired at bootstrap but must not construct anything until something has
 * actually failed: ExceptionsLogger opens a file handle when constructed, and
 * RequestContext pulls in the acting-user provider, which builds the
 * authentication service. Resolving eagerly would put both on the critical path
 * of every healthy request.
 */
class ErrorReportingResolver
{
    /**
     * @param ContainerInterface $container
     * @return callable returns [ErrorHandling, RequestContext]
     */
    public static function forContainer(ContainerInterface $container)
    {
        return static function () use ($container) {
            return [
                $container->get(ErrorHandling::class),
                $container->get(RequestContext::class),
            ];
        };
    }
}
