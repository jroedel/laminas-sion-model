<?php

declare(strict_types=1);

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 */

namespace SionModel\Controller;

use DomainException;
use Exception;
use InvalidArgumentException;
use JTranslate\Controller\Plugin\NowMessenger;
use Laminas\Form\Form;
use Laminas\Form\FormInterface;
use Laminas\Http\Response;
use Laminas\Log\LoggerInterface;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Mvc\Controller\Plugin\PluginInterface;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ModelInterface;
use Laminas\View\Model\ViewModel;
use SionModel;
use SionModel\Db\Model\PredicatesTable;
use SionModel\Db\Model\SionTable;
use SionModel\Entity\Entity;
use SionModel\Form\CommentForm;
use SionModel\Form\DeleteEntityForm;
use SionModel\Form\TouchForm;
use SionModel\Service\EntitiesService;
use Webmozart\Assert\Assert;

use function array_keys;
use function count;
use function implode;
use function is_array;
use function is_callable;
use function ucfirst;
use function ucwords;

class SionController extends AbstractActionController
{
    public const ACTION_SHOW       = 'show';
    public const ACTION_EDIT       = 'edit';
    public const ACTION_DELETE     = 'delete';
    public const ACTION_TOUCH      = 'touch';
    public const ACTION_TOUCH_JSON = 'touchJson';

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

    protected array $entitySpecifications  = [];
    protected ?Entity $entitySpecification = null;
    protected array $sionModelConfig       = [];
    protected string $defaultRedirectRoute;

    /**
     * The entity objects that have been requested (normally just one in a page load)
     *
     * @var array $object
     */
    protected array $object = [];

    /**
     * The id of the entity in question
     */
    protected ?int $actionEntityId = null;

    public function __construct(
        protected string $entity,
        protected EntitiesService $entitiesService,
        protected SionTable $sionTable,
        protected PredicatesTable $predicatesTable,
        protected ?FormInterface $createActionForm,
        protected ?FormInterface $editActionForm,
        protected array $config,
        protected LoggerInterface $logger
    ) {
        Assert::notEmpty($this->entity);
        $this->entitySpecifications = $this->entitiesService->getEntities();
        Assert::keyExists($this->entitySpecifications, $this->entity);
        $this->entitySpecification = $this->entitySpecifications[$this->entity];
        Assert::keyExists($config, 'sion_model');
        $this->sionModelConfig = $config['sion_model'];
        Assert::keyExists($config['sion_model'], 'default_redirect_route');
        $this->defaultRedirectRoute = $config['sion_model']['default_redirect_route'];
    }

