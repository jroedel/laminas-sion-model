<?php

namespace SionModel\Service;

use Interop\Container\ContainerInterface;
use Laminas\Mail\Transport\Sendmail;
use Laminas\Mail\Transport\Smtp;
use Laminas\Mail\Transport\SmtpOptions;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Builds the transport used for exception notifications.
 *
 * Registered as its own service so a project can alias it to something else
 * without touching the notifier.
 *
 * It reads the top-level `smtp_options` block — the one already present in
 * config/autoload/local.php and, in the container, in docker/local.docker.php,
 * pointing at Mailpit. Using that block means the whole notification path is
 * verifiable locally rather than only in production.
 *
 * laminas-mail is used directly rather than through the application's other mail
 * paths on purpose: AcMailer is configured in mail.global.php but is not
 * installed, so `acmailer.mailservice.default` does not exist; and Swiftmailer,
 * which JUser still uses, is abandoned and on its way out. laminas-mail is a
 * maintained dependency the application already requires.
 */
class ExceptionMailTransportFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = (array) $container->get('Config');
        $smtp   = isset($config['smtp_options']) && is_array($config['smtp_options'])
            ? $config['smtp_options']
            : [];

        $host = $this->pick($smtp, ['server', 'host']);
        if (null === $host) {
            //no SMTP configured: hand off to the local MTA
            return new Sendmail();
        }

        $username = $this->pick($smtp, ['username']);
        $password = $this->pick($smtp, ['password']);
        //authenticate only when there is a credential to authenticate with;
        //Mailpit in the capsule takes unauthenticated mail on port 1025
        $useAuth = null !== $username && null !== $password;

        $connectionConfig = [];
        if ($useAuth) {
            $connectionConfig['username'] = $username;
            $connectionConfig['password'] = $password;
        }
        $ssl = $this->pick($smtp, ['ssl']);
        if (null !== $ssl) {
            $connectionConfig['ssl'] = $ssl;
        }

        return new Smtp(new SmtpOptions([
            'name'              => $host,
            'host'              => $host,
            'port'              => isset($smtp['port']) ? (int) $smtp['port'] : 25,
            'connection_class'  => $useAuth
                ? (isset($smtp['connection_class']) ? $smtp['connection_class'] : 'login')
                : 'smtp',
            'connection_config' => $connectionConfig,
        ]));
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
