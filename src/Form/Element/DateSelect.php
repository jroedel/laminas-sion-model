<?php

declare(strict_types=1);

namespace SionModel\Form\Element;

use DateTime;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use Laminas\Form\ElementPrepareAwareInterface;
use Laminas\Form\FormInterface;

use function date;
use function is_array;
use function is_string;
use function sprintf;

/**
 * A day, a month and a year, as three `<select>`s that post as one field.
 *
 * One of these on the site — `Schoenstatt\Form\PersonForm::nameDay` — and it is the reason
 * this class exists at all. A name day is a day and a month with no meaningful year, which
 * is why the form hides the year select behind a fixed 1900 and pairs the field with
 * `SionModel\Filter\DateSelectNoYear`.
 *
 * ## Why an element composes elements
 *
 * The value of one of these is `1900-09-15`, and the browser posts
 * `nameDay[year]=1900&nameDay[month]=09&nameDay[day]=15`. Something has to join and split
 * that, and the choice laminas made — and this keeps — is that the parent element owns
 * three real {@see Select}s, renames them to `name[day]` and so on when the form is
 * prepared, and translates between the parts and the string in `setValue()`/`getValue()`.
 *
 * `templates/schoenstatt/_person-fields.html.twig` reaches straight for
 * `getMonthElement()->getValue()` and `getDayElement()->getValue()`, so the sub-elements
 * are part of the public surface and not an implementation detail.
 *
 * ## laminas' MonthSelect is not reproduced
 *
 * There, `DateSelect extends MonthSelect` and adds the day. Here there is no MonthSelect:
 * the element census found zero of them, in any form, under any name — so the parent class
 * would have existed only to be inherited from once. Everything it held is below.
 *
 * ## The trap in `setValue()`
 *
 * A string is fed to `new DateTime()`, and an unparsable one **throws**. That is not
 * theoretical: it is why `SionModel\Form\SionForm::blankUnusableDateSelectValues()` exists,
 * blanking anything a date select cannot swallow *before* `setData()` reaches it, so that a
 * hostile or fat-fingered post fails validation instead of fataling. Keep the throw: it is
 * the contract that guard is written against, and swallowing it here would make the guard
 * look unnecessary while quietly accepting nonsense.
 *
 * The other trap is `null`: unless `create_empty_option` is set, a null value means *now*,
 * not empty. A date select left blank therefore reads as today unless the form asks for an
 * empty option — and `PersonForm` does ask.
 */
class DateSelect extends Element implements ElementPrepareAwareInterface
{
    protected Select $dayElement;

    protected Select $monthElement;

    protected Select $yearElement;

    protected int $minYear;

    protected int $maxYear;

    protected bool $createEmptyOption = false;

    protected bool $renderDelimiters = true;

    /**
     * @param null|int|string $name
     * @param iterable<string, mixed> $options
     */
    public function __construct($name = null, iterable $options = [])
    {
        //All of this before parent::__construct(), which runs setOptions(), which writes
        //into every one of them.
        $this->minYear      = ((int) date('Y')) - 100;
        $this->maxYear      = (int) date('Y');
        $this->dayElement   = new Select('day');
        $this->monthElement = new Select('month');
        $this->yearElement  = new Select('year');

        parent::__construct($name, $options);
    }

    /** @param iterable<string, mixed> $options */
    public function setOptions(iterable $options): static
    {
        parent::setOptions($options);

        if (isset($this->options['day_attributes'])) {
            /** @var array<string, mixed> $dayAttributes */
            $dayAttributes = $this->options['day_attributes'];
            $this->setDayAttributes($dayAttributes);
        }

        if (isset($this->options['month_attributes'])) {
            /** @var array<string, mixed> $monthAttributes */
            $monthAttributes = $this->options['month_attributes'];
            $this->setMonthAttributes($monthAttributes);
        }

        if (isset($this->options['year_attributes'])) {
            /** @var array<string, mixed> $yearAttributes */
            $yearAttributes = $this->options['year_attributes'];
            $this->setYearAttributes($yearAttributes);
        }

        if (isset($this->options['min_year'])) {
            $this->setMinYear((int) $this->options['min_year']);
        }

        if (isset($this->options['max_year'])) {
            $this->setMaxYear((int) $this->options['max_year']);
        }

        if (isset($this->options['create_empty_option'])) {
            $this->setShouldCreateEmptyOption((bool) $this->options['create_empty_option']);
        }

        if (isset($this->options['render_delimiters'])) {
            $this->setShouldRenderDelimiters((bool) $this->options['render_delimiters']);
        }

        return $this;
    }

