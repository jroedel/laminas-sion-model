<?php

namespace SionModel\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream as LogWriterStream;

/**
 * Factory responsible of priming the ZendLog service
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class ExceptionsLoggerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        $path = $config['sion_model']['exceptions_log_path'];
        $yearMonth = date('Y-m');
        $path = str_replace('{monthString}', $yearMonth, $path);
        $log = new Logger();
        $writer = new LogWriterStream($path);
        $log->addWriter($writer);

        return $log;
    }
}
