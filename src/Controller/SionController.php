<?php

declare(strict_types=1);

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 */

namespace SionModel\Controller;

use Exception;
use InvalidArgumentException;
use JTranslate\Controller\Plugin\NowMessenger;
use Laminas\Form\Form;
use Laminas\Form\FormInterface;
use Laminas\Http\Response;
use Laminas\Log\LoggerAwareTrait;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Mvc\Controller\Plugin\PluginInterface;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use SionModel;
use SionModel\Db\Model\PredicatesTable;
use SionModel\Db\Model\SionTable;
use SionModel\Entity\Entity;
use SionModel\Form\CommentForm;
use SionModel\Form\DeleteEntityForm;
use SionModel\Form\TouchForm;
use SionModel\Service\EntitiesService;

use function array_keys;
use function count;
use function implode;
use function is_array;
use function is_callable;
use function is_numeric;
use function is_string;
use function method_exists;
use function ucfirst;
use function ucwords;

class SionController extends AbstractActionController
{
    use LoggerAwareTrait;

    /** @var Entity[] $entitySpecifications */
    protected $entitySpecifications;
    /** @var Entity $entitySpecification */
    protected $entitySpecification;
    /** @var SionTable $sionTable */
    protected $sionTable;
    /** @var string $entity */
    protected $entity;
    /** @var string $defaultRedirectRoute */
    protected $defaultRedirectRoute;
    /** @var array $sionModelConfig */
    protected $sionModelConfig;

    /** @var PredicatesTable $predicateTable */
    protected $predicateTable;

    public const ACTION_SHOW       = 'show';
    public const ACTION_EDIT       = 'edit';
    public const ACTION_DELETE     = 'delete';
    public const ACTION_TOUCH      = 'touch';
    public const ACTION_TOUCH_JSON = 'touchJson';
    /**
     * Maps an action to the Entity property which gives the route parameter name for finding the entityId
     *
     * @deprecated
     *
     * @var array ENTITY_ACTION_ROUTE_KEY_PROPERTIES
     */
    protected const ENTITY_ACTION_ROUTE_KEY_PROPERTIES = [
        self::ACTION_SHOW       => 'showRouteKey',
        self::ACTION_EDIT       => 'editRouteKey',
        self::ACTION_DELETE     => 'deleteRouteKey',
        self::ACTION_TOUCH      => 'touchRouteKey',
        self::ACTION_TOUCH_JSON => 'touchJsonRouteKey',
    ];

    /**
     * Maps an action to the Entity property which gives the route params array.
     * The route params array declares which route paramaters maps to which entity fields
     *
     * @var array
     */
    protected const ENTITY_ACTION_ROUTE_PARAMS_PROPERTIES = [
        self::ACTION_SHOW       => 'showRouteParams',
        self::ACTION_EDIT       => 'editRouteParams',
        self::ACTION_DELETE     => 'deleteRouteParams',
        self::ACTION_TOUCH      => 'touchRouteParams',
        self::ACTION_TOUCH_JSON => 'touchJsonRouteParams',
    ];

    /**
     * The entity objects that have been requested (normally just one in a page load)
     *
     * @var array $object
     */
    protected $object = [];

    /**
     * The id of the entity in question
     *
     * @var int|string $entityId
     */
    protected $actionEntityIds = [];

    protected $createActionForm;

    protected $editActionForm;

    /** @var EntitiesService $entitiesService */
    protected $entitiesService;

    protected $config;

    /**
     * If a SionController needs more services than those provided they can specify these
     * in the 'controller_services' configuration, and they will be injected into this array.
     *
     * @var array $services
     */
    protected $services;

    /**
     * @param string $entity
     * @throws Exception
     */
    public function __construct(
        $entity,
        EntitiesService $entitiesService,
        SionTable $sionTable,
        PredicatesTable $predicateTable,
        $createActionForm,
        $editActionForm,
        array $config,
        array $services
    ) {
        //@todo check the types
        $this->setEntity($entity);
        $this->entitiesService  = $entitiesService;
        $this->sionTable        = $sionTable;
        $this->predicateTable   = $predicateTable;
        $this->createActionForm = $createActionForm;
        $this->editActionForm   = $editActionForm;
        $this->config           = $config;
        $this->sionModelConfig  = $config['sion_model'];
        $this->services         = $services;
    }

    public function indexAction()
    {
        /** @var SionTable $table */
        $table      = $this->getSionTable();
        $entity     = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        $objects    = $table->getObjects($entity);
        return new ViewModel([
            'entity'     => $entity,
            'entitySpec' => $entitySpec,
            'objects'    => $objects,
        ]);
    }

    /**
     * Retrieve a requested entityObject by the route parameter specified by the entity's show_route_key.
     * The consumer of the SionController should implement the view template
     *
     * @todo introduce resource-level checks
     * @throws Exception
     * @return ViewModel|ResponseInterface
     */
    public function showAction()
    {
        $entity     = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        $id         = $this->getEntityIdParam('show');
        if (! $id) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage(ucwords($entity) . ' not found.');
            $redirectRoute = $entitySpec->indexRoute
                ? $entitySpec->indexRoute
                : $this->getDefaultRedirectRoute();
            return $this->redirect()->toRoute($redirectRoute);
        }

