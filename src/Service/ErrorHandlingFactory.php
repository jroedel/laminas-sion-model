<?php

namespace SionModel\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Factory responsible of priming the ZendLog service
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class ErrorHandlingFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $logger = $container->get('ExceptionsLogger');
        $service = new ErrorHandling($logger);
        return $service;
    }
}
