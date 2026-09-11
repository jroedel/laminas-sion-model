<?php

declare(strict_types=1);

namespace SionModel\Form;

use SionModel\Form\Exception\ElementNotFound;

use function array_key_exists;
use function count;
use function ctype_digit;
use function is_array;
use function is_iterable;
use function iterator_to_array;
use function max;
use function sprintf;

/**
 * A fieldset repeated once per row.
 *
 * ## The one collection in this application, and why it still needs all of this
 *
 * `Books\Form\MassCheckoutForm::checkout` — four rows of "book, borrower, date" so a
 * librarian can record a stack of loans at once. `library-mass-checkout.html.twig`
 * iterates it, ships a fifth row as a JavaScript template, and the page's script clones
 * that template when the librarian wants another line. Every option below is one that page
 * sets, and the behaviours are transcribed from `Laminas\Form\Element\Collection` rather
 * than reinvented, because `test/Form/form-markup.php` records what that class produced.
 *
 * ## The behaviour that is easy to lose, and was measured
 *
 * **A collection that has taken data creates no further rows.** `addNewTargetElementInstance()`
 * switches {@see $createChildrenOnPrepare} off, so `prepare()` materialises `count` rows on
 * a form nobody submitted and adds none to a form that came back from a failed POST with
 * one row in it. Get that wrong in either direction and the page is wrong in a way a
 * screenshot shows: four empty lines where the librarian submitted one, or one line where
 * they expect four. It is also why `test/Form/form-markup.php` records the `prepared` state
 * from a build of its own — the recording had this backwards until it was measured.
 *
 * **The template row is added, prepared, and removed again.** It exists so that
 * `prepare()` gives it the same `checkout[__index__][…]` naming every real row gets; it
 * must not survive into the rendered list, or the form posts a row of empty strings.
 */
class Collection extends Fieldset
{
    public const DEFAULT_TEMPLATE_PLACEHOLDER = '__index__';

    /** How many rows a pristine form renders. */
    protected int $count = 1;

    protected bool $allowAdd = true;

    protected bool $allowRemove = true;

    protected bool $shouldCreateTemplate = false;

    protected string $templatePlaceholder = self::DEFAULT_TEMPLATE_PLACEHOLDER;

    protected ?ElementInterface $targetElement = null;

    /** The highest row index created so far; -1 when there is none. */
    protected int $lastChildIndex = -1;

    /** Switched off the moment a row is created from data. See the class docblock. */
    protected bool $createChildrenOnPrepare = true;

    /** @inheritDoc */
    public function setOptions(iterable $options): static
    {
        $options = is_array($options) ? $options : iterator_to_array($options);

        if (array_key_exists('target_element', $options)) {
            $this->setTargetElement($options['target_element']);
        }
        if (array_key_exists('count', $options)) {
            $this->setCount((int) $options['count']);
        }
        if (array_key_exists('allow_add', $options)) {
            $this->allowAdd = (bool) $options['allow_add'];
        }
        if (array_key_exists('allow_remove', $options)) {
            $this->allowRemove = (bool) $options['allow_remove'];
        }
        if (array_key_exists('should_create_template', $options)) {
            $this->shouldCreateTemplate = (bool) $options['should_create_template'];
        }
        if (array_key_exists('template_placeholder', $options)) {
            $this->templatePlaceholder = (string) $options['template_placeholder'];
        }

        //The rest — label above all — through the element's own handling. The keys read
        //here are left in deliberately: `getOptions()` is part of what
        //`test/Element/element-surface.php` records, and laminas leaves them in too.
        return parent::setOptions($options);
    }

    /** @param ElementInterface|array<string, mixed> $elementOrSpec */
    public function setTargetElement(ElementInterface|array $elementOrSpec): static
    {
        $this->targetElement = $elementOrSpec instanceof ElementInterface
            ? $elementOrSpec
            : $this->getFormFactory()->create($elementOrSpec);

        return $this;
    }

