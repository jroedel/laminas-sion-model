<?php
namespace SionModel\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

class ModerateGenericForm extends Form implements InputFilterProviderInterface
{
	public function __construct($name = null)
	{
		// we want to ignore the name passed
		parent::__construct('moderate_generic');
// 		$this->setAttribute('method', 'GET');
        $this->setAttribute('style', 'display: inline;');
		$this->add(array(
		    'name' => 'suggestionId',
		    'type' => 'Hidden',
		));
		$this->add(array(
		    'name' => 'suggestionResponse',
		    'type' => 'Textarea',
		    'options' => array(
		        'label' => 'Notes to the reviewer of your suggestion',
		        'required' => false,
		    ),
		    'attributes' => array(
		        'required' => false,
		        'rows' => 10,
		    ),
		));
		$this->add(array(
			'name' => 'security',
			'type' => 'csrf',
		    'options' => array(
                'csrf_options' => array(
                     'timeout' => 600,
                ),
	        ),
		));
		$this->add(array(
		    'name' => 'accept',
		    'type' => 'Submit',
		    'attributes' => array(
		        'value' => 'Accept',
		        'class' => 'btn-success'
		    ),
		));
		$this->add(array(
		    'name' => 'deny',
		    'type' => 'Submit',
		    'attributes' => array(
		        'value' => 'Deny',
		        'class' => 'btn-danger'
		    ),
		));
	}

	public function getInputFilterSpecification()
	{
		return array(
		    'suggestionResponse' => array(
				'required' => false,
                'filters' => array(
                    array('name' => 'StripTags'),
                    array('name' => 'ToNull'),
                ),
		    ),
	        'suggestionId' => array(
				'required' => false,
                'filters' => array(
                    array('name' => 'ToInt'),
                    array('name' => 'ToNull'),
                ),
			),
		);
	}
}