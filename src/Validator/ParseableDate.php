<?php

namespace SionModel\Validator;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Laminas\Validator\AbstractValidator;

use function is_scalar;

/**
 * Validates that a value is a date the application can actually parse.
 *
 * This is the second half of SionModel\Filter\ToDateTime and is close to
 * useless without it. The filter turns a parseable string into a \DateTime and
 * hands back anything it could not parse *unchanged* — it may not throw,
 * because a throw out of a filter escapes InputFilter::isValid() and becomes a
 * 500 with the user's whole submission lost. Returning the input unchanged
 * instead of null is what makes the failure visible here: validators see the
 * filtered value, so a null would be indistinguishable from a field left blank
 * and 'asdf' typed into a date field would be discarded in silence.
 *
 * So the division of labour is:
 *
 *  - already a \DateTimeInterface  -> valid; the filter parsed it.
 *  - null or empty                 -> valid; emptiness is `required`/NotEmpty's
 *                                    business, not ours. Saying otherwise here
 *                                    would make every optional date mandatory.
 *  - a non-empty scalar            -> invalid; with the filter in front this can
 *                                    only be something \DateTime refused. The
 *                                    parse is retried anyway so the validator is
 *                                    also correct used on its own.
 *  - anything else (array, object) -> invalid; `name[]=x` in a POST is enough to
 *                                    produce one.
 *
 * What it deliberately does not do is judge whether a parseable date is
 * *plausible*: 'tomorrow', '+500 years' and the year 9999 all pass. Bounding a
 * date is a per-field decision about what this database records and belongs in
 * that field's specification, not in a general-purpose validator.
 */
class ParseableDate extends AbstractValidator
{
    public const NOT_PARSEABLE = 'dateNotParseable';
    public const INVALID       = 'dateInvalidType';

    /**
     * Neither template interpolates %value%. The rejected value is hostile by
     * definition — a NUL byte, 400 digits, invalid UTF-8 — and the form already
     * shows the user what they typed in the field itself, so echoing it into an
     * error message buys nothing.
     *
     * @var array<string, string>
     */
    protected $messageTemplates = [
        self::NOT_PARSEABLE => 'This does not look like a date. Please enter one like 2020-03-15.',
        self::INVALID       => 'Invalid type given. A date or a date string was expected.',
    ];

    /**
     * Returns true if and only if $value is a date, or is empty.
     *
     * @param  mixed $value
     * @return bool
     */
    public function isValid($value)
    {
        if ($value instanceof DateTimeInterface) {
            return true;
        }

        if (! is_scalar($value)) {
            // null lands here too, and is empty rather than invalid.
            if (null === $value) {
                return true;
            }
            $this->error(self::INVALID);
            return false;
        }

        // Cast first: new \DateTime('') is *now*, so '' has to be caught before
        // any parse is attempted. false casts to '' and means "nothing entered".
        $string = (string) $value;
        if ($string === '') {
            return true;
        }

        $this->setValue($value);

        try {
            new DateTime($string, new DateTimeZone('UTC'));
        } catch (Exception $e) {
            // \DateMalformedStringException on 8.3+, plain \Exception before it.
            $this->error(self::NOT_PARSEABLE);
            return false;
        }

        return true;
    }
}
