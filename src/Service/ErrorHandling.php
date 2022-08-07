<?php

declare(strict_types=1);

namespace SionModel\Service;

use Exception;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Log\LoggerInterface;
use Laminas\Stdlib\RequestInterface;
use Laminas\Uri\Http;

use function implode;
use function is_string;

class ErrorHandling
{
    public function __construct(protected LoggerInterface $logger)
    {
    }

    public function logException(Exception $e, RequestInterface $request, array $roles, ?string $identity): void
    {
        $trace    = $e->getTraceAsString();
        $i        = 1;
        $messages = [];
        do {
            $messages[] = $i++ . ": " . $e->getMessage();
        } while ($e = $e->getPrevious());
        $log = '';
        if ($request instanceof Request) {
            $uri = $request->getUriString();
            if ($uri instanceof Http) {
                $uri = $uri->toString();
            }
            if (is_string($uri)) {
                $log .= $uri . "\n";
            }
        }

        if (! empty($identity)) {
            $log .= "Identity: $identity\n";
        }
        try {
            $log .= "Roles: " . implode(', ', $roles) . "\n";
        } catch (Exception) {
        }

        $log .= "Exception:\n" . implode("\n", $messages);
        $log .= "\nTrace:\n" . $trace;

        $this->logger->err($log);
    }
}
