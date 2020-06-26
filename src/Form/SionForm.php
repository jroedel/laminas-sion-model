<?php

namespace SionModel\Form;

use Zend\Form\Form;
use Zend\Form\Element\Select;
use Zend\Filter\ToNull;
use Zend\Authentication\AuthenticationServiceInterface;
use SionModel\Person\PersonProviderInterface;
use Zend\Validator\StringLength;
use SionModel\Validator\Phone;
use Zend\Filter\StripTags;
use Zend\Filter\StripNewlines;
use Zend\Filter\StringTrim;
use Zend\Form\Element\Csrf;
use Zend\Form\Element\Textarea;
use Zend\Form\Element\Email;
use Zend\Filter\ToInt;
use Zend\Form\Element\Hidden;
use Zend\Form\Element\Submit;
use Zend\Validator\EmailAddress;

class SionForm extends Form
{
    protected $filterSpec;
    protected $phoneInputFilterSpec;
    protected $phoneLabelInputFilterSpec;

    protected $isMultiPersonUser = false;

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

    public function setInputFilterSpecification($spec)
    {
        $this->filterSpec = $spec;
    }

    /**
     * Primes the form for a suggestion. If user is a multi-person-user,
     * it fetches records for the value options of suggestionByPersonId.
     *
     * @param AuthenticationServiceInterface $authService
     * @param PersonProviderInterface $personProvider
     */
    public function prepareForSuggestion(
        AuthenticationServiceInterface $authService,
        PersonProviderInterface $personProvider = null
    ) {
        $name = $this->getName();
        if (false !== ($lastUnderscore = strrpos($name, '_'))) {
            $name = substr($name, $lastUnderscore + 1);
        }
        $this->setName('suggest_' . $name);
        $this->add([
            'name' => 'suggestionNotes',
            'type' => Textarea::class,
            'options' => [
                'label' => 'Notes to the reviewer of your suggestion',
                'required' => false,
            ],
            'attributes' => [
                'required' => false,
                'rows' => 5,
            ],
        ]);
        $this->add([ //only for users with the multiPersonUser bit
            'name' => 'suggestionByPersonId',
            'type' => Select::class,
            'options' => [
                'label' => 'Your name',
                'empty_option' => '',
                'unselected_value' => '',
            ],
            'attributes' => [
                'required' => true, //no requirement enforced on server-side, only client
            ],
        ]);
        $this->add([ //only for users with the multiPersonUser bit
            'name' => 'suggestionByEmail',
            'type' => Email::class,
            'options' => [
                'label' => 'Your email',
            ],
            'attributes' => [
                'required' => true, //no requirement enforced on server-side, only client
                'maxlength' => '70',
            ],
        ]);

        $inputSpec = $this->getInputFilterSpecification();
        $inputSpec['suggestionNotes'] = [
            'required' => false,
            'filters' => [
                ['name' => StripTags::class],
                ['name' => ToNull::class],
            ],
        ];
        $inputSpec['suggestionByPersonId'] = [
            'required' => false,
            'filters' => [
                ['name' => ToInt::class],
                ['name' => ToNull::class,
                    'options' => [
                        'type' => ToNull::TYPE_INTEGER,
                    ],
                ],
            ],
        ];
        $inputSpec['suggestionByEmail'] = [
            'required' => false,
            'filters' => [
                ['name' => StringTrim::class],
                ['name' => ToNull::class,
                    'options' => [
                        'type' => ToNull::TYPE_STRING,
                    ]
                ],
            ],
            'validators' => [
                ['name' => EmailAddress::class],
            ],
        ];
        $this->setInputFilterSpecification($inputSpec);

        //prime the suggestionByPersonId if user is multi-person
        if ($authService->hasIdentity() && $authService->getIdentity()->multiPersonUser) {
            $this->setIsMultiPersonUser(true);
            if (! isset($personProvider)) {
                /*
                 * Only throw an exception if we actually have a multi-person user as
                 * many apps won't allow them to exist.
                 */
                throw new \Exception(
                    'We have a multi-person user, but no `multi_person_user_person_provider` was given'
                    );
            }
            $persons = $personProvider->getPersonValueOptions(false, false);
            $this->get('suggestionByPersonId')->setValueOptions($persons);
        }
    }

    public function prepareForModeration($oldData)
    {
        $name = $this->getName();
        if (false !== ($lastUnderscore = strrpos($name, '_'))) {
            $name = substr($name, $lastUnderscore + 1);
        }
        $this->setName('moderate_' . $name);
        $this->get('submit')->setAttribute('value', 'Accept');
        $this->get('submit')->setAttribute('class', 'btn-success');
        //set the help block to show the old values
        foreach ($oldData as $field => $value) {
            if ($this->has($field)) {
                if (($element = $this->get($field)) instanceof Select) {
                    $lookup = $element->getValueOptions();
                    if (key_exists($value, $lookup)) {
                        $helpBlock = "The old value was: " . $lookup[$value] .
                           ' (' . $value . ')';
                    } else {
                        $helpBlock = "The old value was: " . $value;
                    }
                } else {
                    $helpBlock = "The old value was: " . $value;
                }
                $this->get($field)
                   ->setOption('help-block', $helpBlock)
                   ->setOption('validation-state', 'warning');
            }
        }
        $this->add([
            'name' => 'suggestionResponse',
            'type' => Textarea::class,
            'options' => [
                'label' => 'Response to the contributor of this suggestion',
                'required' => false,
            ],
            'attributes' => [
                'required' => false,
                'rows' => 10,
            ],
        ]);
        $this->add([
            'name' => 'suggestionId',
            'type' => Hidden::class,
        ]);
        $this->add([
            'name' => 'deny',
            'type' => Submit::class,
            'attributes' => [
                'value' => 'Deny',
                'class' => 'btn-danger'
            ],
        ]);
        $inputSpec = $this->getInputFilterSpecification();
        $inputSpec['suggestionResponse'] = [
            'required' => false,
            'filters' => [
                ['name' => 'StripTags'],
                ['name' => 'ToNull'],
            ],
        ];
        $this->setInputFilterSpecification($inputSpec);
    }

    /**
     * Normally setData is called on an edit action. This will automatically decode html
     * entity fields to prevent entities from being double-encoded.
     * {@inheritDoc}
     * @see \Zend\Form\Form::setData()
     * @todo I'm not positive this works 100%, it seemed to decode script tags well,
     * but not apostrophes. I ended up using StripTags instead.
     */
    public function setData($data)
    {
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
            if (isset($data[$element]) && $this->has($element)) {
                $data[$element] = html_entity_decode($data[$element]);
            }
        }
        return parent::setData($data);
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
}
