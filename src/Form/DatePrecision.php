<?php

namespace SionModel\Form;

use SionModel\I18n\View\Helper\DatePrecisionFormat;

/**
 * The element definition and the input filter entry for a date-precision field,
 * in one place.
 *
 * Eight date fields across four forms need the same pair, and they need it to
 * stay the same. Both halves are here because in laminas-form they are separate
 * and easy to get half-right in ways nothing reports:
 *
 * - A `Select`'s value_options give it an InArray validator, but a form's
 *   `getInputFilterSpecification()` **overwrites** the element-derived input by
 *   name, so naming the field there without restating the validator silently
 *   removes the only thing stopping an arbitrary string. 93 choice fields in this
 *   application have lost their InArray exactly that way.
 * - An element left out of the specification entirely gets
 *   `['required' => false]` and nothing else — no filter, no validator, raw
 *   input straight through.
 *
 * So callers use both: element() in the constructor, filterSpec() in the
 * specification. The domain is stated once, in
 * DatePrecisionFormat::PRECISIONS, which is also what does the rendering — the
 * value written and the value understood cannot drift apart.
 */
final class DatePrecision
{
    /**
     * Matches the columns' DEFAULT and events.StartDatePrecision's existing
     * behaviour: a date with nothing said about it is a date known to the day.
     */
    public const DEFAULT_PRECISION = DatePrecisionFormat::DAY;

    /**
     * The labels are English source strings; the view translates them, as it does
     * every other label in these forms.
     *
     * @return array<string, string>
     */
    public static function valueOptions()
    {
        return [
            DatePrecisionFormat::DAY   => 'Exact day',
            DatePrecisionFormat::MONTH => 'Month and year only',
            DatePrecisionFormat::YEAR  => 'Year only',
        ];
    }

    /**
     * @param string $name  The precision field's own name, e.g. 'birthDatePrecision'.
     * @param string $label
     * @return array<string, mixed>
     */
    public static function element($name, $label = 'Date precision')
    {
        return [
            'name' => $name,
            'type' => 'Select',
            'options' => [
                'label' => $label,
                'value_options' => self::valueOptions(),
                //No empty_option, and an empty submitted value is rejected rather
                //than coerced: the select always sends one of the three, so ''
                //means something went wrong and silently turning it into 'day'
                //would hide that.
                //
                //A field *absent* from the post is a different case, and the two
                //sentences that used to stand here about it were wrong — measured
                //2026-08-15, by an insert that failed. They claimed an absent
                //optional field "never reaches getData()", so an edit would leave
                //the stored precision alone and a create would fall through to the
                //column's own DEFAULT. Neither happens.
                //`BaseInputFilter::setData()` gives every input it holds a value
                //whether or not the data mentions it, so an absent field arrives in
                //getData() as **null** and both createHelper() and updateHelper()
                //write it. These four columns are NOT NULL, so the write fails:
                //`Column 'PriestDatePrecision' cannot be null`. A template that
                //does not render one of these selects must round-trip it in a
                //hidden input — see templates/schoenstatt/_person-fields.html.twig,
                //and test/Integration/PortedTemplatesRenderEveryNotNullFieldTest,
                //which fails when one stops doing so.
                //
                //filterSpec() owns the domain check, so the Select's automatic
                //one is turned off to leave exactly one. Normally this flag is a
                //smell — it is how 93 choice fields in this application ended up
                //with no server-side domain check at all — but here it is the
                //opposite: the specification's InArray survives both construction
                //paths (a bare `new Form()`, where the spec overwrites the
                //element's input, and a container-built form, where the two
                //merge), whereas the element's only survives one. Without the
                //flag the merged case validates twice and reports the same
                //failure twice.
                'disable_inarray_validator' => true,
            ],
            'attributes' => [
                'required' => false,
                'value' => self::DEFAULT_PRECISION,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function filterSpec()
    {
        return [
            'required' => false,
            'filters' => [
                ['name' => 'SionModel\Filter\StringTrim'],
            ],
            'validators' => [
                [
                    //Restated rather than inherited from the element, because the
                    //form specification would otherwise overwrite it away.
                    'name' => 'SionModel\Validator\InArray',
                    'options' => [
                        'haystack' => DatePrecisionFormat::PRECISIONS,
                        'strict'   => \SionModel\Validator\InArray::COMPARE_STRICT,
                    ],
                ],
            ],
        ];
    }
}