    public function getDayElement(): Select
    {
        return $this->dayElement;
    }

    public function getMonthElement(): Select
    {
        return $this->monthElement;
    }

    public function getYearElement(): Select
    {
        return $this->yearElement;
    }

    /** @return list<Select> */
    public function getElements(): array
    {
        return [$this->dayElement, $this->monthElement, $this->yearElement];
    }

    /** @param array<string, mixed> $dayAttributes */
    public function setDayAttributes(array $dayAttributes): static
    {
        $this->dayElement->setAttributes($dayAttributes);

        return $this;
    }

    /** @return array<string, mixed> */
    public function getDayAttributes(): array
    {
        return $this->dayElement->getAttributes();
    }

    /** @param array<string, mixed> $monthAttributes */
    public function setMonthAttributes(array $monthAttributes): static
    {
        $this->monthElement->setAttributes($monthAttributes);

        return $this;
    }

    /** @return array<string, mixed> */
    public function getMonthAttributes(): array
    {
        return $this->monthElement->getAttributes();
    }

    /** @param array<string, mixed> $yearAttributes */
    public function setYearAttributes(array $yearAttributes): static
    {
        $this->yearElement->setAttributes($yearAttributes);

        return $this;
    }

    /** @return array<string, mixed> */
    public function getYearAttributes(): array
    {
        return $this->yearElement->getAttributes();
    }

    public function setMinYear(int $minYear): static
    {
        $this->minYear = $minYear;

        return $this;
    }

    public function getMinYear(): int
    {
        return $this->minYear;
    }

    public function setMaxYear(int $maxYear): static
    {
        $this->maxYear = $maxYear;

        return $this;
    }

    public function getMaxYear(): int
    {
        return $this->maxYear;
    }

    public function setShouldCreateEmptyOption(bool $createEmptyOption): static
    {
        $this->createEmptyOption = $createEmptyOption;

        return $this;
    }

    public function shouldCreateEmptyOption(): bool
    {
        return $this->createEmptyOption;
    }

    public function setShouldRenderDelimiters(bool $renderDelimiters): static
    {
        $this->renderDelimiters = $renderDelimiters;

        return $this;
    }

    public function shouldRenderDelimiters(): bool
    {
        return $this->renderDelimiters;
    }

    /**
     * A string, a DateTimeInterface, an array of parts, or null.
     *
     * @throws InvalidArgumentException When a string cannot be parsed as a date.
     */
    public function setValue(mixed $value): static
    {
        if (is_string($value)) {
            try {
                $value = new DateTime($value);
            } catch (Exception $exception) {
                throw new InvalidArgumentException(
                    'Value should be a parsable string or an instance of DateTime',
                    0,
                    $exception
                );
            }
        }

        //Null means "now" unless the form asked for an empty option — see the class docblock.
        if (null === $value && ! $this->shouldCreateEmptyOption()) {
            $value = new DateTime();
        }

        if ($value instanceof DateTimeInterface) {
            $value = [
                'year'  => $value->format('Y'),
                'month' => $value->format('m'),
                'day'   => $value->format('d'),
            ];
        }

        if (is_array($value)) {
            $this->yearElement->setValue($value['year']);
            $this->monthElement->setValue($value['month']);
            $this->dayElement->setValue($value['day']);
        } else {
            $this->yearElement->setValue(null);
            $this->monthElement->setValue(null);
            $this->dayElement->setValue(null);
        }

        return $this;
    }

    public function getValue(): ?string
    {
        $year  = $this->yearElement->getValue();
        $month = $this->monthElement->getValue();
        $day   = $this->dayElement->getValue();

        if ($this->shouldCreateEmptyOption() && null === $year && null === $month && null === $day) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    public function prepareElement(FormInterface $form): void
    {
        $name = (string) $this->getName();
        $this->dayElement->setName($name . '[day]');
        $this->monthElement->setName($name . '[month]');
        $this->yearElement->setName($name . '[year]');
    }

    /** A Collection copies its target element, and two copies must not share sub-elements. */
    public function __clone()
    {
        $this->dayElement   = clone $this->dayElement;
        $this->monthElement = clone $this->monthElement;
        $this->yearElement  = clone $this->yearElement;
    }
}
