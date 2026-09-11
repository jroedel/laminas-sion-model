<?php

declare(strict_types=1);

namespace SionModel\Validator;

use Laminas\Translator\TranslatorInterface;

use function array_key_exists;
use function array_keys;
use function array_unique;
use function current;
use function implode;
use function is_array;
use function is_object;
use function is_string;
use function key;
use function method_exists;
use function sprintf;
use function str_replace;
use function ucfirst;
use function var_export;

use const SORT_REGULAR;

/**
 * The message machinery every validator here shares.
 *
 * `SionModel\Validator\AbstractValidator` is 662 lines; this is what the application reaches
 * of it. Gone: the `Traversable` options, `valueObscured`, `messageLength`, the
 * per-instance translator, `TranslatorAwareInterface`, and `getOption()`.
 *
 * ## A message is a template plus the value that failed
 *
 * A validator declares `$messageTemplates` — key => text — and reports one with
 * {@see error()}. The text may carry `%value%`, which is the value that failed, and
 * `%name%` for anything in `$messageVariables`. That substitution is why these classes hold
 * state at all, and why {@see \SionModel\Form\Validation\InputFilter} builds a fresh one per
 * field rather than sharing.
 *
 * ## The translator is static, and that is not an accident to be fixed
 *
 * `App\Laminas\TranslatorConfigurator` calls `setDefaultTranslator()` once, and every
 * validator built anywhere thereafter translates through it. It is global state and it is
 * the only way a validator constructed inside a form specification — with no container, no
 * request and no injection point — can speak the visitor's language.
 *
 * Two consequences worth naming, because both have cost time:
 *
 * - **A CLI process usually has no translator**, so messages come out in their source
 *   English. That is right for a console command and wrong to conclude anything from.
 * - **A recording of messages must take it away first.** `test/Rules/rule-surface.php`
 *   recorded `NotEmpty` as "Valor es requerido" on its first run, because an earlier test
 *   in the same process had switched locale. The text domain is `default`, which is what
 *   `TranslatorConfigurator` passes and what five catalogs are keyed by.
 */
abstract class AbstractValidator implements ValidatorInterface
{
    /** @var TranslatorInterface|null */
    protected static $defaultTranslator;

    /** @var string */
    protected static $defaultTranslatorTextDomain = 'default';

    /** @var mixed */
    protected $value;

    /**
     * Message key => template. Declared by each validator; `%value%` and any
     * `$messageVariables` key are substituted.
     *
     * @var array<string, string>
     */
    protected $messageTemplates = [];

    /**
     * Template placeholder => where to read it.
     *
     * A string names a property of the validator; an array of the form
     * `['options' => 'max']` names `$this->options['max']`. Both spellings are in the
     * classes this replaces, so both are honoured.
     *
     * @var array<string, string|array<string, string>>
     */
    protected $messageVariables = [];

    /** @var array<string, string> */
    private array $messages = [];

