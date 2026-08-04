<?php

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Container\ContainerInterface;

/**
 * The general application log, rotated by month through the {monthString}
 * placeholder in sion_model.application_log_path.
 *
 * Level::Debug because that is what laminas-log did: a Logger with a Stream
 * writer and no priority filter wrote every event, and SionCacheTrait's
 * per-cache-write debug() calls have always landed in this file. Raising the
 * threshold is a real decision about log volume, not part of the port.
 */
class LoggerFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        $path = str_replace(
            '{monthString}',
            date('Y-m'),
            (string) $config['sion_model']['application_log_path']
        );

        $handler = new StreamHandler($path, Level::Debug);
        //same formatter settings as the exceptions log, so both files read alike:
        //multi-line messages survive, and lines carrying no context lose the
        //trailing "[] []".
        $handler->setFormatter(new LineFormatter(null, null, true, true));

        return new Logger('application', [$handler]);
    }
}
