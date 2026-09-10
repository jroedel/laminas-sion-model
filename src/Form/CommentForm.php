<?php

namespace SionModel\Form;

use Laminas\InputFilter\InputFilterProviderInterface;

/**
 * The form every entity show page renders under its comment list.
 *
 * ## The filter spec named a field that does not exist
 *
 * Until 2026-09-10 `getInputFilterSpecification()` returned rules for `text`. There is
 * no `text` element on this form and no `text` column on the `comment` entity — the body
 * is `comment`, and `comment` had no spec entry at all. laminas gives an element with no
 * spec entry an input with `required => false` and nothing else, so the entire form was
 * unfiltered and unvalidated while looking as though it were not. Measured on the
 * assembled `getInputFilter()`:
 *
 *     security     filters: StringTrim            validators: Csrf
 *     comment      filters: (none)                validators: (none)
 *     redirect     filters: (none)                validators: (none)
 *     text         filters: StripTags, ToNull     validators: (none)
 *
 * Three consequences, all reproduced before this was changed:
 *
 *   1. An empty textarea validated, and `getValues()` returned `''` rather than absent,
 *      so `SionTable::createHelper()`'s `isset($data['comment'])` check passed and the
 *      submission inserted a **published, empty comment row** linked to the entity.
 *   2. `comments.Comment` is `varchar(500)` and the server runs `STRICT_TRANS_TABLES`,
 *      so a 501-character body was an uncaught SQLSTATE exception — a 500 page, not a
 *      validation message.
 *   3. Markup passed through verbatim. That was never an XSS hole (the list template
 *      prints `{{ object.comment }}`, which Twig escapes), but `StripTags` is what the
 *      author asked for and it had been landing on nothing.
 *
 * ## Why `comment` is required
 *
 * Because the alternative is worse in both directions. Adding `ToNull` without
 * `required` turns an empty body into `null`, which fails `createHelper()`'s
 * `isset()` on a `required_columns_for_creation` entry and raises
 * `InvalidArgumentException` — trading a junk row for a 500. Requiring the field
 * rejects the submission in the form, where a rejection can be reported.
 *
 * ## Why the bound is 500 and not a rounder number
 *
 * It is the column. A validator that disagrees with the schema either rejects rows the
 * database would have taken or admits rows it will not, and the second is the failure
 * that reaches a visitor as a 500. The element carries the same number as `maxlength`
 * so the browser refuses first and nobody loses a long comment to a round trip.
 */
class CommentForm extends SionForm implements InputFilterProviderInterface
{
    /** `comments`.`Comment` is varchar(500). */
    public const COMMENT_MAX_LENGTH = 500;

    /**
     * `redirect` is not persisted and not a column; the bound exists only so the field
     * is not an unbounded string. 2048 is the conventional URL ceiling and every value
     * this form actually produces is a site-relative path an order of magnitude shorter.
     */
    public const REDIRECT_MAX_LENGTH = 2048;

    public function __construct()
    {
        parent::__construct('comment');

        $this->add([//http://www.codingdrama.com/bootstrap-markdown/
            'name' => 'comment',
            'type' => 'Textarea',
            'options' => [
                'label' => 'Leave a comment',
            ],
            'attributes' => [
                'required' => false,
//                 'data-provide' => 'markdown',
//                 'data-parser' => 'CommonMark',
                'rows' => 3,
                'maxlength' => self::COMMENT_MAX_LENGTH,
            ],
        ]);
        $this->add([
            'name' => 'redirect',
            'type' => 'Hidden',
        ]);
//We won't allow editing the legacy columns
        $this->add([
            'name' => 'submit',
            'type' => 'Submit',
            'attributes' => [
                'value' => 'Submit',
                'id' => 'submit',
                'class' => 'btn-primary'
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [
            'security' => CsrfSpec::forElement($this->get('security')),
            'comment' => [
                'required' => true,
                'filters' => [
                    ['name' => 'StripTags'],
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'StringLength',
                        'options' => [
                            'max' => self::COMMENT_MAX_LENGTH,
                        ],
                    ],
                ],
            ],
            'redirect' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => 'ToNull',
                        'options' => [
                            'type' => \Laminas\Filter\ToNull::TYPE_STRING,
                        ],
                    ],
                ],
                'validators' => [
                    ['name' => 'StringLength',
                        'options' => [
                            'max' => self::REDIRECT_MAX_LENGTH,
                        ],
                    ],
                ],
            ],
        ];
    }
}
