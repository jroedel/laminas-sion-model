<?php

namespace SionModel\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use SionModel\Error\ExceptionNotifier;
use SionModel\Error\ExceptionStore;
use SionModel\Error\Fingerprinter;
use Throwable;

/**
 * Factory responsible of priming the ErrorHandling service
 *
 * @author Jeff Roedel <jeff.roedel@schoenstatt-fathers.org>
 */
class ErrorHandlingFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $logger = $container->get('ExceptionsLogger');

        //the recorder is best-effort: this service is fetched while the
        //application is already failing, so a container that cannot build the
        //store or the mail transport must still hand back something that can
        //write the log line we have always written
        return new ErrorHandling(
            $logger,
            $this->optional($container, Fingerprinter::class),
            $this->optional($container, ExceptionStore::class),
            $this->optional($container, ExceptionNotifier::class)
        );
    }

    /**
     * @param ContainerInterface $container
     * @param string             $service
     * @return mixed|null
     */
    private function optional(ContainerInterface $container, $service)
    {
        if (! $container->has($service)) {
            return null;
        }
        try {
            return $container->get($service);
        } catch (Throwable $e) {
            return null;
        }
    }
}
