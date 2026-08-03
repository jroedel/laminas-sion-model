<?php

namespace SionModel\Service;

use SionModel\Error\ExceptionNotifier;
use SionModel\Error\ExceptionRecord;
use SionModel\Error\ExceptionStore;
use SionModel\Error\Fingerprinter;
use Throwable;

/**
 * Turns a thrown exception into a log line, a durable record, and — the first
 * time that particular failure is seen — an email.
 *
 * The three steps are ordered by how much we trust them. Logging happens first
 * and unconditionally, so a bug in the recorder or the mailer can never cost us
 * the log line we have always had. Recording and notifying are each wrapped
 * independently: this code runs while the application is already returning a
 * 500, and an exception raised in here would replace a handled error page with
 * an unhandled fatal.
 *
 * The recorder dependencies are optional so that a project sharing SionModel
 * without the exception_notifications config keeps the historic log-only
 * behaviour.
 */
class ErrorHandling
{
    /** @var \Laminas\Log\LoggerInterface */
    protected $logger;

    /** @var Fingerprinter|null */
    protected $fingerprinter;

    /** @var ExceptionStore|null */
    protected $store;

    /** @var ExceptionNotifier|null */
    protected $notifier;

    /**
     * @param \Laminas\Log\LoggerInterface $logger
     * @param Fingerprinter|null           $fingerprinter
     * @param ExceptionStore|null          $store
     * @param ExceptionNotifier|null       $notifier
     */
    public function __construct(
        $logger,
        ?Fingerprinter $fingerprinter = null,
        ?ExceptionStore $store = null,
        ?ExceptionNotifier $notifier = null
    ) {
        $this->logger        = $logger;
        $this->fingerprinter = $fingerprinter;
        $this->store         = $store;
        $this->notifier      = $notifier;
    }

    /**
     * Append the exception to the exceptions log.
     *
     * The output format is unchanged from the original implementation on
     * purpose: data/logs/exceptions_*.log goes back to 2020 and stays readable
     * with the same eyes and the same greps.
     *
     * @param Throwable $e
     * @return void
     */
    public function logException($e)
    {
        $trace = $e->getTraceAsString();
        $i = 1;
        $messages = [];
        do {
            $messages[] = $i++ . ": " . $e->getMessage();
        } while ($e = $e->getPrevious());

        $log = "Exception:\n" . implode("\n", $messages);
        $log .= "\nTrace:\n" . $trace;

        $this->logger->err($log);
    }

    /**
     * Log, record and — if warranted — notify.
     *
     * @param Throwable $e
     * @param array     $attributes route, controller, action, revision, context
     * @return void
     */
    public function handle($e, array $attributes = [])
    {
        try {
            $this->logException($e);
        } catch (Throwable $loggingFailure) {
            //nothing left to log with; swallowing is the only safe option
        }

        if (null === $this->fingerprinter || null === $this->store) {
            return;
        }

        try {
            $record  = ExceptionRecord::fromThrowable($e, $this->fingerprinter, $attributes);
            $outcome = $this->store->record($record);
        } catch (Throwable $recordingFailure) {
            $this->note('exception recording failed: ' . $recordingFailure->getMessage());
            return;
        }

        if (null === $outcome) {
            //the store declined: unwritable, or at its fingerprint ceiling
            return;
        }

        if (null === $this->notifier) {
            return;
        }

        try {
            $status = $this->notifier->notify($record, $outcome);
        } catch (Throwable $notifyFailure) {
            $this->note('exception notification failed: ' . $notifyFailure->getMessage());
            return;
        }

        if (null !== $status) {
            $this->note(sprintf('exception %s: %s', $outcome->getFingerprint(), $status));
        }
    }

    /**
     * @param string $message
     * @return void
     */
    private function note($message)
    {
        try {
            $this->logger->err($message);
        } catch (Throwable $e) {
            //see handle(): there is nothing left to report with
        }
    }
}
