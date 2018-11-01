<?php
namespace SionModel\Form;

use Zend\Form\Form;
use Zend\InputFilter\InputFilterProviderInterface;

class DeleteEntityForm extends Form implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('entity_delete');

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
                'value' => 'Delete',
                'id' => 'submit',
                'class' => 'btn-danger'
            ],
        ]);
        $this->add([
            'name' => 'cancel',
//          'type' => 'Submit',
            'attributes' => [
                'value' => 'Cancel',
                'id' => 'submit',
                'data-dismiss' => 'modal'
//              'class' => 'btn-danger'
            ],
        ]);
    }

    public function getInputFilterSpecification()
    {
        return [
//          'entityId' => [
//              'required' => true,
//              'validators' => [
//                  [
//                      'name'    => 'Zend\Validator\Db\RecordExists',
//                      'options' => [
//                          'table' => $this->tableName,
//                          'field' => $this->tableKey,
//                          'adapter' => \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter(),
//                          'messages' => [
//                              \Zend\Validator\Db\RecordExists::ERROR_NO_RECORD_FOUND => 'Entity not found in database'
//                          ],
//                      ],
//                  ],
//              ],
//          ],
        ];
    }
}