        if (! $this->isActionAllowed('show')) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Access to entity denied.');
            $redirectRoute = $entitySpec->indexRoute
                ? $entitySpec->indexRoute
                : $this->getDefaultRedirectRoute();
            return $this->redirect()->toRoute($redirectRoute);
        }

        /** @var SionTable $table */
        $table = $this->getSionTable();
        if (! $table->existsEntity($entity, $id)) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage(ucfirst($entity) . ' not found.');
            $redirectRoute = $entitySpec->indexRoute
                ? $entitySpec->indexRoute
                : $this->getDefaultRedirectRoute();
            return $this->redirect()->toRoute($redirectRoute);
        }

        $entityObject = $this->getEntityObject($id);
        //if the entity doesn't exist, redirect to the index or the default route
        if (! isset($entityObject)) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage(ucfirst($entity) . ' not found.');
            $redirectRoute = $entitySpec->indexRoute
                ? $entitySpec->indexRoute
                : $this->getDefaultRedirectRoute();
            return $this->redirect()->toRoute($redirectRoute);
        }

        $changes = $table->getEntityChanges($entity, $id);

        $comments            = [];
        $commentForm         = null;
        $predicateTable      = $this->getPredicateTable();
        $commentableEntities = $predicateTable->getCommentPredicates();
        if (isset($commentableEntities[$entity])) {
            //this entity has a comments predicate, assume the user wants them
            $comments = $predicateTable->getComments([
                'predicateKind'  => $commentableEntities[$entity],
                'objectEntityId' => $id,
                'status'         => PredicatesTable::COMMENT_STATUS_PUBLISHED,
            ]);

            $commentForm = new CommentForm();
            $commentUrl  = $this->url()->fromRoute('comments/create', [
                //@todo this should be configurable
                'kind'      => PredicatesTable::COMMENT_KIND_COMMENT,
                'entity'    => $entity,
                'entity_id' => $id,
            ]);
            $commentForm->setAttribute('action', $commentUrl);
            $commentForm->get('redirect')->setValue($this->url()->fromRoute(null, [], [], true));
        }

        $table->registerVisit($entity, $entityObject[$entitySpec->entityKeyField]);

        $visitsArray = $table->getVisitCounts($entity, [$id]);
        if (isset($visitsArray[$id])) {
            $visits = $visitsArray[$id];
        } else {
            $visits = [
                'total'     => 0,
                'pastMonth' => 0,
            ];
        }

        //@todo enable suggest form
