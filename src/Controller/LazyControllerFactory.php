<?php

declare(strict_types=1);

namespace SionModel\Controller;

use Exception;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;

use ReflectionNamedType;
use Webmozart\Assert\Assert;
use function str_contains;

class LazyControllerFactory implements AbstractFactoryInterface
{
    /**
     * @param ContainerInterface $container
     * @param $requestedName
     * @return bool
     */
    public function canCreate(ContainerInterface $container, $requestedName)
    {
        return str_contains($requestedName, '\\Controller\\') && ! str_contains($requestedName, 'Test');
    }

    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return mixed|object|null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \ReflectionException
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $class = new ReflectionClass($requestedName);
        if ($constructor = $class->getConstructor()) {
            if ($params = $constructor->getParameters()) {
                $paramNum           = 0;
                $parameterInstances = [];
                foreach ($params as $param) {
                    ++$paramNum;
                    $paramType = $param->getType();
                    Assert::notNull(
                        $paramType,
                        self::class . "can't instantiate $requestedName. Can only fill typed constructor parameters."
                    );
                    Assert::isInstanceOf(
                        $paramType,
                        ReflectionNamedType::class,
                        self::class . " doesn't support union or intersection types"
                    );

                    if ($paramType->isBuiltin()) {
                        if ('array' === $paramType->getName() && $param->getName() === 'config') {
                            $parameterInstances[] = $container->get('config');
                            continue;
                        }
                        throw new Exception(
                            self::class . " can't instantiate $requestedName because param number $paramNum is builtin."
                        );
                    }
                    $className = $paramType->getName();
                    Assert::notNull($className);
                    if ($container->has($className)) {
                        try {
                            $parameterInstances[] = $container->get($className);
                        } catch (Exception $e) {
                            throw new Exception(
                                self::class . " couldn't create an instance of $className to satisfy the constructor "
                                . "for $requestedName.",
                                0,
                                $e
                            );
                        }
                    } else {
                        //try to just instantiate one
                        try {
                            $parameterInstances[] = new $className();
                        } catch (Exception $e) {
                            throw new Exception(
                                self::class . " couldn't create an instance of $className to satisfy the constructor "
                                . "for $requestedName.",
                                0,
                                $e
                            );
                        }
                    }
                }
                return $class->newInstanceArgs($parameterInstances);
            }
        }

        return new $requestedName();
    }
}
