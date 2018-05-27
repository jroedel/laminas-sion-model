<?php
namespace SionModel\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

class SuggestForm extends SionForm implements InputFilterProviderInterface
{
    /**
     * These are the possible values for the haystack
     * @var array
     */
    protected $entityHaystack = [];
    
	public function __construct(array $entityHaystack)
	{
		// we want to ignore the name passed
		parent::__construct('suggest');
		$this->setAttribute('data-loading-text', 'Please wait...');
        $this->setEntityHaystack($entityHaystack);
		
		$this->add([
		    'name' => 'entity',
		    'type' => 'Hidden',
		]);
		$this->add([
		    'name' => 'entityId',
		    'type' => 'Hidden',
		]);
		$this->add([
			'name' => 'submit',
			'type' => 'Submit',
			'attributes' => [
				'value' => 'Submit',
				'id' => 'submit',
				'class' => 'btn-primary',
			],
		]);
		$this->add([
			'name' => 'cancel',
			'type' => 'Button',
			'attributes' => [
				'value' => 'Cancel',
				'id' => 'submit',
				'data-dismiss' => 'modal'
// 				'class' => 'btn-danger'
			],
		]);
	}
	
	/**
	 * Set the haystack of acceptable values for the entity field
	 * @return array
	 */
	public function getEntityHaystack()
	{
	    return $this->entityHaystack;
	}
	
	/**
	 * Set the haystack of acceptable values for the entity field
	 * @todo update the input filter if it's already been set
	 * 
	 * @param array $entityHaystack
	 * @return \SionModel\Form\SuggestForm
	 */
	public function setEntityHaystack(array $entityHaystack)
	{
	    $this->entityHaystack = $entityHaystack;
	    return $this;
	}

	public function setInputFilterSpecification($spec)
	{
	    $this->filterSpec = $spec;
	}

	public function getInputFilterSpecification()
	{
	    if ($this->filterSpec) {
	        return $this->filterSpec;
	    }
		$this->filterSpec = [
		    'entity' => [
		        'required' => false,
		        'validators' => [
		            [
		                'name' => 'InArray',
		                'options' => [
		                    'haystack' => $this->entityHaystack,
		                ],
		            ],
		        ],
		        'filters' => [
                    ['name' => 'ToNull'],
		        ],
		    ],
		    'entityId' => [
		        'required' => false,
		        'filters' => [
                    ['name' => 'ToInt'],
                    ['name' => 'ToNull'],
		        ],
		    ],
		];
		return $this->filterSpec;
	}
}
