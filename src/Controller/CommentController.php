<?php
namespace SionModel\Controller;

use SionModel\Db\Model\PredicatesTable;

class CommentController extends SionController
{
    /**
     * 
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::createEntityPostFormValidation()
     */
    public function createEntityPostFormValidation($data, $form)
    {
        //set additional params from the route for the creation of the comment
        $data['kind'] = $this->params()->fromRoute('kind');
        $data['status'] = PredicatesTable::COMMENT_STATUS_PUBLISHED; //@todo get some info from entity spec
        $data['entity'] = $this->params()->fromRoute('entity');
        $data['entityId'] = $this->params()->fromRoute('entity_id');
        return parent::createEntityPostFormValidation($data, $form);
    }
    
    public function redirectAfterCreate($newId, $data = [], $form = null)
    {
        if (isset($data['redirect'])) {
            //@todo confirm that redirect is a valid route
            return $this->redirect()->toUrl($data['redirect']);
        }
        return parent::redirectAfterCreate($newId, $data, $form);
    }
}
