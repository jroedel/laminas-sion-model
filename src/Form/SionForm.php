<?php

declare(strict_types=1);

namespace SionModel\Form;

use Exception;
use JUser\Model\User;
use Laminas\Authentication\AuthenticationServiceInterface;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Filter\StringTrim;
use Laminas\Filter\StripNewlines;
use Laminas\Filter\StripTags;
use Laminas\Filter\ToInt;
use Laminas\Filter\ToNull;
use Laminas\Form\Element\Csrf;
use Laminas\Form\Element\Email;
use Laminas\Form\Element\Hidden;
use Laminas\Form\Element\Select;
use Laminas\Form\Element\Submit;
use Laminas\Form\Element\Textarea;
use Laminas\Form\Form;
use Laminas\Validator\EmailAddress;
use Laminas\Validator\StringLength;
use SionModel\Person\PersonProviderInterface;
use SionModel\Validator\Phone;

use function array_key_exists;
use function html_entity_decode;
use function strrpos;
use function substr;

class SionForm extends Form
{
    protected array $filterSpec = [];

    protected array $phoneInputFilterSpec = [
        'required'   => false,
        'filters'    => [
            ['name' => ToNull::class],
        ],
        'validators' => [
            [
                'name'    => StringLength::class,
                'options' => [
                    'encoding' => 'UTF-8',
                    'max'      => 50,
                ],
            ],
            ['name' => Phone::class],
        ],
    ];

    protected array $phoneLabelInputFilterSpec = [
        'required'   => false,
        'filters'    => [
            ['name' => StripTags::class],
            ['name' => StripNewlines::class],
            ['name' => StringTrim::class],
            ['name' => ToNull::class],
        ],
        'validators' => [
            [
                'name'    => StringLength::class,
                'options' => [
                    'encoding' => 'UTF-8',
                    'max'      => 50,
                ],
            ],
        ],
    ];

    protected bool $isMultiPersonUser = false;

    /**
     * Db adapter used for validators, optional
     */
    protected ?AdapterInterface $adapter;

    public function __construct($name)
    {
        parent::__construct($name);

        $this->add([
            'name'    => 'security',
            'type'    => Csrf::class,
            'options' => [
                'csrf_options' => [
                    'timeout' => 900,
                ],
            ],
        ]);
    }

    public function setInputFilterSpecification(array $spec): static
    {
        $this->filterSpec = $spec;
        return $this;
    }

    public function getInputFilterSpecification(): array
    {
        return $this->filterSpec;
    }

    /**
     * Primes the form for a suggestion. If user is a multi-person-user,
     * it fetches records for the value options of suggestionByPersonId.
     */
    public function prepareForSuggestion(
        User $userIdentity,
        ?PersonProviderInterface $personProvider = null
    ): void {
        $name = $this->getName();
        if (false !== ($lastUnderscore = strrpos($name, '_'))) {
            $name = substr($name, $lastUnderscore + 1);
        }
        $this->setName('suggest_' . $name);
        $this->add([
            'name'       => 'suggestionNotes',
            'type'       => Textarea::class,
            'options'    => [
                'label'    => 'Notes to the reviewer of your suggestion',
                'required' => false,
            ],
            'attributes' => [
                'required' => false,
                'rows'     => 5,
            ],
        ]);
        $this->add([ //only for users with the multiPersonUser bit
            'name'       => 'suggestionByPersonId',
            'type'       => Select::class,
            'options'    => [
                'label'            => 'Your name',
                'empty_option'     => '',
                'unselected_value' => '',
            ],
            'attributes' => [
                'required' => true, //no requirement enforced on server-side, only client
            ],
        ]);
        $this->add([ //only for users with the multiPersonUser bit
            'name'       => 'suggestionByEmail',
            'type'       => Email::class,
            'options'    => [
                'label' => 'Your email',
            ],
            'attributes' => [
                'required'  => true, //no requirement enforced on server-side, only client
                'maxlength' => '70',
            ],
        ]);

        $inputSpec                         = $this->getInputFilterSpecification();
        $inputSpec['suggestionNotes']      = [
            'required' => false,
            'filters'  => [
                ['name' => StripTags::class],
                ['name' => ToNull::class],
            ],
        ];
        $inputSpec['suggestionByPersonId'] = [
            'required' => false,
            'filters'  => [
                ['name' => ToInt::class],
                [
                    'name'    => ToNull::class,
                    'options' => [
                        'type' => ToNull::TYPE_INTEGER,
                    ],
                ],
            ],
        ];
        $inputSpec['suggestionByEmail']    = [
            'required'   => false,
            'filters'    => [
                ['name' => StringTrim::class],
                [
                    'name'    => ToNull::class,
                    'options' => [
                        'type' => ToNull::TYPE_STRING,
                    ],
                ],
            ],
            'validators' => [
                ['name' => EmailAddress::class],
            ],
        ];
        $this->setInputFilterSpecification($inputSpec);

        //prime the suggestionByPersonId if user is multi-person
        if ($userIdentity->getMultiPersonUser()) {
            $this->setIsMultiPersonUser(true);
            if (! isset($personProvider)) {
                /*
                 * Only throw an exception if we actually have a multi-person user as
                 * many apps won't allow them to exist.
                 */
                throw new Exception(
                    'We have a multi-person user, but no `multi_person_user_person_provider` was given'
                );
            }
            $persons = $personProvider->getPersonValueOptions(false, false);
            $this->get('suggestionByPersonId')->setValueOptions($persons);
        }
    }

    public function prepareForModeration($oldData): void
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
                    if (array_key_exists($value, $lookup)) {
                        $helpBlock = "The old value was: " . $lookup[$value]
                           . ' (' . $value . ')';
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
            'name'       => 'suggestionResponse',
            'type'       => Textarea::class,
            'options'    => [
                'label'    => 'Response to the contributor of this suggestion',
                'required' => false,
            ],
            'attributes' => [
                'required' => false,
                'rows'     => 10,
            ],
        ]);
        $this->add([
            'name' => 'suggestionId',
            'type' => Hidden::class,
        ]);
        $this->add([
            'name'       => 'deny',
            'type'       => Submit::class,
            'attributes' => [
                'value' => 'Deny',
                'class' => 'btn-danger',
            ],
        ]);
        $inputSpec                       = $this->getInputFilterSpecification();
        $inputSpec['suggestionResponse'] = [
            'required' => false,
            'filters'  => [
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
     *
     * @see \Laminas\Form\Form::setData()
     *
     * @todo I'm not positive this works 100%, it seemed to decode script tags well,
     * but not apostrophes. I ended up using StripTags instead.
     */
    public function setData(iterable $data)
    {
        $filterSpec           = $this->getInputFilterSpecification();
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

    public function getIsMultiPersonUser(): bool
    {
        return $this->isMultiPersonUser;
    }

    public function setIsMultiPersonUser(bool $isMultiPersonUser): static
    {
        $this->isMultiPersonUser = $isMultiPersonUser;
        return $this;
    }

    public function getAdapter(): AdapterInterface
    {
        if (! isset($this->adapter)) {
            throw new Exception('A db adapter was requested (maybe for validation), but none was injected');
        }
        return $this->adapter;
    }

    public function setAdapter(AdapterInterface $adapter): static
    {
        $this->adapter = $adapter;
        return $this;
    }
}