    /** @param array<string, mixed>|null $options */
    public function __construct($options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    public static function setDefaultTranslator(
        ?TranslatorInterface $translator = null,
        ?string $textDomain = null
    ): void {
        self::$defaultTranslator = $translator;

        if (null !== $textDomain) {
            self::$defaultTranslatorTextDomain = $textDomain;
        }
    }

    public static function getDefaultTranslator(): ?TranslatorInterface
    {
        return self::$defaultTranslator;
    }

    public static function getDefaultTranslatorTextDomain(): string
    {
        return self::$defaultTranslatorTextDomain;
    }

    /**
     * Apply an options array, by setter where there is one.
     *
     * laminas tried `$name`, then `set<Name>`, then `is<Name>`, then `$this->options[$name]`,
     * and silently accepted anything else into an untyped bag. The first three are kept —
     * `messages`, `messageTemplates`, `strict` and `literal` all arrive through them — and
     * the bag is not: an option nothing reads is an option that was meant to do something.
     *
     * @param array<string, mixed> $options
     * @throws Exception\InvalidArgumentException
     */
    public function setOptions(array $options): static
    {
        /** @var mixed $option */
        foreach ($options as $name => $option) {
            $setter = 'set' . ucfirst((string) $name);

            if (method_exists($this, $setter)) {
                $this->{$setter}($option);
                continue;
            }

            if (isset($this->options) && is_array($this->options) && array_key_exists($name, $this->options)) {
                $this->options[$name] = $option;
                continue;
            }

            throw new Exception\InvalidArgumentException(sprintf(
                'The option "%s" is not one %s accepts',
                (string) $name,
                static::class
            ));
        }

        return $this;
    }

    /**
     * Replace one or more message templates.
     *
     * Nine specifications override a message this way — "That is not this library's name.
     * Nothing has been deleted." is one — and the override is the text a person reads.
     *
     * @param array<string, string> $messages
     */
    public function setMessages(array $messages): static
    {
        foreach ($messages as $key => $message) {
            $this->messageTemplates[$key] = $message;
        }

        return $this;
    }

    /**
     * Replace the **whole** template set, which is not what the name suggests.
     *
     * `messageTemplates` was never a setter in laminas: `AbstractValidator::setOptions()`
     * found no `setMessageTemplates()` and no `$options` key for it, and fell through to
     * assigning `$abstractOptions['messageTemplates']` wholesale. So a specification
     * spelling `'messageTemplates' => ['regexNotMatch' => '…']` **deletes** `regexInvalid`
     * and `regexErrorous` along the way, and two specifications here do exactly that.
     *
     * The consequence is live and worth naming: a `Regex` so configured, handed an array or
     * a boolean, reports `regexInvalid` — for which there is now no template — so
     * {@see error()} produces no message, the engine sees an empty message set, and **the
     * field passes**. Two fields in the application are in that state.
     *
     * Reproduced rather than repaired, because repairing it changes what two forms accept,
     * which is a decision about those forms. {@see setMessages()} is the merging spelling
     * and is what a specification should use.
     *
     * @param array<string, string> $messages
     */
    public function setMessageTemplates(array $messages): static
    {
        $this->messageTemplates = $messages;

        return $this;
    }

    /** @return array<string, string> */
    public function getMessages()
    {
        return array_unique($this->messages, SORT_REGULAR);
    }

    /**
     * Record the value under judgement, and clear what the last judgement said.
     *
     * Clearing is the part that matters: a validator reused across two values would
     * otherwise report the first one's failure against the second.
     */
    protected function setValue(mixed $value): void
    {
        $this->value    = $value;
        $this->messages = [];
    }

    /** @return mixed */
    protected function getValue()
    {
        return $this->value;
    }

    /**
     * Report a failure under one of this validator's message keys.
     *
     * A key with no template is silently ignored, which is laminas' behaviour and is what
     * lets a subclass narrow the set of messages it can produce.
     */
    protected function error(?string $messageKey, mixed $value = null): void
    {
        if (null === $messageKey) {
            $keys       = array_keys($this->messageTemplates);
            $messageKey = current($keys);
        }

        if (! is_string($messageKey) || ! isset($this->messageTemplates[$messageKey])) {
            return;
        }

        $this->messages[$messageKey] = $this->createMessage(
            $messageKey,
            null === $value ? $this->value : $value
        );
    }

    private function createMessage(string $messageKey, mixed $value): string
    {
        $message = $this->translate($this->messageTemplates[$messageKey]);

        if (is_object($value)) {
            $printed = method_exists($value, '__toString') ? (string) $value : $value::class . ' object';
        } elseif (is_array($value)) {
            $printed = var_export($value, true);
        } else {
            $printed = (string) $value;
        }

        $message = str_replace('%value%', $printed, $message);

        foreach ($this->messageVariables as $placeholder => $source) {
            if (is_array($source)) {
                /** @var mixed $replacement */
                $replacement = $this->{key($source)}[current($source)];
            } else {
                /** @var mixed $replacement */
                $replacement = $this->{$source};
            }

            if (is_array($replacement)) {
                $replacement = '[' . implode(', ', $replacement) . ']';
            }

            $message = str_replace('%' . $placeholder . '%', (string) $replacement, $message);
        }

        return $message;
    }

    private function translate(string $message): string
    {
        return null === self::$defaultTranslator
            ? $message
            : self::$defaultTranslator->translate($message, self::$defaultTranslatorTextDomain);
    }
}
