<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

use SionModel\Form\FormInterface;
use SionModel\Form\PrepareAwareInterface;
use Laminas\Validator\Csrf as CsrfValidator;

use function array_merge;

/**
 * The hidden field carrying a form's CSRF token. 35 of them — every form that writes.
 *
 * ## This one still owns a validator, and that is not an oversight
 *
 * Every other element here answers questions and judges nothing. This one holds a
 * `Laminas\Validator\Csrf` because the token is *generated* by that validator, out of a
 * session container it also owns: `getValue()` is the hash, `getAttributes()` seeds the
 * `value` attribute with it, and `prepareElement()` regenerates it whenever a form is
 * prepared for rendering. The validator is the token's source, not a rule applied to a
 * submission.
 *
 * The rule applied to a submission is a separate instance, built from
 * `SionModel\Form\CsrfSpec::forElement()` into the form's specification, and it reads
 * `getCsrfValidatorOptions()` from here so that both point at the same session container.
 * That container key is the element's own name — `security` — and getting it wrong means a
 * valid token is rejected on every form on the site, which is why `CsrfSpec` takes the
 * element rather than returning a constant. Its docblock has the measurement.
 *
 * ## The options that differ per form
 *
 * `SionForm` and four others pass `csrf_options => ['timeout' => 900]`; the rest pass
 * nothing and take laminas' 300 seconds. 28 of the 35 carry the option.
 */
class Csrf extends Element implements PrepareAwareInterface
{
    /** @var array<string, mixed> */
    protected array $attributes = [
        'type' => 'hidden',
    ];

    /** @var array<string, mixed> */
    protected array $csrfValidatorOptions = [];

    protected ?CsrfValidator $csrfValidator = null;

    /** @param iterable<string, mixed> $options */
    public function setOptions(iterable $options): static
    {
        parent::setOptions($options);

        if (isset($this->options['csrf_options'])) {
            /** @var array<string, mixed> $csrfOptions */
            $csrfOptions = $this->options['csrf_options'];
            $this->setCsrfValidatorOptions($csrfOptions);
        }

        return $this;
    }

    /** @return array<string, mixed> */
    public function getCsrfValidatorOptions(): array
    {
        return $this->csrfValidatorOptions;
    }

    /** @param array<string, mixed> $options */
    public function setCsrfValidatorOptions(array $options): static
    {
        $this->csrfValidatorOptions = $options;

        return $this;
    }

    public function getCsrfValidator(): CsrfValidator
    {
        if (null === $this->csrfValidator) {
            //`name` first, so a form that really did set its own container key in
            //csrf_options keeps it. Same order as CsrfSpec::validators(), which has to
            //produce a validator reading the same container as this one.
            $this->csrfValidator = new CsrfValidator(
                array_merge(['name' => $this->getName()], $this->csrfValidatorOptions)
            );
        }

        return $this->csrfValidator;
    }

    public function setCsrfValidator(CsrfValidator $validator): static
    {
        $this->csrfValidator = $validator;

        return $this;
    }

    /** The token, not whatever was posted: this element's value is always freshly minted. */
    public function getValue(): string
    {
        return $this->getCsrfValidator()->getHash();
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        $attributes          = parent::getAttributes();
        $attributes['value'] = $this->getCsrfValidator()->getHash();

        return $attributes;
    }

    /** Regenerates the token. `Form::prepare()` calls this before every render. */
    public function prepareElement(FormInterface $form): void
    {
        $this->getCsrfValidator()->getHash(true);
    }
}
