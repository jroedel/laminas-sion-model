<?php

namespace SionModel\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * Builds the application-wide mail transport.
 *
 * It reads the top-level `smtp_options` block — the one already present in
 * config/autoload/local.php and, in the container, in docker/local.docker.php,
 * pointing at Mailpit. Using that block means every mail path is verifiable
 * locally rather than only in production.
 *
 * Registered as 'SionModel\MailTransport'. The exception notifier consumes it
 * through the 'SionModel\ExceptionMailTransport' alias, so a project can still
 * point exception mail at a different transport without touching the notifier.
 */
class MailTransportFactory implements FactoryInterface
{
    /**
     * A mail host that has stopped answering must not stall a request for the
     * 60 seconds of default_socket_timeout: sign-in links are sent during the
     * request, and PHP execution is itself capped at 60s in the capsule.
     */
    private const TIMEOUT_SECONDS = 30;

    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = (array) $container->get('Config');
        $smtp   = isset($config['smtp_options']) && is_array($config['smtp_options'])
            ? $config['smtp_options']
            : [];

        $host = $this->pick($smtp, ['server', 'host']);
        if (null === $host) {
            //no SMTP configured: hand off to the local MTA
            return new SendmailTransport();
        }

        //implicit TLS (smtps) only when explicitly asked for; STARTTLS is
        //negotiated automatically whenever the server offers it, which covers
        //the usual 'ssl' => 'tls' on port 587
        $tls = 'ssl' === $this->pick($smtp, ['ssl']) ? true : null;

        $transport = new EsmtpTransport(
            $host,
            isset($smtp['port']) ? (int) $smtp['port'] : 25,
            $tls
        );
        $transport->getStream()->setTimeout(self::TIMEOUT_SECONDS);

        $username = $this->pick($smtp, ['username']);
        $password = $this->pick($smtp, ['password']);
        //authenticate only when there is a credential to authenticate with;
        //Mailpit in the capsule takes unauthenticated mail on port 1025
        if (null !== $username && null !== $password) {
            $transport->setUsername($username);
            $transport->setPassword($password);
        }

        return $transport;
    }

    /**
     * First non-empty value among the given keys.
     *
     * @param array    $config
     * @param string[] $keys
     * @return string|null
     */
    private function pick(array $config, array $keys)
    {
        foreach ($keys as $key) {
            if (! isset($config[$key])) {
                continue;
            }
            $value = $config[$key];
            if (is_string($value) && '' === trim($value)) {
                continue;
            }
            if (null === $value) {
                continue;
            }
            return $value;
        }
        return null;
    }
}
