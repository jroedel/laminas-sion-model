<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\Mail\Transport\TransportInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use SionModel\Db\Model\MailingsTable;
use SionModel\Mailing\Mailer;
use Webmozart\Assert\Assert;

class MailerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $mailTransport = $container->get(TransportInterface::class);
        $mailingsTable = $container->get(MailingsTable::class);
        $logger        = $container->get('SionModel\Logger');
        $config        = $container->get('Config');
        Assert::keyExists($config, 'zend_mail_transport_options');
        Assert::keyExists($config['zend_mail_transport_options'], 'connection_config');
        Assert::keyExists($config['zend_mail_transport_options']['connection_config'], 'username');
        Assert::email($config['zend_mail_transport_options']['connection_config']['username']);

        return new Mailer(
            mailTransport: $mailTransport,
            mailingsTable: $mailingsTable,
            logger: $logger,
//            config: $config,
            defaultFromAddress: $config['zend_mail_transport_options']['connection_config']['username']
        );
    }
}
