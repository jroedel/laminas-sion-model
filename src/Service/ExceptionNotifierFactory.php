<?php

namespace SionModel\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use SionModel\Error\Config;
use SionModel\Error\ExceptionNotifier;
use SionModel\Error\ExceptionStore;
use SionModel\Error\NotificationGate;

/**
 * Factory responsible of priming the ExceptionNotifier service
 */
class ExceptionNotifierFactory implements FactoryInterface
{
    /** Service name of the transport, so a project can alias it elsewhere. */
    public const TRANSPORT_SERVICE = 'SionModel\ExceptionMailTransport';

    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config  = (array) $container->get('Config');
        $options = Config::notifications($config);

        $gate = new NotificationGate(
            (bool) $options['enabled'],
            (array) $options['ignore_classes'],
            (array) $options['spike_counts']
        );

        return new ExceptionNotifier(
            $container->get(self::TRANSPORT_SERVICE),
            $gate,
            $container->get(ExceptionStore::class),
            [
                'to'                  => $options['to'],
                'from'                => $this->sender($options, $config),
                'from_name'           => $options['from_name'],
                'subject_prefix'      => $options['subject_prefix'],
                'max_emails_per_hour' => (int) $options['max_emails_per_hour'],
                'breaker_seconds'     => (int) $options['breaker_seconds'],
            ]
        );
    }

    /**
     * The envelope sender. An explicit setting wins; otherwise reuse the SMTP
     * account, which is the address the mail server will actually accept.
     *
     * @param array $options
     * @param array $config
     * @return string
     */
    private function sender(array $options, array $config)
    {
        if (isset($options['from']) && '' !== $options['from']) {
            return $options['from'];
        }
        if (
            isset($config['smtp_options']['username'])
            && false !== strpos((string) $config['smtp_options']['username'], '@')
        ) {
            return $config['smtp_options']['username'];
        }
        $host = gethostname();
        return 'webmaster@' . (false === $host ? 'localhost' : $host);
    }
}