//         $sm = $this->getServiceLocator ();
//         /** @var SionModel\Form\SionForm $suggestForm **/
//         if (!isset($entitySpec->suggestForm)) {
//             $suggestForm = $sm->get('SionModel\Form\SuggestForm');
//         } elseif ($sm->has($entitySpec->suggestForm)) {
//             $suggestForm = $sm->get($entitySpec->suggestForm);
//         } elseif (class_exists($entitySpec->suggestForm)) {
//             $suggestFormName = $entitySpec->suggestForm;
//             $suggestForm = new $suggestFormName;
//         } else {
//             throw new \InvalidArgumentException(
//                 'Invalid suggest_form specified for \''.$entity.'\' entity.');
//         }
        $view = new ViewModel([
            'entityId'    => $id,
            'entity'      => $entityObject,
            'changes'     => $changes,
            'comments'    => $comments,
            'commentForm' => $commentForm,
            'visits'      => $visits,
//             'suggestForm'   => $suggestForm,
//             'deviceType'    => $deviceType,
        ]);

        //check if the user has the showActionTemplate option set, if not they'll go to the default
        if (isset($entitySpec->showActionTemplate)) {
            $template = $entitySpec->showActionTemplate;
            $view->setTemplate($template);
        }
        return $view;
    }

    /**
     * Create an entity
     *
     * @throws InvalidArgumentException
     * @throws Exception
     * @return ViewModel|ResponseInterface
     */
    public function createAction()
    {
        /** @var SionTable $table **/
        $table      = $this->getSionTable();
        $entity     = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();

        if (! isset($entitySpec->createActionForm)) {
            throw new InvalidArgumentException(
                'If the createAction for \''
                . $entity
                . '\' is to be used, it must specify the create_action_form configuration.'
            );
        }
        $form = $this->createActionForm;
        $view = new ViewModel([
            'form' => $form,
        ]);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $this->getPostDataForCreateAction();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                //if we have a dataHandler registered, call it @deprecated
                if (isset($entitySpec->createActionValidDataHandler)) {
                    $handlerFunction = $entitySpec->createActionValidDataHandler;
                    if (
                        ! method_exists($this, $handlerFunction) ||
                        method_exists('SionController', $handlerFunction)
                    ) {
                        throw new Exception(
                            'Invalid create_action_valid_data_handler set for entity \''
                            . $entity
                            . '\''
                        );
                    }
                    //don't return here so that if the handler doesn't redirect, we send them back to the form
                    $this->$handlerFunction($data, $form);
                } else { //if we have no data handler, we'll do it ourselves
                    $response = $this->createEntityPostFormValidation($data, $form);
                    if ($response instanceof ResponseInterface) {
                        return $response;
                    }
                }
            } else {
                $response = $this->doWorkWhenFormInvalidForCreateAction($view);
                if ($response instanceof ResponseInterface) {
                    return $response;
                }
            }
        } else {
            $response = $this->doWorkWhenNotPostForCreateAction($view);
            if ($response instanceof ResponseInterface) {
                return $response;
            }
        }

        //check if the user has the createActionTemplate option set, if not they'll go to the default
        if (isset($entitySpec->createActionTemplate)) {
            $template = $entitySpec->createActionTemplate;
            $view->setTemplate($template);
        }
        return $view;
    }

    /**
     * This function will be executed within the create action
     * if the form doesn't validate properly. This is for a
     * consumer of this class to overload this method.
     *
     * @return void
     */
    public function doWorkWhenFormInvalidForCreateAction(ViewModel $view)
    {
        /** @var SionModel\Form\SionForm $form */
        $form     = $view->getVariable('form');
        $messages = $form->getMessages();
        $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_ERROR)
            ->addMessage(
                'Error in form submission, please review: ' . implode(', ', array_keys($messages))
            );
    }

    /**
     * This function will be executed within the create action
     * if the page load was not a POST method. This is for a
     * consumer of this class to overload this method.
     *
     * @return void
     */
    public function doWorkWhenNotPostForCreateAction(ViewModel $view)
    {
    }

    /**
     * Return an array of data to be passed to the setData function of the CreateEntity form
     *
     * @return array
     */
    public function getPostDataForCreateAction()
    {
        return $this->getRequest()->getPost()->toArray();
    }

    /**
     * Creates a new entity, notifies the user via flash messenger and redirects.
     *
     * @param mixed[] $data
     * @param Form $form
     * @return ResponseInterface|NULL
     */
    public function createEntityPostFormValidation($data, $form)
    {
        $entity = $this->getEntity();
        $table  = $this->getSionTable();
        if (! ($newId = $table->createEntity($entity, $data))) {
            $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_ERROR)
            ->addMessage('Error in form submission, please review.');
        } else {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
            ->addMessage(ucwords($entity) . ' successfully created.');
            return $this->redirectAfterCreate((int) $newId, $data, $form);
        }
    }

    /**
     * This function is called after a successful entity creation to redirect the user.
     * May be overwritten by a child Controller to add functionality.
     *
     * @param int $newId
     * @param mixed[] $data
     * @param FormInterface $form
     * @throws Exception
     * @return ResponseInterface
     */
    public function redirectAfterCreate($newId, $data = [], $form = null)
    {
        /** @var SionTable $table **/
        $table      = $this->getSionTable();
        $entity     = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();

        //check if user has the redirect route set
        if (isset($entitySpec->createActionRedirectRoute)) {
            $entityObject = $this->getEntityObject($newId);
            if (is_array($entitySpec->createActionRedirectRouteParams)) {
                $params = [];
                foreach ($entitySpec->createActionRedirectRouteParams as $routeParam => $entityField) {
                    if (! is_string($routeParam)) {
                        throw new Exception(
                            "Invalid create_action_redirect_route_params configuration for `$entity`"
                        );
                    }
                    if (! is_string($entityField)) {
                        throw new Exception(
                            "Invalid create_action_redirect_route_params configuration for `$entity`"
                        );
                    }
                    if (! isset($entityObject[$entityField])) {
                        throw new Exception(
                            "Error while redirecting after a successful create. Missing param `$entityField`"
                        );
                    } else {
                        $params[$routeParam] = $entityObject[$entityField];
                    }
                }
                if (count($params) === count($entitySpec->createActionRedirectRouteParams)) {
                    return $this->redirect()->toRoute($entitySpec->createActionRedirectRoute, $params);
                } else {
                    //it should be impossible to be here
                    throw new Exception('Unknown error');
                }
            }
            if (is_array($entitySpec->defaultRouteParams)) {
                $params = [];
                foreach ($entitySpec->defaultRouteParams as $routeParam => $entityField) {
                    if (! is_string($routeParam)) {
                        throw new Exception("Invalid default_route_params configuration for `$entity`");
                    }
                    if (! is_string($entityField)) {
                        throw new Exception("Invalid default_route_params configuration for `$entity`");
                    }
                    if (! isset($entityObject[$entityField])) {
                        throw new Exception(
                            "Error while redirecting after a successful create. Missing param `$entityField`"
                        );
                    } else {
                        $params[$routeParam] = $entityObject[$entityField];
                    }
                }
                if (count($params) === count($entitySpec->defaultRouteParams)) {
                    return $this->redirect()->toRoute($entitySpec->createActionRedirectRoute, $params);
                } else {
                    //it should be impossible to be here
                    throw new Exception('Unknown error');
                }
            }
            if (
                ! isset($entitySpec->createActionRedirectRouteKeyField) ||
                $entitySpec->createActionRedirectRouteKeyField === $entitySpec->entityKeyField ||
                ! isset($entitySpec->createActionRedirectRouteKey)
            ) {
                return $this->redirect()->toRoute(
                    $entitySpec->createActionRedirectRoute,
                    isset($entitySpec->createActionRedirectRouteKey) ?
                    [$entitySpec->createActionRedirectRouteKey => $newId] : []
                );
            }
            $entityObj = $table->getObject($entity, $newId);
            if (! isset($entityObj[$entitySpec->createActionRedirectRouteKeyField])) {
                throw new Exception(
                    'create_action_redirect_route_key_field is misconfigured for entity \''
                    . $entity
                    . '\''
                );
            }
            return $this->redirect()->toRoute(
                $entitySpec->createActionRedirectRoute,
                [
                    $entitySpec->createActionRedirectRouteKey
                        => $entityObj[$entitySpec->createActionRedirectRouteKeyField],
                ]
            );
        }
        return $this->redirect()->toRoute($this->getDefaultRedirectRoute());
    }

    /**
     * Standard edit action which checks edit_route_key input, looks up the entity,
     * checks for a post, validates the data with the form and submits the change if the form validates.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @return ViewModel|ResponseInterface
     */
    public function editAction()
    {
        $entity     = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
//         if (!isset($entitySpec->editRouteKey)) {
//             throw new \Exception("Please set the edit_route_key config key of $entity in order to use the editAction.");
//         }
        $id = $this->getEntityIdParam('edit');
        //if the entity doesn't exist, redirect to the index or the default route
        if (! $id) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage(ucfirst($entity) . ' not found.');
            $redirectRoute = $entitySpec->indexRoute
                ? $entitySpec->indexRoute
                : $this->getDefaultRedirectRoute();
            return $this->redirect()->toRoute($redirectRoute);
        }
        $entityObject = $this->getEntityObject($id);
        //if the entity doesn't exist, redirect to the index or the default route
        if (! isset($entityObject)) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage(ucfirst($entity) . ' not found.');
            $redirectRoute = $entitySpec->indexRoute
                ? $entitySpec->indexRoute
                : $this->getDefaultRedirectRoute();
            return $this->redirect()->toRoute($redirectRoute);
        }

        if (! $this->isActionAllowed('edit')) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Access to entity denied.');
            $redirectRoute = $entitySpec->indexRoute
                ? $entitySpec->indexRoute
                : $this->getDefaultRedirectRoute();
            return $this->redirect()->toRoute($redirectRoute);
        }

        if (! isset($this->editActionForm)) {
            throw new InvalidArgumentException(
                'If the editAction for \''
                    . $entity
                    . '\' is to be used, it must specify the edit_action_form configuration.'
            );
        }
        $form = $this->editActionForm;

        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $this->getPostDataForEditAction();
            $form->setData($data);
            if ($form->isValid()) {
                $data     = $form->getData();
                $response = $this->updateEntityPostFormValidation($id, $data, $form);
                if ($response instanceof ResponseInterface) {
                    return $response;
                }
            } else {
                $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_ERROR)
                    ->addMessage('Error in form submission, please review.');
            }
        } else {
            $form->setData($entityObject);
        }
        $deleteForm = new DeleteEntityForm();
        $view       = new ViewModel([
            'entity'     => $entityObject,
            'entityId'   => $id,
            'form'       => $form,
            'deleteForm' => $deleteForm,
        ]);

        //check if the user has the editActionTemplate option set, if not they'll go to the default
        if (isset($entitySpec->editActionTemplate)) {
            $template = $entitySpec->editActionTemplate;
            $view->setTemplate($template);
        }
        return $view;
    }

    /**
     * Return an array of data to be passed to the setData function of the EditEntity form
     *
     * @return array
     */
    public function getPostDataForEditAction()
    {
        return $this->getRequest()->getPost()->toArray();
    }

    /**
     * Updates a given entity, notifies the user via flash messenger and calls the redirect function.
     *
     * @param int $id
     * @param mixed[] $data
     * @param Form $form
     * @throws Exception
     */
    public function updateEntityPostFormValidation($id, $data, $form): ResponseInterface
    {
        $entity = $this->getEntity();
        /** @var SionTable $table **/
        $table         = $this->getSionTable();
        $updatedObject = $table->updateEntity($entity, $id, $data);
        $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
            ->addMessage(ucfirst($entity) . ' successfully updated.');
        return $this->redirectAfterEdit($id, $data, $form, $updatedObject);
    }

    /**
     * Redirects the user after successfully editing an entity.
     *
     * @param int $id
     * @param mixed[] $data
     * @param FormInterface $form
     * @throws Exception
     * @return ResponseInterface
     */
    public function redirectAfterEdit($id, $data = [], $form = null, $updatedObject = [])
    {
        $entitySpec   = $this->getEntitySpecification();
        $entityObject = $this->getEntityObject($id);
        /*
         * Priority of redirect options
         * 1. showRouteParams
         * 2. showRouteKey+showRouteKeyField
         * 3. defaultRouteParams
         * 4. index route
         * 5. default redirect route
         */
        if ($entitySpec->showRoute && is_array($entitySpec->showRouteParams)) {
            $params = [];
            foreach ($entitySpec->showRouteParams as $routeParam => $entityField) {
                if (! isset($updatedObject[$entityField])) {
                    //@todo log this
//                     throw new \Exception(
//          "Error while redirecting after a successful edit. Missing param `$entityField`");
                } else {
                    $params[$routeParam] = $updatedObject[$entityField];
                }
            }
            if (count($params) === count($entitySpec->showRouteParams)) {
                return $this->redirect()->toRoute($entitySpec->showRoute, $params);
            }
        }
        if (
            $entitySpec->showRoute && $entitySpec->showRouteKey &&
            $entitySpec->showRouteKeyField
        ) {
            if (! isset($entityObject[$entitySpec->showRouteKeyField])) {
                //@todo log this, and go on with life
                throw new Exception("show_route_key_field config for entity '$entity' refers to a key that doesn't exist");
            }
            return $this->redirect()->toRoute(
                $entitySpec->showRoute,
                [$entitySpec->showRouteKey => $entityObject[$entitySpec->showRouteKeyField]]
            );
        }
        if ($entitySpec->showRoute && is_array($entitySpec->defaultRouteParams)) {
            $params = [];
            foreach ($entitySpec->defaultRouteParams as $routeParam => $entityField) {
                if (! isset($updatedObject[$entityField])) {
                    //@todo log this
//                     throw new \Exception("Error while redirecting after a successful edit.
//                     Missing param `$entityField`");
                } else {
                    $params[$routeParam] = $updatedObject[$entityField];
                }
            }
            if (count($params) === count($entitySpec->defaultRouteParams)) {
                return $this->redirect()->toRoute($entitySpec->showRoute, $params);
            }
        }
        if ($entitySpec->indexRoute) {
            return $this->redirect()->toRoute($entitySpec->indexRoute);
        }
        return $this->redirect()->toRoute($this->getDefaultRedirectRoute());
    }

    /**
     * @todo test!
     * @throws Exception
     * @return ViewModel|ResponseInterface
     */
    public function touchAction()
    {
        $entity     = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        $id         = $this->getEntityIdParam('touch');
        //if the entity doesn't exist, redirect to the index or the default route
        if (! $id) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage(ucfirst($entity) . ' not found.');
            $redirectRoute = $entitySpec->indexRoute
                ? $entitySpec->indexRoute
                : $this->getDefaultRedirectRoute();
            return $this->redirect()->toRoute($redirectRoute);
        }
        $entityObject = $this->getEntityObject($id);
        //if the entity doesn't exist, redirect to the index or the default route
        if (! isset($entityObject)) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage(ucfirst($entity) . ' not found.');
            $redirectRoute = $entitySpec->indexRoute
                ? $entitySpec->indexRoute
                : $this->getDefaultRedirectRoute();
            return $this->redirect()->toRoute($redirectRoute);
        }

        $form = new TouchForm();

        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost()->toArray();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                /** @var SionTable $table **/
                $table        = $this->getSionTable();
                $fieldToTouch = $this->whichFieldToTouch();
                $table->touchEntity($entity, $id, $fieldToTouch);
                $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                    ->addMessage(ucfirst($entity) . ' successfully marked up-to-date.');
                if (
                    $entitySpec->showRouteKey && $entitySpec->showRouteKey &&
                    $entitySpec->showRouteKeyField
                ) {
                    if (! isset($entityObject[$entitySpec->showRouteKeyField])) {
                        throw new Exception(
                            "show_route_key_field config for entity '"
                            . $entity
                            . "' refers to a key that doesn't exist"
                        );
                    }
                    return $this->redirect()->toRoute(
                        $entitySpec->showRoute,
                        [$entitySpec->showRouteKey => $entityObject[$entitySpec->showRouteKeyField]]
                    );
                } else {
                    return $this->redirect()->toRoute($this->getDefaultRedirectRoute());
                }
            } else {
                $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_ERROR)
                    ->addMessage('Error in form submission, please review.');
            }
        } else {
            $form->setData($entityObject);
        }
        return new ViewModel([
            'entity'   => $entityObject,
            'entityId' => $id,
            'form'     => $form,
        ]);
    }

    /**
     * Touch the entity, and return the status through the HTTP code
     *
     * @return ResponseInterface|JsonModel
     */
    public function touchJsonAction()
    {
        $entity = $this->getEntity();

        $id = $this->getEntityIdParam('touchJson');
        if (! isset($id)) {
            return $this->sendFailedMessage('Invalid id passed.');
        }
        $callback = $this->params()->fromQuery('callback', null);
        if (! isset($callback)) {
            return $this->sendFailedMessage(
                'All requests must include a callback function set as a query parameter \'callback\'.'
            );
        }

        $form = new TouchForm();

        $request = $this->getRequest();
        if (! $request->isPost()) {
            return $this->sendFailedMessage('Please use post method.');
        }
        //$data = Json::decode($request->getContent(), Json::TYPE_ARRAY);
        $data = $request->getPost()->toArray();
        $form->setData($data);
        if (! $form->isValid()) {
            return $this->sendFailedMessage('The following fields are invalid: '
                . implode(', ', array_keys($form->getInputFilter()->getInvalidInput()))
                    . $request->getContent());
        }

        /** @var SionTable $table */
        $table        = $this->getSionTable();
        $fieldToTouch = $this->whichFieldToTouch();
        $return       = $table->touchEntity($entity, $id, $fieldToTouch);

        $view = new JsonModel([
            'return'  => $return,
            'field'   => $fieldToTouch,
            'message' => 'Success',
        ]);
        $view->setJsonpCallback($callback);
        return $view;
    }

    /**
     * If the form has been posted, confirm the CSRF. If all is well, delete the entity.
     * If the request is a GET, ask the user to confirm the deletion
     *
     * @return ViewModel|ResponseInterface
     * @todo Create a view template to ask for confirmation
     * @todo check if client expects json, and make it AJAX friendly
     */
    public function deleteAction()
    {
        $entity     = $this->getEntity();
        $id         = $this->getEntityIdParam('delete');
        $entitySpec = $this->getEntitySpecification();

        $request = $this->getRequest();

        //make sure we have all the information that we need to delete
        if (! $entitySpec->isEnabledForEntityDelete()) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('This entity cannot be deleted, please check the configuration.');
            return $this->redirectAfterDelete(false);
        }

        //make sure the user has permission to delete the entity
        if (! $this->isActionAllowed('delete')) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('You do not have permission to delete this entity.');
            return $this->redirectAfterDelete(false);
        }

        //@deprecated @todo remove this part
        if (
            isset($entitySpec->deleteActionAclResource) &&
            ! $this->isAllowed(
                $entitySpec->deleteActionAclResource,
                $entitySpec->deleteActionAclPermission ?
                $entitySpec->deleteActionAclPermission : null
            )
        ) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('You do not have permission to delete this entity.');
            return $this->redirectAfterDelete(false);
        }

        //make sure our table exists
        $table = $this->getSionTable();

        //make sure our entity exists
        if (! $table->existsEntity($entity, $id)) {
            $this->getResponse()->setStatusCode(401);
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage('The entity you\'re trying to delete doesn\'t exists.');
            return $this->redirectAfterDelete(false);
        }

        //@todo See if we can discern the name of the entity to show it to the user.

        $form = new DeleteEntityForm();
        if ($request->isPost()) {
            $data = $request->getPost();
            $form->setData($data);
            if ($form->isValid()) {
                $table->deleteEntity($entity, $id);
                $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                ->addMessage('Entity successfully deleted.');
                return $this->redirectAfterDelete(true);
            } else {
                $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_ERROR)
                    ->addMessage('Error in form submission, please review.');
                //exists, but either didn't match params or bad csrf
                $this->getResponse()->setStatusCode(401);
            }
        }

        //set the form action url
        $form->setAttribute('action', $this->getRequest()->getRequestUri());
        $entityObject = $this->getEntityObject($id);

        $view = new ViewModel([
            'form'         => $form,
            'entity'       => $entity,
            'entityId'     => $id,
            'entityObject' => $entityObject,
        ]);
        //@todo first check if the Controller has a default template, if not
        //look for a configuration value, if not, use the default template
        $view->setTemplate('sion-model/sion-model/delete');
        return $view;
    }

    /**
     * Called in order to redirect after error or success on deleteAction
     *
     * @param bool $actionWasSuccessful
     * @throws Exception
     */
    protected function redirectAfterDelete($actionWasSuccessful = true): Response
    {
        $entity     = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        if (isset($entitySpec->deleteActionRedirectRoute)) {
            return $this->redirect()->toRoute($entitySpec->deleteActionRedirectRoute);
        } elseif (isset($entitySpec->indexRoute)) {
            return $this->redirect()->toRoute($entitySpec->indexRoute);
        } else {
            if (null === ($defaultRedirectRoute = $this->getDefaultRedirectRoute())) {
                throw new Exception(
                    "Please configure the deleteActionRedirectRoute for "
                    . $entity
                    . ", or the sion_model default_redirect_route."
                );
            }
            return $this->redirect()->toRoute($defaultRedirectRoute);
        }
    }

    /**
     * @psalm-param 'delete'|'edit'|'show' $action
     */
    protected function isActionAllowed(string $action)
    {
        if (! isset(Entity::IS_ACTION_ALLOWED_PERMISSION_PROPERTIES[$action])) {
            throw new InvalidArgumentException('Invalid action parameter');
        }
        $entityId   = $this->getEntityIdParam($action);
        $entitySpec = $this->getEntitySpecification();

        if (! isset($entitySpec->aclResourceIdField)) {
            return true;
        }

        /**
         * isAllowed plugin
         *
         * @var PluginInterface $isAllowedPlugin
         */
        $isAllowedPlugin = null;
        try {
            $isAllowedPlugin = $this->plugin('isAllowed');
        } catch (Exception $e) {
        }
        //if we don't have the isAllowed plugin, just allow
        if (! is_callable($isAllowedPlugin)) {
            return true;
        }

        $permissionProperty = Entity::IS_ACTION_ALLOWED_PERMISSION_PROPERTIES[$action];
        $object             = $this->getEntityObject($entityId);
        if (! isset($entitySpec->$permissionProperty)) {
            //we don't need the permission, just the resourceId
            return $isAllowedPlugin($object[$entitySpec->aclResourceIdField]);
        }

        return $isAllowedPlugin($object[$entitySpec->aclResourceIdField], $entitySpec->$permissionProperty);
    }

    /**
     * The goal here is to find out the entityId of what user is looking at.
     * There are a couple different places we have to look to discover this:
     * 1. We check the routeParams property of the Entity corresponding to the current action.
     *    If the consumer has set that property, we see if one of those parameters is the entityKeyField
     * 2. Then we check the routeKey property corresponding to the action.
     * 3. Then we check the defaultRouteParams property and see if one is the entityKeyField
     * 4. Finally, we check the defaultRouteKey property
     * If no parameter is set in the Request, $default is returned
     *
     * @param string $action
     * @param mixed $default
     * @throws Exception
     * @return mixed
     */
    protected function getEntityIdParam($action = 'show', $default = null)
    {
        if (isset($this->actionEntityIds[$action])) {
            return $this->actionEntityIds[$action];
        }
        $entity = $this->getEntity();
        /** @var Entity $entitySpec */
        $entitySpec     = $this->getEntitySpecification();
        $actionRouteKey = null;
        //first try to find the key field among the route params fields
        if (isset(self::ENTITY_ACTION_ROUTE_PARAMS_PROPERTIES[$action]) && isset($entitySpec->entityKeyField)) {
            $actionRouteParams = self::ENTITY_ACTION_ROUTE_PARAMS_PROPERTIES[$action];
            if (isset($entitySpec->$actionRouteParams)) {
                $routeParams = $entitySpec->$actionRouteParams;
                foreach ($routeParams as $routeParam => $entityKey) {
                    if ($entityKey === $entitySpec->entityKeyField) {
                        $id = $this->params()->fromRoute($routeParam, $default);
                        break;
                    }
                }
                if (isset($id)) {
                    if (is_numeric($id)) {
                        $this->actionEntityIds[$action] = (int) $id;
                    } else {
                        $this->actionEntityIds[$action] = $id;
                    }
                    return $this->actionEntityIds[$action];
                }
            }
        }
        //then try action route keys
        if (isset(self::ENTITY_ACTION_ROUTE_KEY_PROPERTIES[$action])) {
            $actionRouteKey = self::ENTITY_ACTION_ROUTE_KEY_PROPERTIES[$action];
            if (isset($entitySpec->$actionRouteKey)) {
                $id = $this->params()->fromRoute($entitySpec->$actionRouteKey, $default);
                if (is_numeric($id)) {
                    $this->actionEntityIds[$action] = (int) $id;
                } else {
                    $this->actionEntityIds[$action] = $id;
                }
                return $this->actionEntityIds[$action];
            }
        }
        //then try default params
        if (isset($entitySpec->defaultRouteParams) && isset($entitySpec->entityKeyField)) {
            $routeParams = $entitySpec->defaultRouteParams;
            foreach ($routeParams as $routeParam => $entityKey) {
                if ($entityKey === $entitySpec->entityKeyField) {
                    $id = $this->params()->fromRoute($routeParam, $default);
                    break;
                }
            }
            if (isset($id)) {
                if (is_numeric($id)) {
                    $this->actionEntityIds[$action] = (int) $id;
                } else {
                    $this->actionEntityIds[$action] = $id;
                }
                return $this->actionEntityIds[$action];
            }
        }
        //finally use default route key
        if (isset($entitySpec->defaultRouteKey)) {
            $id = $this->params()->fromRoute($entitySpec->defaultRouteKey, $default);
            if (is_numeric($id)) {
                $this->actionEntityIds[$action] = (int) $id;
            } else {
                $this->actionEntityIds[$action] = $id;
            }
            return $this->actionEntityIds[$action];
        }
        throw new Exception(
            "Please configure the $actionRouteParams for the $entity entity to use the $action action."
        );
    }

    protected function sendFailedMessage(string $message): ResponseInterface
    {
        $response = $this->getResponse();
        $response->setStatusCode(401);
        $response->sendHeaders();
        $response->setContent($message);
        return $response;
    }

    /**
     * Election rules:
     * 1. If there is a route parameter to specify the field, and the field exists, use it.
     * 2. Else, if the touchDefaultField exists, use it.
     * 3. Else, touch the entityKeyField if it exists
     */
    protected function whichFieldToTouch()
    {
        $entity     = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        $touchField = null;
        if (isset($entitySpec->touchFieldRouteKey)) {
            $touchField = $this->params()->fromRoute($entitySpec->touchFieldRouteKey);
            if (isset($entitySpec->updateColumns[$touchField])) {
                return $touchField;
            }
        }
        if (
            isset($entitySpec->touchDefaultField)
            && isset($entitySpec->updateColumns[$entitySpec->touchDefaultField])
        ) {
            return $entitySpec->touchDefaultField;
        }
        if (
            isset($entitySpec->entityKeyField)
            && isset($entitySpec->updateColumns[$entitySpec->entityKeyField])
        ) {
            return $entitySpec->entityKeyField;
        }
        throw new Exception("Cannot find a field to touch for entity '$entity'");
    }

    /**
     * Get the entitySpecification value
     *
     * @return Entity
     */
    public function getEntitySpecification()
    {
        if (! isset($this->entitySpecification)) {
            $entity   = $this->getEntity();
            $entities = $this->getEntitySpecifications();
            if (! isset($entities[$entity])) {
                throw new Exception('Invalid entity given\'' . $entity . '\'');
            }
            $this->setEntitySpecification($entities[$entity]);
        }
        return $this->entitySpecification;
    }

    /**
     * @return self
     */
    public function setEntitySpecification(Entity $entitySpecification)
    {
        $this->entitySpecification = $entitySpecification;
        return $this;
    }

    /**
     * Get the array of entity specifications from config
     *
     * @return Entity[]
     */
    public function getEntitySpecifications()
    {
        if (! isset($this->entitySpecifications)) {
            $entitiesService            = $this->entitiesService;
            $this->entitySpecifications = $entitiesService->getEntities();
        }
        return $this->entitySpecifications;
    }

    /**
     * Get an array representing the entity object given by a particular id
     *
     * @param int $id
     * @return mixed[]
     */
    public function getEntityObject($id)
    {
        if (isset($this->object[$id])) {
            return $this->object[$id];
        }
        $table             = $this->getSionTable();
        $this->object[$id] = $table->getObject($this->getEntity(), $id, true);
        return $this->object[$id];
    }

    /**
     * Get the sionTable value
     *
     * @return SionTable
     */
    public function getSionTable()
    {
        if (! isset($this->sionTable)) {
            throw new Exception('Invalid SionModel class set for entity \'' . $this->getEntity() . '\'');
        }
        return $this->sionTable;
    }

    /**
     * @param SionTable $sionTable
     * @return self
     */
    public function setSionTable($sionTable)
    {
        if (! $sionTable instanceof SionTable) {
            throw new Exception('Expecting SionModelClass to be a SionTable instance.');
        }
        $this->sionTable = $sionTable;
        return $this;
    }

    /**
     * Get the sionTable value
     *
     * @return PredicatesTable
     */
    public function getPredicateTable()
    {
        if (! isset($this->predicateTable)) {
            throw new Exception('Missing predicate table');
        }
        return $this->predicateTable;
    }

    /**
     * @return self
     */
    public function setPredicateTable(PredicatesTable $predicateTable)
    {
        $this->predicateTable = $predicateTable;
        return $this;
    }

    /**
     * Get the entity value
     *
     * @return string
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
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
     *
     * @return string
     */
    public function getDefaultRedirectRoute()
    {
        if (! isset($this->defaultRedirectRoute)) {
            $config        = $this->getSionModelConfig();
            $redirectRoute = $config['default_redirect_route'];
            $this->setDefaultRedirectRoute($redirectRoute);
        }
        return $this->defaultRedirectRoute;
    }

    /**
     * @param string $defaultRedirectRoute
     * @return self
     */
    public function setDefaultRedirectRoute($defaultRedirectRoute)
    {
        $this->defaultRedirectRoute = $defaultRedirectRoute;
        return $this;
    }

    /**
     * Get the sionModelConfig value
     *
     * @return array
     */
    public function getSionModelConfig()
    {
        if (! isset($this->sionModelConfig)) {
            throw new Exception('Something went wrong, no sion model config available');
        }
        return $this->sionModelConfig;
    }

    /**
     * @param array $sionModelConfig
     * @return self
     */
    public function setSionModelConfig(array $sionModelConfig)
    {
        $this->sionModelConfig = $sionModelConfig;
        return $this;
    }
}
