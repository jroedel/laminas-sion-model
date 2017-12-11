<?php
namespace SionModel\Mailing;

use AcMailer\Service\MailServiceInterface;
use AcMailer\Service\MailServiceAwareInterface;
use Zend\I18n\Translator\TranslatorInterface;
use Zend\I18n\Translator\TranslatorAwareInterface;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;
use Zend\Math\Rand;
use voku\Html2Text\Html2Text;
use Zend\Mail\Message;
use Zend\Mail\AddressList;
use SionModel\Db\Model\SionTable;

class Mailer implements MailServiceAwareInterface, TranslatorAwareInterface
{
    const CSS_PATH_DEFAULT = '/../../../public/css/email-default.css';

    const TOKEN_LENGTH = 24;

    /**
     * @var MailServiceInterface $mailService
     */
    protected $mailService;

    /**
     * @var TranslatorInterface $translator
     */
    protected $translator;

    /**
     * @todo implement or delete
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
    * @var SionTable $sionTable
    */
    protected $sionTable;

    public function __construct($mailService, $translator, $config, $sionTable)
    {
        $this->mailService  = $mailService;
        $this->translator   = $translator;
        $this->config       = $config;
        $this->sionTable = $sionTable;
    }

    public function reportMailing(Message $message, $attempt = 1, $maxAttempts = 3, $exception = null,
        $locale = null, $template = null, $trackingToken = null, $tags = null)
    {
        static $timeZone;
        if (!isset($timeZone)) {
            $timeZone = new \DateTimeZone('UTC');
        }
        $table = $this->getSionTable();
        $actingUser = $table->getActingUserId();
        $body = $message->getBodyText();
        $html = new Html2Text($body);
        //report email
        $report = [
            'toAddresses' => self::AddressListToString($message->getTo()),
            'mailingOn' => new \DateTime(null, $timeZone),
            'mailingBy' => $actingUser,
            'subject' => $message->getSubject(),
            'body' => $body,
            'sender' => !is_null($message->getSender()) ? $message->getSender()->toString() : null,
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

    protected static function AddressListToString(AddressList $list)
    {
        $addresses = [];
        foreach ($list as $address) {
            $addresses[] = $address->toString();
        }
        return implode(';', $addresses);
    }

    /**
     * @todo this
     */
    public function processQueue()
    {

    }

    /**
     * Inlines CSS rules in an HTML document
     * @todo Add a little caching so we don't have to read the same
     *      CSS file several times in the same PHP instance
     * @param string $body
     * @param string $cssPath
     */
    public static function inlineEmailStyles($body, $cssPath = Mailer::CSS_PATH_DEFAULT)
    {
        // create instance
        $cssToInlineStyles = new CssToInlineStyles();

        $css = file_get_contents(__DIR__ . $cssPath);

        // output
        return $cssToInlineStyles->convert(
            $body,
            $css
        );
    }

    protected static function getNewTrackingToken()
    {
        $token = Rand::getString(self::TOKEN_LENGTH, null, true);
        return $token;
    }

    /**
     * @param MailServiceInterface $mailService
     * @return $this
     */
    public function setMailService(MailServiceInterface $mailService)
    {
        $this->mailService = $mailService;
        return $this;
    }

    /**
     * @return MailServiceInterface
     */
    public function getMailService()
    {
        return $this->mailService;
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
    public function setTranslator(TranslatorInterface $translator = null, $textDomain = null)
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
     * @return SionTable
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
