<?php

namespace SionModel\Validator;

use SionModel\Filter\DateTimeParser;

use function is_scalar;
use function is_string;

/**
 * Is this date plausible for the field it was entered into?
 *
 * SionModel\Filter\DateTimeParser answers "is this a real, storable date" — a
 * property of the calendar and the column. This answers the separate question
 * each field has its own answer to. A birth date cannot be in the future; an
 * assignment's end date routinely is, because offices are planned ahead; a book
 * cannot have been borrowed tomorrow.
 *
 * Every bound configured in this application was checked against the live data
 * first and rejects **zero** existing rows. That is the property to preserve when
 * changing one: a bound that excludes a value already stored does not prevent bad
 * data, it makes every future edit of that record fail validation on a field the
 * editor may not even be touching.
 *
 * `min` and `max` accept either an absolute `Y-m-d` or a relative expression
 * (`'today'`, `'+2 years'`, `'+10 years'`), and a relative one is resolved **when
 * validation runs**, not when the validator is built. That distinction matters
 * here more than it looks: the merged module configuration is cached to
 * data/config/, so a bound computed while building a configuration array would be
 * frozen into the cache and would drift silently until someone cleared it.
 *
 * Comparison is date-only. These columns are DATE, and the values arrive as
 * midnight UTC, so comparing instants would make "today" mean "before this moment
 * today" and reject a date entered this afternoon.
 */
class DateWithinRange extends AbstractValidator
{
    public const TOO_EARLY = 'dateTooEarly';
    public const TOO_LATE  = 'dateTooLate';
    public const INVALID   = 'dateNotComparable';

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::TOO_EARLY => "This date is earlier than %minDisplay%, which this field does not expect. "
            . "Please check it.",
        self::TOO_LATE  => "This date is later than %maxDisplay%, which this field does not expect. "
            . "Please check it.",
        self::INVALID   => 'Invalid type given. A date was expected.',
    ];

    /**
     * Only the resolved bounds are exposed to messages. The rejected value is
     * not interpolated: it is hostile by definition on the paths that matter, and
     * the form redisplays what the user typed in the field itself.
     *
     * @var array<string, mixed>
     */
    protected $messageVariables = [
        'minDisplay' => 'minDisplay',
        'maxDisplay' => 'maxDisplay',
    ];

    /** @var string|null */
    protected $min;

    /** @var string|null */
    protected $max;

    /** @var string */
    protected $minDisplay = '';

    /** @var string */
    protected $maxDisplay = '';

    /**
     * @param string|null $min
     * @return self
     */
    public function setMin($min)
    {
        $this->min = $min;
        return $this;
    }

    /**
     * @param string|null $max
     * @return self
     */
    public function setMax($max)
    {
        $this->max = $max;
        return $this;
    }

    /**
     * @param  mixed $value
     * @return bool
     */
    public function isValid($value, $context = null)
    {
        //Emptiness is `required`/NotEmpty's business. Saying otherwise here would
        //make every optional date mandatory, and most of these are unknown for
        //most records — 175 of 325 persons have no birth date.
        if (null === $value || '' === $value) {
            return true;
        }

        $date = DateTimeParser::parse($value);
        if (null === $date) {
            return true;
        }

        //false means the parser refused it. That is ParseableDate's finding to
        //report, not this validator's — two messages for one mistake reads as two
        //mistakes. Answering true here is not laxness: the value cannot pass the
        //chain, because ParseableDate is on every field this one is.
        if (false === $date) {
            if (! is_scalar($value)) {
                $this->setValue('');
                $this->error(self::INVALID);
                return false;
            }
            return true;
        }

        $this->setValue('');

        $day = $date->format('Y-m-d');

        $min = $this->resolve($this->min);
        if (null !== $min && $day < $min) {
            $this->minDisplay = $min;
            $this->error(self::TOO_EARLY);
            return false;
        }

        $max = $this->resolve($this->max);
        if (null !== $max && $day > $max) {
            $this->maxDisplay = $max;
            $this->error(self::TOO_LATE);
            return false;
        }

        return true;
    }

    /**
     * A bound as a Y-m-d string, resolved now.
     *
     * @param  string|null $bound
     * @return string|null
     */
    private function resolve($bound)
    {
        if (! is_string($bound) || '' === $bound) {
            return null;
        }

        try {
            //Deliberately the ambient timezone rather than UTC: 'today' means the
            //day it is where the application runs, and the stored values are
            //dates rather than instants, so the comparison is between calendar
            //days either way.
            $resolved = new \DateTime($bound);
        } catch (\Exception $e) {
            //A misconfigured bound must not take the request down. Failing open
            //here loses one check; throwing would lose the whole submission, and
            //the tests are what catch a bad bound.
            return null;
        }

        return $resolved->format('Y-m-d');
    }
}
