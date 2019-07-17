<?php
/**
 * SionModel Module
 */

namespace SionModel\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use SionModel\Mailing\Mailer;

/**
 * Factory responsible of priming the Mailer service
 *
 * @author Jeff Ro <jeff.roedel.isp@gmail.com>
 */
class MailerFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $translator = $container->get('translator');
        $mailService = $container->get('acmailer.mailservice.default');
        $config = $container->get('Config');

        $mailer = new Mailer($mailService, $translator, $config);
        return $mailer;
    }
}
