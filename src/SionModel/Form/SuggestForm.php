<?php
namespace SionModel\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

class SuggestForm extends SionForm implements InputFilterProviderInterface
{
	public function __construct()
	{
		// we want to ignore the name passed
		parent::__construct('suggest');
		$this->setAttribute('data-loading-text', 'Please wait...');

		$this->add(array(
		    'name' => 'entity',
		    'type' => 'Hidden',
		));
		$this->add(array(
		    'name' => 'entityId',
		    'type' => 'Hidden',
		));
		$this->add(array(
			'name' => 'submit',
			'type' => 'Submit',
			'attributes' => array(
				'value' => 'Submit',
				'id' => 'submit',
				'class' => 'btn-primary',
			),
		));
		$this->add(array(
			'name' => 'cancel',
			'type' => 'Button',
			'attributes' => array(
				'value' => 'Cancel',
				'id' => 'submit',
				'data-dismiss' => 'modal'
// 				'class' => 'btn-danger'
			),
		));
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
		$this->filterSpec = array(
		    'entity' => array(
		        'required' => false,
		        'validators' => array(
		            array(
		                'name' => 'InArray',
		                'options' => array(
		                    'haystack' => array( //@todo make this list automatic
// 		                        'filiation',
// 		                        'generation',
// 		                        'house',
// 		                        'person',
// 		                        'person-misc',
		                    ),
		                ),
		            ),
		        ),
		        'filters' => array(
                    array('name' => 'ToNull'),
		        ),
		    ),
		    'entityId' => array(
		        'required' => false,
		        'filters' => array(
                    array('name' => 'ToInt'),
                    array('name' => 'ToNull'),
		        ),
		    ),
		);
		return $this->filterSpec;
	}
}
