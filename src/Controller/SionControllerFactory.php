<?php

namespace SionModel\Controller;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\AbstractFactoryInterface;
use SionModel\Service\EntitiesService;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use SionModel\Db\Model\PredicatesTable;

class SionControllerFactory implements AbstractFactoryInterface
{
    /** @var EntitiesService $entitiesService */
    protected $entitiesService;

    public function canCreate(ContainerInterface $container, $requestedName)
    {
        if (! isset($this->entitiesService)) {
            $parentLocator = $container->getServiceLocator();
            /** @var EntitiesService $entitiesService */
            $this->entitiesService = $parentLocator->get(EntitiesService::class);
        }
        $controllers = $this->entitiesService->getEntityControllers();
        return array_key_exists($requestedName, $controllers);
    }

    /**
     * These aliases work to substitute class names with SM types that are buried in ZF
     * @var array
     */
    protected $aliases = [
        'Zend\Form\FormElementManager' => 'FormElementManager',
        'Zend\Validator\ValidatorPluginManager' => 'ValidatorManager',
        'Zend\Mvc\I18n\Translator' => 'translator',
    ];

    /**
     * Create an object
     *
     * @param  ContainerInterface $container
     * @param  string             $requestedName
     * @param  null|array         $options
     * @return object
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     *     creating a service.
     * @throws \Exception if any other error occurs
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $parentLocator = $container->getServiceLocator();
        if (! isset($this->entitiesService)) {
            /** @var EntitiesService $entitiesService */
            $this->entitiesService = $parentLocator->get(EntitiesService::class);
        }
        //figure out what entity we're dealing with
        $entitiesSpecs = $this->entitiesService->getEntities();
        $controllers = $this->entitiesService->getEntityControllers();
        $entity = $controllers[$requestedName];
        $entitySpec = $entitiesSpecs[$entity];

        //get sionTable
        if (! $parentLocator->has($entitySpec->sionModelClass)) {
            throw new \Exception('Invalid SionModel class set for entity \'' . $entity . '\'');
        }
        $sionTable = $parentLocator->get($entitySpec->sionModelClass);

        $predicateTable = $parentLocator->get(PredicatesTable::class);

        //get createActionForm
        /** @var SionForm $createActionForm **/
        $createActionForm = null;
        if ($parentLocator->has($entitySpec->createActionForm)) {
            $createActionForm = $parentLocator->get($entitySpec->createActionForm);
        } elseif (class_exists($entitySpec->createActionForm)) {
            $createActionForm = new $entitySpec->createActionForm();
        }

        //get editActionForm
        /** @var SionForm $editActionForm **/
        $editActionForm = null;
        if ($parentLocator->has($entitySpec->editActionForm)) {
            $editActionForm = $parentLocator->get($entitySpec->editActionForm);
        } elseif (class_exists($entitySpec->editActionForm)) {
            $className = $entitySpec->editActionForm;
            $editActionForm = new $className();
        }

        //get sionModelConfig
        $config = $parentLocator->get('Config');

        //get other requested services
        $services = [];
        foreach ($entitySpec->controllerServices as $service) {
            $obj = null;
            if ($container->has($service)) {
                $obj = $container->get($service);
            }
            $services[$service] = $obj;
        }

        return new $requestedName(
            $entity,
            $this->entitiesService,
            $sionTable,
            $predicateTable,
            $createActionForm,
            $editActionForm,
            $config,
            $services
        );
    }
}
