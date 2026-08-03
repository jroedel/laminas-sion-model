<?php

namespace SionModel\Error;

use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Mails a write-up the first time a failure is seen, and again if its volume
 * spikes.
 *
 * Two things here are defensive rather than decorative:
 *
 * - The fingerprint is marked notified *before* the send is attempted.
 *   The SMTP transport waits up to 30 seconds for a connection (the socket
 *   timeout MailTransportFactory sets), so a mail host that has stopped
 *   answering would otherwise cost every subsequent visitor 30 seconds on a
 *   request that has already failed. Marking first means at most one request
 *   pays that price; the failure is written into meta.json, where
 *   fetch-exceptions.sh reports it.
 *
 * - A failed send opens a circuit breaker. A host refusing connections now will
 *   refuse the next one too.
 *
 * The consequence is honest and worth stating: if SMTP is down when a new
 * fingerprint first appears, that email is lost, not retried. The record is
 * still on disk with notify_error set, which is what the fetch script surfaces.
 */
class ExceptionNotifier
{
    /** @var TransportInterface */
    private $transport;

    /** @var NotificationGate */
    private $gate;

    /** @var ExceptionStore */
    private $store;

    /** @var array */
    private $config;

    /**
     * @param TransportInterface $transport
     * @param NotificationGate   $gate
     * @param ExceptionStore     $store
     * @param array              $config
     */
    public function __construct(
        TransportInterface $transport,
        NotificationGate $gate,
        ExceptionStore $store,
        array $config = []
    ) {
        $this->transport = $transport;
        $this->gate      = $gate;
        $this->store     = $store;
        $this->config    = $config + [
            'to'                  => [],
            'from'                => 'webmaster@localhost',
            'from_name'           => null,
            'subject_prefix'      => null,
            'max_emails_per_hour' => 20,
            'breaker_seconds'     => 900,
        ];
    }

    /**
     * @param ExceptionRecord $record
     * @param RecordOutcome   $outcome
     * @return string|null a short status for the application log, or null when
     *                     the gate decided this occurrence is not notifiable
     */
    public function notify(ExceptionRecord $record, RecordOutcome $outcome)
    {
        $decision = $this->gate->decide($record->getClassChain(), $outcome);
        if (null === $decision) {
            return null;
        }

        $recipients = $this->recipients();
        if ([] === $recipients) {
            //recording without notifying is a legitimate configuration
            return 'not-notified: no recipients configured';
        }
        if ($this->store->isBreakerOpen()) {
            return 'not-notified: transport breaker open';
        }
        if (! $this->store->claimEmailSlot($this->config['max_emails_per_hour'])) {
            return 'not-notified: hourly email ceiling reached';
        }

        $this->store->markNotified($outcome->getFingerprint(), $decision['mark']);

        try {
            $this->transport->send($this->buildMessage($record, $outcome, $decision, $recipients));
        } catch (Throwable $e) {
            $this->store->noteNotifyError($outcome->getFingerprint(), get_class($e) . ': ' . $e->getMessage());
            $this->store->openBreaker($this->config['breaker_seconds']);
            return 'notify failed: ' . $e->getMessage();
        }

        return 'notified ' . $decision['label'] . ' to ' . implode(', ', $recipients);
    }

    /**
     * @param ExceptionRecord $record
     * @param RecordOutcome   $outcome
     * @param array           $decision
     * @param string[]        $recipients
     * @return Email
     */
    private function buildMessage(
        ExceptionRecord $record,
        RecordOutcome $outcome,
        array $decision,
        array $recipients
    ) {
        $message = new Email();
        $message->from(new Address($this->config['from'], (string) $this->config['from_name']));
        foreach ($recipients as $recipient) {
            $message->addTo($recipient);
        }
        $message->subject($this->subject($record, $decision));
        $message->text($this->body($record, $outcome, $decision));

        return $message;
    }

    /**
     * @param ExceptionRecord $record
     * @param array           $decision
     * @return string
     */
    private function subject(ExceptionRecord $record, array $decision)
    {
        $prefix = $this->config['subject_prefix'];
        if (null === $prefix || '' === $prefix) {
            $prefix = '[' . $this->hostname() . ']';
        }
        $subject = sprintf(
            '%s %s %s on %s',
            $prefix,
            $decision['label'],
            $this->shortClass($record->getClass()),
            $record->getRoute()
        );

        //a header may not contain CR or LF, and the route name reaches us from
        //configuration rather than from a request, but sanitise regardless
        $subject = str_replace(["\r", "\n", "\t"], ' ', $subject);
        $subject = $this->toUtf8(preg_replace('/\s+/', ' ', $subject));

        return substr(trim($subject), 0, 200);
    }

    /**
     * @param ExceptionRecord $record
     * @param RecordOutcome   $outcome
     * @param array           $decision
     * @return string
     */
    private function body(ExceptionRecord $record, RecordOutcome $outcome, array $decision)
    {
        $fingerprint = $outcome->getFingerprint();
        $header      = [];
        if ('spike' === $decision['reason']) {
            $header[] = sprintf(
                'This failure has now occurred %d times. It was already reported once; '
                . 'this message is the volume alert.',
                $outcome->getCount()
            );
        } else {
            $header[] = 'This failure has not been seen before. You will not be emailed about it again '
                . 'unless its volume spikes, or until the store is cleared.';
        }
        $header[] = '';
        $header[] = 'Full write-ups, including the most recent occurrence:';
        $header[] = '';
        $header[] = '    bash tools/fetch-exceptions.sh';
        $header[] = '    less data/exceptions-prod/' . $fingerprint . '/first.txt';
        $header[] = '';
        $header[] = 'Once fixed, clearing the record re-arms notification, so you hear about it '
            . 'again if it comes back:';
        $header[] = '';
        $header[] = '    bash tools/clear-exceptions.sh --yes ' . $fingerprint;
        $header[] = '';
        $header[] = str_repeat('-', 72);
        $header[] = '';

        $body = implode("\n", $header) . $record->toWriteUp();

        return $this->toUtf8($body);
    }

    /**
     * @return string[]
     */
    private function recipients()
    {
        $to = $this->config['to'];
        if (is_string($to)) {
            $to = '' === $to ? [] : [$to];
        }
        if (! is_array($to)) {
            return [];
        }
        $clean = [];
        foreach ($to as $address) {
            $address = trim((string) $address);
            if ('' !== $address && false !== strpos($address, '@')) {
                $clean[] = $address;
            }
        }
        return array_values(array_unique($clean));
    }

    /**
     * @param string $class
     * @return string
     */
    private function shortClass($class)
    {
        $position = strrpos($class, '\\');
        return false === $position ? $class : substr($class, $position + 1);
    }

    /**
     * @return string
     */
    private function hostname()
    {
        if (isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST']) && '' !== $_SERVER['HTTP_HOST']) {
            return preg_replace('/[^A-Za-z0-9.:\-]/', '', $_SERVER['HTTP_HOST']);
        }
        $host = gethostname();
        return false === $host ? 'unknown-host' : $host;
    }

    /**
     * Exception messages carry whatever bytes came out of the database or the
     * request; an invalid sequence would make the MIME part undeliverable.
     *
     * @param string $value
     * @return string
     */
    private function toUtf8($value)
    {
        $value = (string) $value;
        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            if (is_string($converted)) {
                return $converted;
            }
        }
        return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '?', $value);
    }
}
