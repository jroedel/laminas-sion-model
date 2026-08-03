<?php

namespace SionModel\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use SionModel\Error\ErrorListener;

/**
 * Factory responsible of priming the ErrorListener service
 */
class ErrorListenerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        //a resolver, not the services themselves: the listener is constructed at
        //bootstrap on every request, and building the reporting stack eagerly
        //would open a log file handle and construct the authentication service
        //on every healthy page view. See ErrorListener.
        return new ErrorListener(ErrorReportingResolver::forContainer($container));
    }
}
