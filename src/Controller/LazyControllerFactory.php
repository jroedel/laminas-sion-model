<?php

namespace SionModel\Controller;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;

class LazyControllerFactory implements AbstractFactoryInterface
{
    public function canCreate(ContainerInterface $container, $requestedName)
    {
        list( $module, ) = explode('\\', __NAMESPACE__, 2);
        return strstr($requestedName, $module . '\Controller') !== false;
    }

    /**
     * These aliases work to substitute class names with SM types that are buried in ZF
     * @var array
     */
    protected $aliases = [
        'Laminas\Form\FormElementManager' => 'FormElementManager',
        'Laminas\Validator\ValidatorPluginManager' => 'ValidatorManager',
        'Laminas\Mvc\I18n\Translator' => 'translator',
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
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $class = new \ReflectionClass($requestedName);
        $parentLocator = $container->getServiceLocator();
        if ($constructor = $class->getConstructor()) {
            if ($params = $constructor->getParameters()) {
                $parameter_instances = [];
                foreach ($params as $p) {
                    if ($p->getClass()) {
                        $cn = $p->getClass()->getName();
                        if (array_key_exists($cn, $this->aliases)) {
                            $cn = $this->aliases[$cn];
                        }

                        try {
                            $parameter_instances[] = $parentLocator->get($cn);
                        } catch (\Exception $x) {
                            echo __CLASS__
                            . " couldn't create an instance of $cn to satisfy the constructor for $requestedName.";
                            exit;
                        }
                    } else {
                        if ($p->isArray() && $p->getName() == 'config') {
                            $parameter_instances[] = $parentLocator->get('config');
                        }
                    }
                }
                return $class->newInstanceArgs($parameter_instances);
            }
        }

        return new $requestedName();
    }
}
