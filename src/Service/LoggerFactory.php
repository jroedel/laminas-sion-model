<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\Log\Logger;
use Laminas\Log\Writer\Mail;
use Laminas\Log\Writer\Stream;
use Laminas\Mail\Message;
use Laminas\Mail\Transport\TransportInterface;
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
        $writer    = new Stream(streamOrUrl: $path, filePermissions: 0664);
        $logger    = new Logger();
        $logger->addWriter($writer);

        //email certain messages
        $mail = new Message();
        $mail->setSender('webmaster@schoenstatt-fathers.link')
            ->addTo('database@schoenstatt-fathers.link');
        $transport  = $container->get(TransportInterface::class);
        $mailWriter = new Mail([
            'mail'                 => $mail,
            'transport'            => $transport,
            'subject_prepend_text' => 'schoenstatt-fathers.link Logger message',
        ]);
        //if more serious than ERR, email
        $mailWriter->addFilter(Logger::ERR);
        $logger->addWriter($mailWriter);

        return $logger;
    }
}
