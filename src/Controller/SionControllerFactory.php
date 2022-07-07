<?php

declare(strict_types=1);

namespace SionModel\Controller;

use Exception;
use Laminas\Form\FormElementManager;
use Laminas\Mvc\I18n\Translator;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Laminas\Validator\ValidatorPluginManager;
use Psr\Container\ContainerInterface;
use SionModel\Db\Model\PredicatesTable;
use SionModel\Form\SionForm;
use SionModel\Service\EntitiesService;

use function array_key_exists;
use function class_exists;

/**
 * @deprecated
 */
class SionControllerFactory implements AbstractFactoryInterface
{
    protected ?EntitiesService $entitiesService = null;

    public function canCreate(ContainerInterface $container, $requestedName)
    {
        if (! isset($this->entitiesService)) {
            $parentLocator         = $container->getServiceLocator();
            $this->entitiesService = $parentLocator->get(EntitiesService::class);
        }
        $controllers = $this->entitiesService->getEntityControllers();
        return array_key_exists($requestedName, $controllers);
    }

    /**
     * These aliases work to substitute class names with SM types that are buried in ZF
     *
     * @var array
     */
    protected $aliases = [
        FormElementManager::class     => 'FormElementManager',
        ValidatorPluginManager::class => 'ValidatorManager',
        Translator::class             => 'translator',
    ];

    /**
     * Create an object
     *
     * @param  string             $requestedName
     * @param  null|array         $options
     * @return object
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $parentLocator = $container->getServiceLocator();
        if (! isset($this->entitiesService)) {
            $this->entitiesService = $parentLocator->get(EntitiesService::class);
        }
        //figure out what entity we're dealing with
        $entitiesSpecs = $this->entitiesService->getEntities();
        $controllers   = $this->entitiesService->getEntityControllers();
        $entity        = $controllers[$requestedName];
        $entitySpec    = $entitiesSpecs[$entity];

        //get sionTable
        if (! isset($entitySpec->sionModelClass) || ! $parentLocator->has($entitySpec->sionModelClass)) {
            throw new Exception('Invalid SionModel class set for entity \'' . $entity . '\'');
        }
        $sionTable       = $parentLocator->get($entitySpec->sionModelClass);
        $predicatesTable = $parentLocator->get(PredicatesTable::class);

        //get createActionForm
        /** @var SionForm $createActionForm **/
        $createActionForm = null;
        if (isset($entitySpec->createActionForm) && $parentLocator->has($entitySpec->createActionForm)) {
            $createActionForm = $parentLocator->get($entitySpec->createActionForm);
        } elseif (class_exists($entitySpec->createActionForm)) {
            $createActionForm = new $entitySpec->createActionForm();
        }

        //get editActionForm
        /** @var SionForm $editActionForm **/
        $editActionForm = null;
        if (isset($entitySpec->editActionForm) && $parentLocator->has($entitySpec->editActionForm)) {
            $editActionForm = $parentLocator->get($entitySpec->editActionForm);
        } elseif (class_exists($entitySpec->editActionForm)) {
            $className      = $entitySpec->editActionForm;
            $editActionForm = new $className();
        }

        //get sionModelConfig
        $config = $parentLocator->get('config');

        $logger = $container->get('SionModel\Logger');

        return new $requestedName(
            entity: $entity,
            entitiesService: $this->entitiesService,
            sionTable: $sionTable,
            predicatesTable: $predicatesTable,
            createActionForm: $createActionForm,
            editActionForm: $editActionForm,
            config: $config,
            logger: $logger
        );
    }
}
