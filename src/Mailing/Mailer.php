<?php

declare(strict_types=1);

namespace SionModel\Mailing;

use DateTime;
use DateTimeZone;
use Laminas\Mail\AddressList;
use Laminas\Mail\Message;
use Laminas\Mail\Transport\TransportInterface;
use Laminas\Math\Rand;
use SionModel\Db\Model\MailingsTable;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;
use voku\Html2Text\Html2Text;
use Webmozart\Assert\Assert;

use function file_get_contents;
use function implode;

class Mailer
{
    private const CSS_PATH_DEFAULT = '/../../../public/css/email-default.css';

    private const TOKEN_LENGTH = 24;

    public function __construct(
        private TransportInterface $mailTransport,
        private MailingsTable $mailingsTable,
        private array $config,
    ) {
    }

    public function queueMessage(Message $message): bool
    {
        return false;
    }

    public function sendMessageAndLog(MailingMessage $mailingMessage): bool
    {
        $message = $mailingMessage->message;
        Assert::true($message->isValid());
        $result    = $this->mailTransport->send($message);
        $exception = null;
        if (! $result->isValid()) {
            if ($result->hasException()) {
                $exception = $result->getException();
            } else {
                $exception = new Exception($result->getMessage());
            }
        }
        //report email
        $this->reportMailing($message, 1, 3, $exception, $locale, $template, $trackingToken, $tags);
    }

    public function sendMessagesAndLog(array $mailingMessages): void
    {

    }

    public function reportMailing(MailingMessage $mailingMessage): void
    {
        static $timeZone;
        if (! isset($timeZone)) {
            $timeZone = new DateTimeZone('UTC');
        }
        $message    = $mailingMessage->message;
        $actingUser = $this->mailingsTable->getActingUserId();
        $body       = $message->getBodyText();
        $html       = new Html2Text($body);
        $sender     = $message->getSender();
        //report email
        $report = [
            'toAddresses'      => self::addressListToString($message->getTo()),
            'ccAddresses'      => self::addressListToString($message->getCc()),
            'bccAddresses'     => self::addressListToString($message->getBcc()),
            'replyToAddresses' => self::addressListToString($message->getReplyTo()),
            'mailingOn'        => new DateTime('now', $timeZone),
            'mailingBy'        => $actingUser,
            'subject'          => $message->getSubject(),
            'body'             => $body,
            'sender'           => $sender?->toString(),
            'text'             => $html->getText(),
            'tags'             => $mailingMessage->tags,
            'trackingToken'    => $mailingMessage->trackingToken,
            'emailTemplate'    => $mailingMessage->template,
            'emailLocale'      => $mailingMessage->locale,
        ];
        $this->mailingsTable->createEntity('mailing', $report);
    }

    protected static function addressListToString(AddressList $list): string
    {
        $addresses = [];
        foreach ($list as $address) {
            $addresses[] = $address->toString();
        }
        return implode(';', $addresses);
    }

    /**
     * Inlines CSS rules in an HTML document
     */
    public static function inlineEmailStyles(string $body, string $cssPath = self::CSS_PATH_DEFAULT): string
    {
        Assert::fileExists($cssPath);
        // create instance
        $cssToInlineStyles = new CssToInlineStyles();

        $css = file_get_contents(__DIR__ . $cssPath);

        // output
        return $cssToInlineStyles->convert(
            $body,
            $css
        );
    }

    public static function getNewTrackingToken(): string
    {
        return Rand::getString(self::TOKEN_LENGTH);
    }
}
