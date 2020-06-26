<?php

/**
 * BjyAuthorize Module (https://github.com/bjyoungblood/BjyAuthorize)
 *
 * @link https://github.com/bjyoungblood/BjyAuthorize for the canonical source repository
 * @license http://framework.zend.com/license/new-bsd New BSD License
 */

namespace SionModel\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\Log\Logger;

class LoggerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        $path = $config['sion_model']['application_log_path'];
        $yearMonth = date('Y-m');
        $path = str_replace('{monthString}', $yearMonth, $path);
        $writer = new \Zend\Log\Writer\Stream($path);
        $logger = new Logger();
        $logger->addWriter($writer);
        return $logger;
    }
}
