<?php

declare(strict_types=1);

namespace SionModel\Service;

use Exception;
use Laminas\Log\LoggerInterface;

use function implode;

class ErrorHandling
{
    public function __construct(protected LoggerInterface $logger)
    {
    }

    public function logException(Exception $e): void
    {
        $trace    = $e->getTraceAsString();
        $i        = 1;
        $messages = [];
        do {
            $messages[] = $i++ . ": " . $e->getMessage();
        } while ($e = $e->getPrevious());

        $log  = "Exception:\n" . implode("\n", $messages);
        $log .= "\nTrace:\n" . $trace;

        $this->logger->err($log);
    }
}
