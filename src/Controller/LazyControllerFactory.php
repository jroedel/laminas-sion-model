<?php

declare(strict_types=1);

namespace SionModel\Controller;

use Exception;
use Laminas\Form\FormElementManager;
use Laminas\Mvc\I18n\Translator;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Laminas\Validator\ValidatorPluginManager;
use Psr\Container\ContainerInterface;
use ReflectionClass;

use function array_key_exists;
use function explode;
use function str_contains;

class LazyControllerFactory implements AbstractFactoryInterface
{
    public function canCreate(ContainerInterface $container, $requestedName)
    {
        [$module] = explode('\\', __NAMESPACE__, 2);
        return str_contains($requestedName, $module . '\Controller');
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
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     *     creating a service.
     * @throws Exception if any other error occurs
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $class = new ReflectionClass($requestedName);
        if ($constructor = $class->getConstructor()) {
            if ($params = $constructor->getParameters()) {
                $parameterInstances = [];
                foreach ($params as $p) {
                    if ($p->getClass()) {
                        $cn = $p->getClass()->getName();
                        if (array_key_exists($cn, $this->aliases)) {
                            $cn = $this->aliases[$cn];
                        }

                        try {
                            $parameterInstances[] = $container->get($cn);
                        } catch (Exception) {
                            echo self::class
                            . " couldn't create an instance of $cn to satisfy the constructor for $requestedName.";
                            exit;
                        }
                    } else {
                        if ($p->isArray() && $p->getName() === 'config') {
                            $parameterInstances[] = $container->get('config');
                        }
                    }
                }
                return $class->newInstanceArgs($parameterInstances);
            }
        }

        return new $requestedName();
    }
}