    public function getTargetElement(): ?ElementInterface
    {
        return $this->targetElement;
    }

    public function setCount(int $count): static
    {
        $this->count = max(0, $count);

        return $this;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function allowAdd(): bool
    {
        return $this->allowAdd;
    }

    public function allowRemove(): bool
    {
        return $this->allowRemove;
    }

    public function shouldCreateTemplate(): bool
    {
        return $this->shouldCreateTemplate;
    }

    public function getTemplatePlaceholder(): string
    {
        return $this->templatePlaceholder;
    }

    /**
     * A row named `__index__`, for a page that clones rows in the browser.
     *
     * `App\Controller\LibraryMassCheckoutController` renders this one through the same
     * partial as a real row and hands the markup to the page's script.
     */
    public function getTemplateElement(): ?ElementInterface
    {
        if (null === $this->targetElement) {
            return null;
        }

        $template = clone $this->targetElement;
        $template->setName($this->templatePlaceholder);

        return $template;
    }

    /**
     * @inheritDoc
     *
     * Rows arrive keyed by index. A row the data does not mention is removed — that is
     * what `allow_remove` means, and the submission of a page whose librarian deleted a
     * line simply does not carry it.
     */
    public function populateValues(iterable $data): void
    {
        $data = is_array($data) ? $data : iterator_to_array($data);

        if (! $this->allowRemove && count($data) < $this->count) {
            throw new ElementNotFound(sprintf(
                'There are fewer rows than %s expects. Either allow_remove, or re-submit the form.',
                static::class
            ));
        }

        foreach ($this->children as $name => $_) {
            if (! array_key_exists($name, $data)) {
                $this->remove((string) $name);
            }
        }

        foreach ($data as $key => $value) {
            $key = (string) $key;
            $row = $this->has($key) ? $this->get($key) : $this->addRow($key);

            if ($row instanceof FieldsetInterface && is_iterable($value)) {
                $row->populateValues($value);
                continue;
            }

            $row->setValue($value);
        }
    }

    /**
     * @inheritDoc
     *
     * The order is laminas': materialise the rows, add the template, rename everything,
     * then take the template away again.
     */
    public function prepareElement(FormInterface $form): void
    {
        if ($this->createChildrenOnPrepare && null !== $this->targetElement) {
            while ($this->count > $this->lastChildIndex + 1) {
                $this->addRow((string) ++$this->lastChildIndex);
            }
        }

        $template = $this->shouldCreateTemplate ? $this->getTemplateElement() : null;
        if (null !== $template) {
            $this->add($template);
        }

        parent::prepareElement($form);

        if (null !== $template) {
            $this->remove($this->templatePlaceholder);
        }
    }

    /**
     * One more row, named by its index.
     *
     * Switching {@see $createChildrenOnPrepare} off here is the whole of the behaviour the
     * class docblock names, and it belongs here rather than in `populateValues()` because
     * `prepare()` on a form that has *any* row must add none.
     */
    private function addRow(string $name): ElementInterface
    {
        if (null === $this->targetElement) {
            throw new ElementNotFound(sprintf('%s has no target element to build a row from.', static::class));
        }

        $this->createChildrenOnPrepare = false;

        $row = clone $this->targetElement;
        $row->setName($name);
        $this->add($row);

        //Only a numeric name moves the index. laminas tests `is_int($key)` on the raw
        //array key, which is the same thing one step earlier: a row keyed `0` in the
        //submitted data arrives here as the string `'0'`.
        if (ctype_digit($name)) {
            $this->lastChildIndex = max($this->lastChildIndex, (int) $name);
        }

        if (! $this->allowAdd && $this->count() > $this->count) {
            throw new ElementNotFound(sprintf(
                'There are more rows than %s expects. Either allow_add, or re-submit the form.',
                static::class
            ));
        }

        return $row;
    }
}
