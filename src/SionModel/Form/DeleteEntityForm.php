<?php
namespace Patres\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

class DeleteEntityForm extends Form implements InputFilterProviderInterface
{
    /**
     * The name of the table from which to delete
     * @var string
     */
    protected $tableName;
    
    /**
     * The primary key column of the table to make sure it exists before deleting.
     * @var string
     */
    protected $tableKey;
    
    /**
     * Get the tableKey value
     * @return string
     */
    public function getTableKey()
    {
        return $this->tableKey;
    }
   
    /**
     * 
     * @param string $tableKey
     * @return self
     */
    public function setTableKey($tableKey)
    {
        $this->tableKey = $tableKey;
        return $this;
    }
    
    /**
    * Get the tableName value
    * @return string
    */
    public function getTableName()
    {
        return $this->tableName;
    }
    
    /**
    * 
    * @param string $tableName
    * @return self
    */
    public function setTableName($tableName)
    {
        $this->tableName = $tableName;
        return $this;
    }
    
	public function __construct($tableName, $tableKey)
	{
		parent::__construct('delete_entity');
		
		$this->tableName = $tableName;
		$this->tableKey = $tableKey;
		
		$this->add([
			'name' => 'entityId',
			'type' => 'Hidden',
		]);
		$this->add([
			'name' => 'security',
			'type' => 'csrf',
		    'options' => [
                'csrf_options' => [
                     'timeout' => 600,
                ],
	        ],
		]);
		$this->add([
			'name' => 'delete',
			'type' => 'Submit',
			'attributes' => [
				'value' => 'Delete',
				'id' => 'submit',
				'class' => 'btn-danger'
			],
		]);
		$this->add([
			'name' => 'cancel',
// 			'type' => 'Submit',
			'attributes' => [
				'value' => 'Cancel',
				'id' => 'submit',
				'data-dismiss' => 'modal'
// 				'class' => 'btn-danger'
			],
		]);
		
	}
	
	public function getInputFilterSpecification()
	{
		return [
			'entityId' => [
				'required' => true,
	            'validators' => [
	                [
	                    'name'    => 'Zend\Validator\Db\RecordExists',
	                    'options' => [
	                        'table' => $this->tableName,
	                        'field' => $this->tableKey,
	                        'adapter' => \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter(),
	                        'messages' => [
	                            \Zend\Validator\Db\RecordExists::ERROR_NO_RECORD_FOUND => 'Entity not found in database' 
	                        ],
	                    ],
	                ],
	            ],
			],
		];
	}
}
