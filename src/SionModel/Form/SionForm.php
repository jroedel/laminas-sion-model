<?php
namespace SionModel\Form;

use Zend\Form\Form;
use Zend\Form\Element\Select;
use Zend\Filter\ToNull;
use Zend\ServiceManager\ServiceLocatorInterface;

class SionForm extends Form
{
    protected $filterSpec;
    protected $phoneInputFilterSpec;
    protected $phoneLabelInputFilterSpec;

    protected $isMultiPersonUser = false;

    public function __construct($name)
    {
        //@todo just added this line 2016-08-22, should check for regression error in EditCourseForm
		parent::__construct($name);

		$this->add([
		    'name' => 'security',
		    'type' => 'csrf',
		    'options' => [
		        'csrf_options' => [
		            'timeout' => 600,
		        ],
		    ],
		]);

        $this->phoneInputFilterSpec = [
			'required' => false,
			'filters'  => [
	            ['name' => 'ToNull'],
			],
			'validators' => [
                [
                    'name' => 'StringLength',
                    'options' => [
                        'encoding' => 'UTF-8',
                        'max' => 50,
                    ],
                ],
				['name' => 'SionModel\Validator\Phone'],
			],
		];

        $this->phoneLabelInputFilterSpec = [
            'required' => false,
            'filters' => [
                ['name' => 'StripTags'],
                ['name' => 'StripNewlines'],
                ['name' => 'StringTrim'],
                ['name' => 'ToNull'],
            ],
            'validators' => [
                [
                    'name' => 'StringLength',
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
	 * it fetches records for the value options of suggestionByPersonId
	 * @param ServiceLocatorInterface $serviceLocator
	 */
	public function prepareForSuggestion($serviceLocator)
	{
	    $name = $this->getName();
	    if (false !== ($lastUnderscore = strrpos($name, '_'))) {
	        $name = substr($name, $lastUnderscore + 1);
	    }
	    $this->setName('suggest_'.$name);
	    $this->add([
	        'name' => 'suggestionNotes',
	        'type' => 'Textarea',
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
		    'type' => 'Select',
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
		    'type' => 'Email',
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
	            ['name' => 'StripTags'],
	            ['name' => 'ToNull'],
	        ],
	    ];
	    $inputSpec['suggestionByPersonId'] = [
			'required' => false,
            'filters' => [
                ['name' => 'ToInt'],
	            ['name' => 'ToNull',
	                'options' => [
                        'type' => ToNull::TYPE_INTEGER,
                    ],
	            ],
            ],
		];
		$inputSpec['suggestionByEmail'] = [
	        'required' => false,
	        'filters' => [
	            ['name' => 'StringTrim'],
	            ['name' => 'ToNull',
	                'options' => [
                        'type' => ToNull::TYPE_STRING,
                    ]
	            ],
	        ],
	        'validators' => [
	            ['name' => 'EmailAddress'],
	        ],
	    ];
		$this->setInputFilterSpecification($inputSpec);

		//@todo make this work without dependency on the PatresTable service!
        //prime the suggestionByPersonId if user is multi-person
		$authService = $serviceLocator->get('zfcuser_auth_service');
		if ($authService->hasIdentity() && $authService->getIdentity()->multiPersonUser) {
            $table = $serviceLocator->get ( 'Patres\Model\PatresTable' );
		    $this->setIsMultiPersonUser(true);
		    $persons = $table->getPersonValueOptions(false, false);
		    $this->get('suggestionByPersonId')->setValueOptions($persons);
		}
	}

	public function prepareForModeration($oldData)
	{
	    $name = $this->getName();
	    if (false !== ($lastUnderscore = strrpos($name, '_'))) {
	        $name = substr($name, $lastUnderscore + 1);
	    }
	    $this->setName('moderate_'.$name);
        $this->get('submit')->setAttribute('value', 'Accept');
        $this->get('submit')->setAttribute('class', 'btn-success');
	    //set the help block to show the old values
	    foreach ($oldData as $field => $value) {
	        if ($this->has($field)) {
	            if (($element = $this->get($field)) instanceof Select)
	            {
	                $lookup = $element->getValueOptions();
	                if (key_exists($value, $lookup)) {
    	               $helpBlock = "The old value was: ".$lookup[$value].
    	                   ' ('.$value.')';
	                } else {
    	               $helpBlock = "The old value was: ".$value;
	                }
	            } else {
    	            $helpBlock = "The old value was: ".$value;
	            }
	            $this->get($field)
	               ->setOption('help-block', $helpBlock)
	               ->setOption('validation-state', 'warning');
	        }
	    }
	    $this->add([
	        'name' => 'suggestionResponse',
	        'type' => 'Textarea',
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
		    'type' => 'Hidden',
		]);
		$this->add([
			'name' => 'deny',
			'type' => 'Submit',
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

	public function setData($data)
	{
	    $filterSpec = $this->getInputFilterSpecification();
	    $htmlEntitiesElements = [];
	    foreach ($filterSpec as $key => $value) {
	        if (isset($value['filters'])) {
	            foreach ($value['filters'] as $filterArray) {
	                if ($filterArray['name'] == 'HtmlEntities') {
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
