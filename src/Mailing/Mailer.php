<?php
namespace SionModel\Mailing;

use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\I18n\Translator\TranslatorAwareInterface;
use SionModel\Db\Model\SionTable;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;
use voku\Html2Text\Html2Text;

/**
 * Base class for application mailers: builds messages stamped with the
 * application's mail identity, renders their bodies from Twig templates through a
 * {@see TemplateRendererInterface}, and records every attempt in the mailings table.
 */
class Mailer implements TranslatorAwareInterface
{
    /**
     * Relative to the application root, where the entry points chdir()
     * to. The old module-relative default ('/../../../public/css/…' from this
     * file) pointed inside the SionModel package, where the file has never
     * existed since the module was vendored into applications — every mail
     * went out unstyled, with only a PHP warning to show for it.
     */
    const CSS_PATH_DEFAULT = 'public/css/email-default.css';

    const TOKEN_LENGTH = 24;

    /**
     * @var TransportInterface $transport
     */
    protected $transport;

    /**
     * @var TemplateRendererInterface $renderer
     */
    protected $renderer;

    /**
     * @var TranslatorInterface $translator
     */
    protected $translator;

    /**
     * @var string $textDomain
     */
    protected $textDomain;

    /**
     * @var bool $isTranslatorEnabled
     */
    protected $isTranslatorEnabled;

    /**
     * @var array $config
     */
    protected $config;

    /**
    * @var SionTable|null $sionTable
    */
    protected $sionTable;

    public function __construct(
        TransportInterface $transport,
        TemplateRendererInterface $renderer,
        $translator,
        array $config,
        ?SionTable $sionTable = null
    ) {
        $this->transport = $transport;
        $this->renderer  = $renderer;
        $this->translator = $translator;
        $this->config    = $config;
        $this->sionTable = $sionTable;
    }

    /**
     * A message pre-addressed with the application's mail identity, taken from
     * the `sion_model.mail` config block (from, from_name, bcc).
     *
     * @return Email
     */
    public function createEmail()
    {
        $mailConfig = isset($this->config['sion_model']['mail']) && is_array($this->config['sion_model']['mail'])
            ? $this->config['sion_model']['mail']
            : [];
        $email = new Email();
        if (isset($mailConfig['from']) && '' !== $mailConfig['from']) {
            $fromName = isset($mailConfig['from_name']) ? (string) $mailConfig['from_name'] : '';
            $email->from(new Address($mailConfig['from'], $fromName));
        }
        $bcc = isset($mailConfig['bcc']) ? (array) $mailConfig['bcc'] : [];
        foreach ($bcc as $address) {
            $email->addBcc($address);
        }
        return $email;
    }

    /**
     * Render a template to an HTML string, for use as a message body.
     *
     * @param string $template a name the renderer resolves, e.g.
     *        `@sion-model/mailing/action-email.html.twig`
     * @param array $params
     * @return string
     */
    public function renderTemplate($template, array $params)
    {
        return $this->renderer->render((string) $template, $params);
    }

    public function reportMailing(
        Email $message,
        $attempt = 1,
        $maxAttempts = 3,
        $exception = null,
        $locale = null,
        $template = null,
        $trackingToken = null,
        $tags = null
    ) {
        $table = $this->getSionTable();
        if (! isset($table)) {
            //a mailer without a table sends without reporting
            return;
        }
        static $timeZone;
        if (!isset($timeZone)) {
            $timeZone = new \DateTimeZone('UTC');
        }
        $actingUser = $table->getActingUserId();
        $body = $message->getHtmlBody();
        if (null === $body) {
            $body = $message->getTextBody();
        }
        $html = new Html2Text((string) $body);
        $sender = $message->getSender();
        //report email
        $report = [
            'toAddresses' => self::addressListToString($message->getTo()),
            'mailingOn' => new \DateTime('now', $timeZone),
            'mailingBy' => $actingUser,
            'subject' => $message->getSubject(),
            'body' => $body,
            'sender' => isset($sender) ? $sender->toString() : null,
            'text' => $html->getText(),
            'tags' => $tags,
            'trackingToken' => $trackingToken,
            'emailTemplate' => $template,
            'emailLocale' => $locale,
            'status' => isset($exception) ? 'Error' : 'Success',
            'attempt' => $attempt,
            'maxAttempts' => $maxAttempts,
            'queueUntil' => null,
            'errorMessage' => isset($exception) ? $exception->getMessage() : null,
            'stackTrace' => isset($exception) ? $exception->getTraceAsString() : null,
        ];
        $table->createEntity('mailing', $report);
    }

