<?php

namespace SionModel\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Container\ContainerInterface;

/**
 * The exceptions log: every failure reaching dispatch.error or render.error,
 * rotated by month through the {monthString} placeholder in
 * sion_model.exceptions_log_path.
 *
 * Separate from the application log on purpose — see
 * docs/exception-reporting.md. This is the line-oriented record; the per-failure
 * directories under data/exceptions/ hold the detail.
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class ExceptionsLoggerFactory implements FactoryInterface
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
            (string) $config['sion_model']['exceptions_log_path']
        );

        $handler = new StreamHandler($path, Level::Debug);
        //allowInlineLineBreaks: ErrorHandling::logException() writes a multi-line
        //"Exception: … Trace: #0 …" block, and monolog's formatter would otherwise
        //flatten every stack trace onto one line. This log goes back to 2020 and
        //has to stay readable with the same eyes and the same greps.
        //ignoreEmptyContextAndExtra: keeps a bare "[] []" off the end of every line.
        $handler->setFormatter(new LineFormatter(null, null, true, true));

        return new Logger('exceptions', [$handler]);
    }
}
