<?php
/**
 * Zend Framework (http://framework.zend.com/)
*
* @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
* @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
* @license   http://framework.zend.com/license/new-bsd New BSD License
*/

namespace SionModel\Controller;

use Zend\View\Model\ViewModel;
use Zend\Mvc\Controller\AbstractActionController;
use SionModel\Db\Model\SionTable;
use SionModel\Service\EntitiesService;
use SionModel;
use SionModel\Form\SionForm;
use SionModel\Entity\Entity;
use Zend\Mvc\Controller\Plugin\FlashMessenger;
use JTranslate\Controller\Plugin\NowMessenger;

class SionController extends AbstractActionController
{
    /**
     * @var Entity $entitySpecification
     */
    protected $entitySpecification;

    /**
     * @var SionTable $sionTable
     */
    protected $sionTable;


    /**
    * @var string $entity
    */
    protected $entity;

    /**
    * @var defaultRedirectRoute $defaultRedirectRoute
    */
    protected $defaultRedirectRoute;

    /**
    * @var array $sionModelConfig
    */
    protected $sionModelConfig;

    /**
     * @param string $entity
     * @throws \Exception
     */
    public function __construct($entity = null)
    {
        $this->setEntity($entity);
    }

    public function createAction()
    {
        $sm = $this->getServiceLocator ();
        /** @var SionTable $table **/
        $table = $this->getSionTable();
        $entity = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();

        if (is_null($entitySpec->createActionForm)) {
            throw new \InvalidArgumentException('If the createAction for \''.$entity.'\' is to be used, it must specify the create_action_form configuration.');
        }
        /** @var SionForm $form **/
        if ($sm->has($entitySpec->createActionForm)) {
            $form = $sm->get($entitySpec->createActionForm);
        } elseif (class_exists($entitySpec->createActionForm)) {
            //@todo test this line
            $form = new $entitySpec->createActionForm;
        } else {
            throw new \InvalidArgumentException('Invalid create_action_form specified for \''.$entity.'\' entity.');
        }

        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                if (!($newId = $table->createEntity($entity, $data))) {
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                } else {
                    $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )
                        ->addMessage ( ucwords($entity).' successfully created.' );
                    //check if user has the redirect route set
                    if (!is_null($entitySpec->createActionRedirectRoute)) {
                        if (is_null($entitySpec->createActionRedirectRouteKeyField) ||
                            $entitySpec->createActionRedirectRouteKeyField == $entitySpec->entityKeyField ||
                            is_null($entitySpec->createActionRedirectRouteKey)
                        ) {
                            $this->redirect ()->toRoute ($entitySpec->createActionRedirectRoute,
                                !is_null($entitySpec->createActionRedirectRouteKey) ?
                                [$entitySpec->createActionRedirectRouteKey => $newId] : []);
                        } else {
                            $entityObj = $table->getEntity($entity, $newId);
                            if (!key_exists($entitySpec->createActionRedirectRouteKeyField, $entityObj)) {
                                throw new \Exception('create_action_redirect_route_key_field is misconfigured for entity \''.$entity.'\'');
                            }
                            $this->redirect ()->toRoute ($entitySpec->createActionRedirectRoute,
                                [$entitySpec->createActionRedirectRouteKey => $entityObj[$entitySpec->createActionRedirectRouteKeyField]]);
                        }
                    } else {
                        $this->redirect ()->toRoute ($this->getDefaultRedirectRoute());
                    }
                }
            } else {
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        }
        $view = new ViewModel([
            'form' => $form,
        ]);

        //check if the user has the createActionTemplate option set, if not they'll go to the default
        if (!is_null($entitySpec->createActionTemplate)) {
            $template = $entitySpec->createActionTemplate;
            $view->setTemplate($template);
        }
        return $view;
    }

    /**
     * Get the entitySpecification value
     * @return Entity
     */
    public function getEntitySpecification()
    {
        if (is_null($this->entitySpecification)) {
            $entity = $this->getEntity();
            $sm = $this->getServiceLocator();
            /** @var EntitiesService $entitiesService */
            $entitiesService = $sm->get('SionModel\Service\EntitiesService');
            $entities = $entitiesService->getEntities();
            if (!key_exists($entity, $entities)) {
                throw new \Exception('Invalid entity given\''.$entity.'\'');
            }
            $this->setEntitySpecification($entities[$entity]);
        }
        return $this->entitySpecification;
    }

    /**
     *
     * @param Entity $entitySpecification
     * @return self
     */
    public function setEntitySpecification(Entity $entitySpecification)
    {
        $this->entitySpecification = $entitySpecification;
        return $this;
    }

    /**
     * Get the sionTable value
     * @return SionTable
     */
    public function getSionTable()
    {
        if (is_null($this->sionTable)) {
            $sm = $this->getServiceLocator();
            $entitySpec = $this->getEntitySpecification();
            if (!$sm->has($entitySpec->sionModelClass)) {
                throw new \Exception('Invalid SionModel class set for entity \''.$this->getEntity().'\'');
            }
            $this->setSionTable($sm->get($entitySpec->sionModelClass));
        }
        return $this->sionTable;
    }

    /**
     * @param SionTable $sionTable
     * @return self
     */
    public function setSionTable($sionTable)
    {
        if (!$sionTable instanceof SionTable) {
            throw new \Exception('Expecting SionModelClass to be a SionTable instance.');
        }
        $this->sionTable = $sionTable;
        return $this;
    }

    /**
     * Get the entity value
     * @return string
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     *
     * @param string $entity
     * @return self
     */
    public function setEntity($entity)
    {
        $this->entity = $entity;
        return $this;
    }

    /**
     * Get the defaultRedirectRoute value
     * @return defaultRedirectRoute
     */
    public function getDefaultRedirectRoute()
    {
        if (is_null($this->defaultRedirectRoute)) {
            $config = $this->getSionModelConfig();
            $redirectRoute = $config['default_redirect_route'];
            $this->setDefaultRedirectRoute($redirectRoute);
        }
        return $this->defaultRedirectRoute;
    }

    /**
     *
     * @param defaultRedirectRoute $defaultRedirectRoute
     * @return self
     */
    public function setDefaultRedirectRoute($defaultRedirectRoute)
    {
        $this->defaultRedirectRoute = $defaultRedirectRoute;
        return $this;
    }

    /**
     * Get the sionModelConfig value
     * @return array
     */
    public function getSionModelConfig()
    {
        if (is_null($this->sionModelConfig)) {
            $config = $sm->get('SionModel\Config');
            $this->setSionModelConfig($config);
        }
        return $this->sionModelConfig;
    }

    /**
     *
     * @param array $sionModelConfig
     * @return self
     */
    public function setSionModelConfig(array $sionModelConfig)
    {
        $this->sionModelConfig = $sionModelConfig;
        return $this;
    }
}