    /**
     * @param Address[] $list
     * @return string
     */
    protected static function addressListToString(array $list)
    {
        $addresses = [];
        foreach ($list as $address) {
            $addresses[] = $address->toString();
        }
        return implode(';', $addresses);
    }

    /**
     * Inlines CSS rules in an HTML document
     * @todo Add a little caching so we don't have to read the same
     *      CSS file several times in the same PHP instance
     * @param string $body
     * @param string $cssPath path to the stylesheet, relative to the
     *      application root (or absolute)
     * @return string
     * @throws \RuntimeException when the stylesheet cannot be read: a missing
     *      file used to degrade silently to unstyled mail
     */
    public static function inlineEmailStyles($body, $cssPath = Mailer::CSS_PATH_DEFAULT)
    {
        $css = @file_get_contents($cssPath);
        if (false === $css) {
            throw new \RuntimeException(sprintf(
                'Email stylesheet not readable: %s (cwd: %s)',
                $cssPath,
                getcwd()
            ));
        }

        return (new CssToInlineStyles())->convert($body, $css);
    }

    /**
     * A tracking token for one message.
     *
     * `Laminas\Math\Rand::getString(24)` until 2026-09, and this reproduces exactly what
     * that did with no character list: base64 of `ceil(length * 0.75)` random bytes, the
     * padding stripped, cut to length. The alphabet therefore still includes `+` and `/`.
     * That is kept rather than tidied because the tokens already in `mailings` were
     * generated this way and the column is compared against them; nothing puts one in a
     * URL, which is the only place those two characters would be a problem.
     *
     * @return string
     */
    protected static function getNewTrackingToken()
    {
        $bytes = random_bytes((int) ceil(self::TOKEN_LENGTH * 0.75));

        return substr(rtrim(base64_encode($bytes), '='), 0, self::TOKEN_LENGTH);
    }

    /**
     * @return TransportInterface
     */
    public function getTransport()
    {
        return $this->transport;
    }

    /**
     * @param TransportInterface $transport
     * @return $this
     */
    public function setTransport(TransportInterface $transport)
    {
        $this->transport = $transport;
        return $this;
    }

    /**
     * Sets translator to use in helper
     *
     * @param  TranslatorInterface $translator  [optional] translator.
     *                                           Default is null, which sets no translator.
     * @param  string              $textDomain  [optional] text domain
     *                                           Default is null, which skips setTranslatorTextDomain
     * @return TranslatorAwareInterface
     */
    public function setTranslator(?TranslatorInterface $translator = null, $textDomain = null)
    {
        $this->translator =  $translator;
        if (isset($textDomain)) {
            $this->setTranslatorTextDomain($textDomain);
        }
        return $this;
    }

    /**
     * Returns translator used in object
     *
     * @return TranslatorInterface|null
     */
    public function getTranslator()
    {
        return $this->translator;
    }

    /**
     * Checks if the object has a translator
     *
     * @return bool
     */
    public function hasTranslator()
    {
        return isset($this->translator) && $this->translator instanceof TranslatorInterface;
    }

    /**
     * Sets whether translator is enabled and should be used
     *
     * @param  bool $enabled [optional] whether translator should be used.
     *                       Default is true.
     * @return TranslatorAwareInterface
     */
    public function setTranslatorEnabled($enabled = true)
    {
        $this->isTranslatorEnabled = (bool) $enabled;
        return $this;
    }

    /**
     * Returns whether translator is enabled and should be used
     *
     * @return bool
     */
    public function isTranslatorEnabled()
    {
        return (bool) $this->isTranslatorEnabled;
    }

    /**
     * Set translation text domain
     *
     * @param  string $textDomain
     * @return TranslatorAwareInterface
     */
    public function setTranslatorTextDomain($textDomain = 'default')
    {
        $this->textDomain = $textDomain;
        return $this;
    }

    /**
     * Return the translation text domain
     *
     * @return string
     */
    public function getTranslatorTextDomain()
    {
        return $this->textDomain;
    }

    /**
     * Get the sionTable value
     * @return SionTable|null
     */
    public function getSionTable()
    {
        return $this->sionTable;
    }

    /**
     *
     * @param SionTable $sionTable
     * @return self
     */
    public function setSionTable($sionTable)
    {
        $this->sionTable = $sionTable;
        return $this;
    }
}
