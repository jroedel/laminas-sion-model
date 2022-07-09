<?php

namespace SionModel\Mailing;

use Laminas\Mail\Message;

class MailingMessage
{
    public function __construct(
        public Message $message,
        public ?string $locale = null,
        public ?string $template = null,
        public ?string $trackingToken = null,
        public ?string $tags = null
    ) {
    }
}
