<?php

declare(strict_types=1);

namespace SionModel\Service;

use Laminas\Log\Logger;
use Laminas\Log\Writer\Mail;
use Laminas\Log\Writer\Stream;
use Laminas\Mail\Message;
use Laminas\Mail\Transport\TransportInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Validator\EmailAddress;
use Psr\Container\ContainerInterface;
use Webmozart\Assert\Assert;

use function date;
use function str_replace;

class LoggerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('Config');
        Assert::keyExists($config, 'sion_model');
        Assert::keyExists($config['sion_model'], 'application_log_path');
        $path      = $config['sion_model']['application_log_path'];
        $yearMonth = date('Y-m');
        $path      = str_replace('{monthString}', $yearMonth, $path);
        $writer    = new Stream(streamOrUrl: $path, filePermissions: 0664);
        Assert::keyExists($config['sion_model'], 'logger_to_file_minimum_level');
        $fileLoggerMinimumLevel = $config['sion_model']['logger_to_file_minimum_level'];
        Assert::integer($fileLoggerMinimumLevel);
        Assert::greaterThanEq($fileLoggerMinimumLevel, 0);
        Assert::lessThanEq($fileLoggerMinimumLevel, 7);
        $writer->addFilter($fileLoggerMinimumLevel);
        $logger = new Logger();
        $logger->addWriter($writer);

        //email certain messages
        Assert::keyExists($config['sion_model'], 'logger_to_email_minimum_level');
        Assert::keyExists($config['sion_model'], 'logger_to_email_to_address');
        Assert::keyExists($config['sion_model'], 'logger_to_email_sender_address');
        $emailLoggerMinimumLevel = $config['sion_model']['logger_to_email_minimum_level'];
        Assert::integer($emailLoggerMinimumLevel);
        Assert::greaterThanEq($emailLoggerMinimumLevel, 0);
        Assert::lessThanEq($emailLoggerMinimumLevel, 7);

        $toAddress      = $config['sion_model']['logger_to_email_to_address'];
        $senderAddress  = $config['sion_model']['logger_to_email_sender_address'];
        $emailValidator = new EmailAddress();
        Assert::true($emailValidator->isValid($toAddress));
        Assert::true($emailValidator->isValid($senderAddress));
        $mail = new Message();
        $mail->setSender($senderAddress)
            ->addTo($toAddress);
        $transport = $container->get(TransportInterface::class);
        $subject   = $config['sion_model']['logger_to_email_subject'] ?? 'Logger message';
        Assert::stringNotEmpty($subject);
        $mailWriter = new Mail([
            'mail'                 => $mail,
            'transport'            => $transport,
            'subject_prepend_text' => $subject,
        ]);
        //if more serious than the requested minimum level, email
        $mailWriter->addFilter($emailLoggerMinimumLevel);
        $logger->addWriter($mailWriter);

        return $logger;
    }
}
