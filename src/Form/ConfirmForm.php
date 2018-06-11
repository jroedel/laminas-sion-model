<?php
namespace SionModel\Form;

use Zend\Form\Form;

class ConfirmForm extends Form
{
	public function __construct()
	{
		// we want to ignore the name passed
		parent::__construct('confirm');

		$this->add([
	        'name' => 'security',
	        'type' => 'csrf',
	        'options' => [
                'csrf_options' => [
                    'timeout' => 900,
                ],
	        ],
		]);
		$this->add([
			'name' => 'submit',
			'type' => 'Submit',
			'attributes' => [
				'value' => 'Confirm',
				'id' => 'submit',
				'class' => 'btn-primary',
			],
		]);
	}
}
