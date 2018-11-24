<?php
namespace SionModel\Controller;

class CommentController extends SionController
{
    public function getPostDataForCreateAction()
    {
        $data = $this->getRequest()->getPost()->toArray();
        $data['kind'] = $this->params()->fromRoute('kind');
        $data['entity'] = $this->params()->fromRoute('entity');
        $data['entityId'] = $this->params()->fromRoute('entity_id');
        return $data;
    }
}