    public function indexAction(): ModelInterface|ResponseInterface
    {
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
    public function showAction(): ModelInterface|ResponseInterface
    {
        $entity     = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        $id         = $this->getEntityIdParam('show');
        /** @var FlashMessenger $flashMessenger */
        $flashMessenger = $this->plugin('flashMessenger');
        Assert::isCallable($flashMessenger);
        if (! $id) {
            $flashMessenger->setNamespace(FlashMessenger::NAMESPACE_ERROR);
            $flashMessenger->addMessage(ucwords($entity) . ' not found.');
            $redirectRoute = $entitySpec->indexRoute ?: $this->getDefaultRedirectRoute();
            return $this->redirect()->toRoute($redirectRoute);
        }

        if (! $this->isActionAllowed('show')) {
            $flashMessenger->setNamespace(FlashMessenger::NAMESPACE_ERROR);
            $flashMessenger->addMessage('Access to entity denied.');
            $redirectRoute = $entitySpec->indexRoute
                ? $entitySpec->indexRoute
                : $this->getDefaultRedirectRoute();
            return $this->redirect()->toRoute($redirectRoute);
        }

        $table = $this->getSionTable();
        if (! $table->existsEntity($entity, $id)) {
            $flashMessenger->setNamespace(FlashMessenger::NAMESPACE_ERROR);
            $flashMessenger->addMessage(ucfirst($entity) . ' not found.');
            $redirectRoute = $entitySpec->indexRoute ?: $this->getDefaultRedirectRoute();
            return $this->redirect()->toRoute($redirectRoute);
        }

        $entityObject = $this->getEntityObject($id);
        //if the entity doesn't exist, redirect to the index or the default route
        if (! isset($entityObject)) {
            $flashMessenger->setNamespace(FlashMessenger::NAMESPACE_ERROR);
            $flashMessenger->addMessage(ucfirst($entity) . ' not found.');
            $redirectRoute = $entitySpec->indexRoute ?: $this->getDefaultRedirectRoute();
            return $this->redirect()->toRoute($redirectRoute);
        }

        $changes = $table->getEntityChanges($entity, $id);

        $comments            = [];
        $commentForm         = null;
        $predicateTable      = $this->getPredicatesTable();
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
    public function createAction(): ModelInterface|ResponseInterface
    {
        $entity     = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();

        Assert::notNull(
            $this->createActionForm,
            "No createActionForm injected into SionController for entity `$entity`"
        );
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
                return $this->createEntityPostFormValidation($data, $form);
            } else {
                $view = $this->doWorkWhenFormInvalidForCreateAction($view);
                if ($view instanceof ResponseInterface) {
                    return $view;
                }
            }
        } else {
            $view = $this->doWorkWhenNotPostForCreateAction($view);
            if ($view instanceof ResponseInterface) {
                return $view;
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
     */
    public function doWorkWhenFormInvalidForCreateAction(ModelInterface $view): ModelInterface|ResponseInterface
    {
        /** @var SionModel\Form\SionForm $form */
        $form     = $view->getVariable('form');
        $messages = $form->getMessages();
        /** @var NowMessenger $nowMessenger */
        $nowMessenger = $this->plugin('nowMessenger');
        Assert::isCallable($nowMessenger);
        $nowMessenger->setNamespace(NowMessenger::NAMESPACE_ERROR);
        $nowMessenger->addMessage(
            'Error in form submission, please review: ' . implode(', ', array_keys($messages))
        );
        return $view;
    }

    /**
     * This function will be executed within the create action
     * if the page load was not a POST method. This is for a
     * consumer of this class to overload this method.
     */
    public function doWorkWhenNotPostForCreateAction(ModelInterface $view): ModelInterface|ResponseInterface
    {
        return $view;
    }

    /**
     * Return an array of data to be passed to the setData function of the CreateEntity form
     */
    public function getPostDataForCreateAction(): array
    {
        return $this->getRequest()->getPost()->toArray();
    }

    /**
     * Creates a new entity, notifies the user via flash messenger and redirects.
     */
    public function createEntityPostFormValidation(array $data, FormInterface $form): ResponseInterface
    {
        $entity = $this->getEntity();
        $table  = $this->getSionTable();
        /** @var FlashMessenger $flashMessenger */
        $flashMessenger = $this->plugin('flashMessenger');
        Assert::isCallable($flashMessenger);
        /** @var NowMessenger $nowMessenger */
        $nowMessenger = $this->plugin('nowMessenger');
        Assert::isCallable($nowMessenger);
        $newId = $table->createEntity($entity, $data);
        $flashMessenger->setNamespace(FlashMessenger::NAMESPACE_SUCCESS);
        $flashMessenger->addMessage(ucwords($entity) . ' successfully created.');
        return $this->redirectAfterCreate($newId, $data, $form);
    }

    /**
     * This function is called after a successful entity creation to redirect the user.
     * May be overwritten by a child Controller to add functionality.
     *
     * @throws Exception
     */
    public function redirectAfterCreate(int $newId, array $data = [], ?FormInterface $form = null): ResponseInterface
    {
        $entity     = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();

        //let's take care of the easy cases first: neither indexRoute nor defaultRedirectRoute require parameters
        if (
            ! isset($entitySpec->createActionRedirectRoute)
            && ! isset($entitySpec->showRoute)
            && ! isset($entitySpec->indexRoute)
        ) {
            return $this->redirect()->toRoute($this->getDefaultRedirectRoute());
        }
        if (
            ! isset($entitySpec->createActionRedirectRoute)
            && ! isset($entitySpec->showRoute)
        ) {
            return $this->redirect()->toRoute($entitySpec->indexRoute);
        }

        //these two are going to require route parameters
        $redirectRoute = $entitySpec->createActionRedirectRoute ?? $entitySpec->showRoute;
        Assert::notNull($redirectRoute); //impossible

        if (! empty($entitySpec->createActionRedirectRouteParams)) {
            $routeParams = $entitySpec->createActionRedirectRouteParams;
            $paramConfig = 'create_action_redirect_route_params';
        } elseif (! empty($entitySpec->defaultRouteParams)) {
            $routeParams = $entitySpec->defaultRouteParams;
            $paramConfig = 'default_route_params';
        } else {
            throw new DomainException(
                "Either createActionRedirectRouteParams or defaultRouteParams are required to redirect after "
                . "creating entity `$entity`"
            );
        }
        $entityObject = $this->getEntityObject($newId);
        $params       = [];
        foreach ($routeParams as $routeParam => $entityField) {
            Assert::string($routeParam, "Invalid $paramConfig configuration for `$entity`");
            Assert::stringNotEmpty($routeParam, "Invalid $paramConfig configuration for `$entity`");
            Assert::string($entityField, "Invalid $paramConfig configuration for `$entity`");
            Assert::stringNotEmpty($entityField, "Invalid $paramConfig configuration for `$entity`");
            Assert::keyExists(
                $entityObject,
                $entityField,
                "Error while redirecting after a successful create. Missing param `$entityField`"
            );
            $params[$routeParam] = $entityObject[$entityField];
        }
        Assert::true(count($params) === count($routeParams)); //impossible
        return $this->redirect()->toRoute($redirectRoute, $params);
    }

    /**
     * Standard edit action which checks edit_route_key input, looks up the entity,
     * checks for a post, validates the data with the form and submits the change if the form validates.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @return ViewModel|ResponseInterface
     */
    public function editAction(): ModelInterface|ResponseInterface
    {
        $entity     = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        Assert::true(
            isset($entitySpec->editRouteParams) || isset($entitySpec->defaultRouteParams),
            "Default edit action for `$entity`in SionController requires editRouteParams or defaultRouteParams"
        );
        $id = $this->getEntityIdParam('edit');

        /** @var FlashMessenger $flashMessenger */
        $flashMessenger = $this->plugin('flashMessenger');
        Assert::isCallable($flashMessenger);
        /** @var NowMessenger $nowMessenger */
        $nowMessenger = $this->plugin('nowMessenger');
        Assert::isCallable($nowMessenger);

        //if the entity doesn't exist, redirect to the index or the default route
        if (! $id) {
            $flashMessenger->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage(ucfirst($entity) . ' not found.');
            $redirectRoute = $entitySpec->indexRoute
                ? $entitySpec->indexRoute
                : $this->getDefaultRedirectRoute();
            return $this->redirect()->toRoute($redirectRoute);
        }
        $entityObject = $this->getEntityObject($id);
        //if the entity doesn't exist, redirect to the index or the default route
        if (! isset($entityObject)) {
            $flashMessenger->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage(ucfirst($entity) . ' not found.');
            $redirectRoute = $entitySpec->indexRoute
                ? $entitySpec->indexRoute
                : $this->getDefaultRedirectRoute();
            return $this->redirect()->toRoute($redirectRoute);
        }

        if (! $this->isActionAllowed('edit')) {
            $flashMessenger->setNamespace(FlashMessenger::NAMESPACE_ERROR)
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
                $nowMessenger->setNamespace(NowMessenger::NAMESPACE_ERROR);
                $nowMessenger->addMessage('Error in form submission, please review.');
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

        /** @var FlashMessenger $flashMessenger */
        $flashMessenger = $this->plugin('flashMessenger');
        Assert::isCallable($flashMessenger);
        /** @var NowMessenger $nowMessenger */
        $nowMessenger = $this->plugin('nowMessenger');
        Assert::isCallable($nowMessenger);

        //if the entity doesn't exist, redirect to the index or the default route
        if (! $id) {
            $flashMessenger->setNamespace(FlashMessenger::NAMESPACE_ERROR);
            $flashMessenger->addMessage(ucfirst($entity) . ' not found.');
            $redirectRoute = $entitySpec->indexRoute
                ? $entitySpec->indexRoute
                : $this->getDefaultRedirectRoute();
            return $this->redirect()->toRoute($redirectRoute);
        }
        $entityObject = $this->getEntityObject($id);
        //if the entity doesn't exist, redirect to the index or the default route
        if (! isset($entityObject)) {
            $flashMessenger->setNamespace(FlashMessenger::NAMESPACE_ERROR);
            $flashMessenger->addMessage(ucfirst($entity) . ' not found.');
            $redirectRoute = $entitySpec->indexRoute ?: $this->getDefaultRedirectRoute();
            return $this->redirect()->toRoute($redirectRoute);
        }

        $form = new TouchForm();

        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost()->toArray();
            $form->setData($data);
            if ($form->isValid()) {
                $data         = $form->getData();
                $table        = $this->getSionTable();
                $fieldToTouch = $this->whichFieldToTouch();
                $table->touchEntity($entity, $id, $fieldToTouch);
                $flashMessenger->setNamespace(FlashMessenger::NAMESPACE_SUCCESS);
                $flashMessenger->addMessage(ucfirst($entity) . ' successfully marked up-to-date.');
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
                $nowMessenger->setNamespace(NowMessenger::NAMESPACE_ERROR);
                $nowMessenger->addMessage('Error in form submission, please review.');
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
    public function touchJsonAction(): ModelInterface|ResponseInterface
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
    public function deleteAction(): ModelInterface|ResponseInterface
    {
        $entity     = $this->getEntity();
        $id         = $this->getEntityIdParam('delete');
        $entitySpec = $this->getEntitySpecification();

        /** @var FlashMessenger $flashMessenger */
        $flashMessenger = $this->plugin('flashMessenger');
        Assert::isCallable($flashMessenger);
        /** @var NowMessenger $nowMessenger */
        $nowMessenger = $this->plugin('nowMessenger');
        Assert::isCallable($nowMessenger);

        $request = $this->getRequest();

        //make sure we have all the information that we need to delete
        if (! $entitySpec->isEnabledForEntityDelete()) {
            $flashMessenger->setNamespace(FlashMessenger::NAMESPACE_ERROR);
            $flashMessenger->addMessage('This entity cannot be deleted, please check the configuration.');
            return $this->redirectAfterDelete(false);
        }

        //make sure the user has permission to delete the entity
        if (! $this->isActionAllowed('delete')) {
            $flashMessenger->setNamespace(FlashMessenger::NAMESPACE_ERROR);
            $flashMessenger->addMessage('You do not have permission to delete this entity.');
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
            $flashMessenger->setNamespace(FlashMessenger::NAMESPACE_ERROR);
            $flashMessenger->addMessage('You do not have permission to delete this entity.');
            return $this->redirectAfterDelete(false);
        }

        //make sure our table exists
        $table = $this->getSionTable();

        //make sure our entity exists
        if (! $table->existsEntity($entity, $id)) {
            $this->getResponse()->setStatusCode(401);
            $flashMessenger->setNamespace(FlashMessenger::NAMESPACE_ERROR);
            $flashMessenger->addMessage('The entity you\'re trying to delete doesn\'t exists.');
            return $this->redirectAfterDelete(false);
        }

        //@todo See if we can discern the name of the entity to show it to the user.

        $form = new DeleteEntityForm();
        if ($request->isPost()) {
            $data = $request->getPost();
            $form->setData($data);
            if ($form->isValid()) {
                $table->deleteEntity($entity, $id);
                $flashMessenger->setNamespace(FlashMessenger::NAMESPACE_SUCCESS);
                $flashMessenger->addMessage('Entity successfully deleted.');
                return $this->redirectAfterDelete(true);
            } else {
                $nowMessenger->setNamespace(NowMessenger::NAMESPACE_ERROR);
                $nowMessenger->addMessage('Error in form submission, please review.');
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
     * 2. Then we check the defaultRouteParams property and see if one is the entityKeyField
     *
     * @throws Exception
     */
    protected function getEntityIdParam(string $action = 'show'): int
    {
        /*
         * This allows child SionControllers to short-circuit this function's logic easily.
         * It also caches the final result
         */
        if (isset($this->actionEntityId)) {
            return $this->actionEntityId;
        }

        Assert::keyExists(self::ENTITY_ACTION_ROUTE_PARAMS_PROPERTIES, $action);
        $entity          = $this->getEntity();
        $realRouteParams = $this->params()->fromRoute();
        Assert::notEmpty($realRouteParams, "No route parameters were found for action `$action` of entity `$entity`");
        $entitySpec        = $this->getEntitySpecification();
        $actionRouteParams = self::ENTITY_ACTION_ROUTE_PARAMS_PROPERTIES[$action];
        Assert::true(
            ! empty($entitySpec->$actionRouteParams) || ! empty($entitySpec->defaultRouteParams),
            "Can't find an id param for the `$action` action of entity `$entity`. Please set defaultRouteParams."
        );
        //(bool) [] === false
        $entityRouteParams = ! empty($entitySpec->$actionRouteParams)
            ? $entitySpec->$actionRouteParams
            : $entitySpec->defaultRouteParams;
        Assert::notEmpty($entityRouteParams, "Entity `$entity` is not configured for determining route parameters.");

        //we're looking for a route key which maps to the entity's primary key
        $id = 0;
        foreach ($entityRouteParams as $routeParam => $entityField) {
            if ($entityField === $entitySpec->entityKeyField) {
                Assert::keyExists(
                    $realRouteParams,
                    $routeParam,
                    "We expected to find the route param `$routeParam`; no dice"
                );
                $id = (int) $realRouteParams[$routeParam];
                break;
            }
        }
        Assert::greaterThan($id, 0);

        return $this->actionEntityId = $id;
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

    public function getEntitySpecification(): Entity
    {
        return $this->entitySpecification;
    }

    /**
     * Get the array of entity specifications from config
     *
     * @return Entity[]
     */
    public function getEntitySpecifications(): array
    {
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

    public function getPredicatesTable(): PredicatesTable
    {
        return $this->predicatesTable;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    /**
     * Get the defaultRedirectRoute value
     */
    public function getDefaultRedirectRoute(): string|null
    {
        return $this->defaultRedirectRoute;
    }

    protected function getSionTable(): SionTable
    {
        return $this->sionTable;
    }
}
