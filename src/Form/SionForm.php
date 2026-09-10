<?php

namespace SionModel\Form;

use SionModel\Filter\DateTimeParser;
use Laminas\Form\Element\DateSelect;
use Laminas\Form\Form;
use Laminas\Filter\ToNull;
use Laminas\Validator\StringLength;
use SionModel\Validator\Phone;
use Laminas\Filter\StripTags;
use Laminas\Filter\StripNewlines;
use Laminas\Filter\StringTrim;
use Laminas\Form\Element\Csrf;
use Laminas\InputFilter\InputFilterProviderInterface;

class SionForm extends Form implements InputFilterProviderInterface
{
    protected $filterSpec;
    protected $phoneInputFilterSpec;
    protected $phoneLabelInputFilterSpec;

    protected $isMultiPersonUser = false;
    
    /**
     * Db adapter used for validators, optional
     * @var \Laminas\Db\Adapter\AdapterInterface $adapter
     */
    protected $adapter;

    public function __construct($name)
    {
        parent::__construct($name);

        $this->add([
            'name' => 'security',
            'type' => Csrf::class,
            'options' => [
                'csrf_options' => [
                    'timeout' => 900,
                ],
            ],
        ]);

        $this->phoneInputFilterSpec = [
            'required' => false,
            'filters'  => [
                ['name' => ToNull::class],
            ],
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'encoding' => 'UTF-8',
                        'max' => 50,
                    ],
                ],
                ['name' => Phone::class],
            ],
        ];

        $this->phoneLabelInputFilterSpec = [
            'required' => false,
            'filters' => [
                ['name' => StripTags::class],
                ['name' => StripNewlines::class],
                ['name' => StringTrim::class],
                ['name' => ToNull::class],
            ],
            'validators' => [
                [
                    'name' => StringLength::class,
                    'options' => [
                        'encoding' => 'UTF-8',
                        'max' => 50,
                    ],
                ],
            ],
        ];
    }

    /**
     * The CSRF token, which every form built on this class carries.
     *
     * Subclasses override this and must spread the same entry in — `CsrfSpec` exists so
     * that it is one expression rather than a copied literal, and so that it reads the
     * element instead of guessing at its options. Stated rather than left to
     * `Laminas\Form\Element\Csrf`'s own input specification because
     * `SionModel\Form\Validation\InputFilter` reads the specification and nothing else:
     * at step 5 a check that lives only on the element is a check that disappears.
     *
     * @return array<string, mixed>
     */
    public function getInputFilterSpecification()
    {
        return [
            'security' => CsrfSpec::forElement($this->get('security')),
        ];
    }

    public function setInputFilterSpecification($spec)
    {
        $this->filterSpec = $spec;
    }

    /**
     * Normally setData is called on an edit action. This will automatically decode html
     * entity fields to prevent entities from being double-encoded.
     * {@inheritDoc}
     * @see \Laminas\Form\Form::setData()
     * @todo I'm not positive this works 100%, it seemed to decode script tags well,
     * but not apostrophes. I ended up using StripTags instead.
     */
    public function setData($data)
    {
        $data = $this->blankUnusableDateSelectValues($data);

        $filterSpec = $this->getInputFilterSpecification();
        $htmlEntitiesElements = [];
        foreach ($filterSpec as $key => $value) {
            if (isset($value['filters'])) {
                foreach ($value['filters'] as $filterArray) {
                    if ($filterArray['name'] === 'HtmlEntities') {
                        $htmlEntitiesElements[] = $key;
                        break;
                    }
                }
            }
        }
        foreach ($htmlEntitiesElements as $element) {
            //is_string, not isset: html_entity_decode() has a string parameter
            //type, so an array reaching it is a TypeError — and this runs in
            //setData(), before isValid(), so no validator could reject the value
            //first and no controller could guard it by checking isValid(). A
            //request sending `contactNotes[]=x` was a 500 with the whole
            //submission lost. A non-string is left alone here and rejected
            //downstream by the field's own validators.
            if (isset($data[$element]) && is_string($data[$element]) && $this->has($element)) {
                $data[$element] = html_entity_decode($data[$element]);
            }
        }
        return parent::setData($data);
    }

    /**
     * Replace anything a DateSelect element cannot swallow with an empty value.
     *
     * These elements are the one place where hostile input escapes *before*
     * isValid() can answer, so nothing downstream can catch it — not a validator,
     * not a controller checking isValid(). Laminas\Form\Element\DateSelect::setValue()
     * is reached from Fieldset::setValue() inside Form::setData(), and it throws
     * three different ways: InvalidArgumentException ("Value should be a parsable
     * string or an instance of DateTime") for 'asdf'; ValueError from
     * DateTime::createFromFormat() for a value carrying a NUL byte; and
     * Laminas\Filter\Exception\RuntimeException ("There are not enough values in
     * the array to filter this date") for an array missing year/month/day. Each
     * was a 500 with the user's whole submission lost.
     *
     * Blanking rather than reporting is the right trade specifically for these
     * elements: the widget is three selects, so a value it cannot parse cannot
     * have been produced by using the form — only by a crafted request. There is
     * no user to show a message to, and `required` still reports the field as
     * missing if it was mandatory.
     *
     * Done here rather than in one form so that adding a DateSelect anywhere does
     * not reintroduce the same 500.
     *
     * @param  array<string, mixed>|\Traversable $data
     * @return array<string, mixed>|\Traversable
     */
    private function blankUnusableDateSelectValues($data)
    {
        if (! is_array($data)) {
            return $data;
        }

        foreach ($data as $name => $value) {
            if (! is_string($name) || ! $this->has($name)) {
                continue;
            }
            if (! $this->get($name) instanceof DateSelect) {
                continue;
            }
            if ($this->isUsableDateSelectValue($value)) {
                continue;
            }
            $data[$name] = '';
        }

        return $data;
    }

    /**
     * @param  mixed $value
     * @return bool
     */
    private function isUsableDateSelectValue($value)
    {
        if (null === $value || '' === $value || $value instanceof \DateTimeInterface) {
            return true;
        }

        //The element's own shape: the three selects post year/month/day. Laminas
        //throws when one is missing rather than treating it as empty.
        if (is_array($value)) {
            return isset($value['year'], $value['month'], $value['day'])
                && is_scalar($value['year'])
                && is_scalar($value['month'])
                && is_scalar($value['day']);
        }

        //Anything else has to be a string the application would accept as a date
        //anywhere else, which is exactly what DateTimeParser decides — reused so
        //that a value rejected here and a value rejected by a date field's
        //validators cannot disagree.
        return DateTimeParser::parse($value) instanceof \DateTimeInterface;
    }

    public function getIsMultiPersonUser()
    {
        return $this->isMultiPersonUser;
    }

    public function setIsMultiPersonUser($isMultiPersonUser)
    {
        $this->isMultiPersonUser = $isMultiPersonUser;
        return $this;
    }
    
    public function getAdapter()
    {
        if (! isset($this->adapter)) {
            throw new \Exception('A db adapter was requested (maybe for validation), but none was injected');
        }
        return $this->adapter;
    }
    
    public function setAdapter(\Laminas\Db\Adapter\AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        return $this;
    }
}
