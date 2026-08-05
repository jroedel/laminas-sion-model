<?php

namespace SionModel\Validator;

use Laminas\Validator\AbstractValidator;
use SionModel\Filter\DateTimeParser;

use function is_array;
use function is_string;

/**
 * This date may not fall before another field's date.
 *
 * Ordering is where the real integrity of these records lives, and none of it was
 * enforced: an assignment could end before it started, and a person could be
 * ordained before being born. The data happens to be clean today — 0 assignments
 * violate it — so this only prevents future mistakes, which is the cheapest time
 * to add a rule.
 *
 * The chain it implements on persons is
 * `birthDate <= priestDate <= bishopDate <= deathDate`. `bishopDate` is the date
 * of **episcopal** ordination, which in the Catholic church always follows
 * priestly ordination, so its place in the chain is a fact about the subject
 * rather than an assumption about the data.
 *
 * **Both sides have to be present, or there is nothing to compare.** 175 of 325
 * persons have no birth date, and 15 assignments have an end date with no start
 * date — the owner confirmed those are meaningful ("left in 2019, joined we do not
 * know when"). Requiring the earlier field would make every one of those records
 * unsavable, so a blank on either side is valid.
 *
 * Two things about `$context` that are easy to get wrong:
 *
 * It holds the *filtered* value for inputs already processed and the raw one for
 * inputs that have not been, and the order is not guaranteed. So the other side is
 * re-normalised through DateTimeParser rather than assumed to be a \DateTime —
 * otherwise the comparison silently succeeds or fails depending on field order in
 * the specification.
 *
 * The failure is reported on the *later* field only, which is why this validator
 * is one-directional. Putting the mirror rule on the earlier field too would
 * report one mistake as two, on two fields, and leave the editor guessing which
 * to change.
 */
class DateNotBefore extends AbstractValidator
{
    public const TOO_EARLY = 'dateBeforeRelatedDate';

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::TOO_EARLY => 'This date is earlier than the %relatedLabel%, which cannot be. '
            . 'Please check both dates.',
    ];

    /** @var array<string, mixed> */
    protected $messageVariables = [
        'relatedLabel' => 'relatedLabel',
    ];

    /**
     * The name of the field this one must not precede.
     *
     * @var string|null
     */
    protected $field;

    /**
     * How to name that field to a human. Kept separate from `field` because the
     * element name ('priestDate') is not what the message should say
     * ('date of priestly ordination').
     *
     * @var string
     */
    protected $relatedLabel = '';

    /**
     * @param string|null $field
     * @return self
     */
    public function setField($field)
    {
        $this->field = $field;
        return $this;
    }

    /**
     * @param string $relatedLabel
     * @return self
     */
    public function setRelatedLabel($relatedLabel)
    {
        $this->relatedLabel = $relatedLabel;
        return $this;
    }

    /**
     * @param  mixed $value
     * @param  array<string, mixed>|null $context
     * @return bool
     */
    public function isValid($value, $context = null)
    {
        if (! is_string($this->field) || '' === $this->field || ! is_array($context)) {
            return true;
        }

        $mine = DateTimeParser::parse($value);
        if (! $mine instanceof \DateTimeInterface) {
            //Absent, or not a date at all. Either way this is not the validator
            //that should complain — ParseableDate reports an unparseable value and
            //`required` reports a missing one.
            return true;
        }

        $theirs = DateTimeParser::parse($context[$this->field] ?? null);
        if (! $theirs instanceof \DateTimeInterface) {
            return true;
        }

        //Date-only, and inclusive: these are DATE columns, and the same day is
        //legitimate for every pair here. Someone ordained bishop on the
        //anniversary of their priestly ordination is unremarkable; a one-day
        //assignment is not an error.
        if ($mine->format('Y-m-d') >= $theirs->format('Y-m-d')) {
            return true;
        }

        $this->setValue('');
        $this->error(self::TOO_EARLY);
        return false;
    }
}
