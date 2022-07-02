<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\Log\Logger;
use Laminas\Log\Writer\Stream;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use function date;
use function str_replace;

class LoggerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config    = $container->get('Config');
        $path      = $config['sion_model']['application_log_path'];
        $yearMonth = date('Y-m');
        $path      = str_replace('{monthString}', $yearMonth, $path);
        $writer    = new Stream($path);
        $logger    = new Logger();
        $logger->addWriter($writer);
        return $logger;
    }
}
