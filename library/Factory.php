<?php

/*
 * This file is part of Respect/Validation.
 *
 * (c) Alexandre Gomes Gaigalas <alexandre@gaigalas.net>
 *
 * For the full copyright and license information, please view the "LICENSE.md"
 * file that was distributed with this source code.
 */

namespace Respect\Validation;

use Doctrine\Common\Annotations\SimpleAnnotationReader;
use ReflectionClass;
use Respect\Validation\Annotations\Template;
use Respect\Validation\Annotations\Templates;
use Respect\Validation\Exceptions\ComponentException;
use Respect\Validation\Exceptions\InvalidRuleException;
use Respect\Validation\Exceptions\RuleNotFoundException;
use Respect\Validation\Message\Formatter;

/**
 * Factory to create rules.
 *
 * @author Henrique Moody <henriquemoody@gmail.com>
 *
 * @since 0.8.0
 */
final class Factory
{
    /**
     * @var string[]
     */
    private $namespaces;

    /**
     * @var ReflectionClass[]
     */
    private $reflections;

    /**
     * @var self
     */
    private static $defaultInstance;

    /**
     * Initializes the rule with the defined namespaces.
     *
     * If the default namespace is not in the array, it will be add to the end
     * of the array.
     *
     * @param array $namespaces
     */
    public function __construct(array $namespaces = [])
    {
        if (!in_array(__NAMESPACE__, $namespaces)) {
            $namespaces[] = __NAMESPACE__;
        }

        $this->namespaces = $namespaces;
    }

    /**
     * Defines the default instance of the factory.
     *
     * @param Factory $factory
     */
    public static function setDefaultInstance(self $factory)
    {
        self::$defaultInstance = $factory;
    }

    /**
     * Returns the default instance of the factory.
     *
     * @return self
     */
    public static function getDefaultInstance(): self
    {
        if (!self::$defaultInstance instanceof self) {
            self::$defaultInstance = new self();
        }

        return self::$defaultInstance;
    }

    /**
     * Returns a list of namespaces.
     *
     * @return array
     */
    public function getNamespaces(): array
    {
        return $this->namespaces;
    }

    /**
     * Creates a rule based on its name with the defined arguments.
     *
     * @param string $ruleName
     * @param array  $arguments
     *
     * @throws ComponentException When the rule cannot be created
     *
     * @return Rule
     */
    public function rule(string $ruleName, array $arguments = []): Rule
    {
        foreach ($this->getNamespaces() as $namespace) {
            $className = rtrim($namespace, '\\').'\\Rules\\'.ucfirst($ruleName);
            if (!class_exists($className)) {
                continue;
            }

            $reflection = $this->getReflection($className);

            if (!$reflection->isSubclassOf(Rule::class)) {
                throw new InvalidRuleException(sprintf('"%s" is not a valid rule', $className));
            }

            if (!$reflection->isInstantiable()) {
                throw new InvalidRuleException(sprintf('"%s" is not instantiable', $className));
            }

            return $reflection->newInstanceArgs($arguments);
        }

        throw new RuleNotFoundException(sprintf('Could not find "%s" rule', $ruleName));
    }

    private function getMessage(ReflectionClass $reflection, Result $result): string
    {
        $reader = new SimpleAnnotationReader();
        $reader->addNamespace('Respect\\Validation\\Annotations');

        $templates = $reader->getClassAnnotation($reflection, Templates::class);

        $template = $this->chooseTemplate($templates, $result);

        $formatter = new Formatter(2, 3);

        return $formatter->create($result->getInput(), $result->getProperties(), $template->message);
    }

    public function exception(Result $result)
    {
        $exceptionName = str_replace('Rule', 'Exception', get_class($result->getRule())).'Exception';

        $reflection = $this->getReflection($exceptionName);
        $message = $this->getMessage($reflection, $result);

        return $reflection->newInstance($message);
    }

    private function chooseTemplate(Templates $templates, Result $result): Template
    {
        $templatesList = $templates->regular;
        if ($result->isInverted()) {
            $templatesList = $templates->inverted;
        }

        $templateId = 'standard';
        $properties = $result->getProperties();
        if (isset($properties['templateId'])) {
            $templateId = $properties['templateId'];
        }

        foreach ($templatesList as $template) {
            if ($template->id != $templateId) {
                continue;
            }

            return $template;
        }

        return current($templateLists);
    }

    /**
     * Creates a ReflectionClass object based on a class name.
     *
     * This method always return the same object for a given class name in order
     * to improve performance.
     *
     * @param string $className
     *
     * @return ReflectionClass
     */
    private function getReflection(string $className): ReflectionClass
    {
        if (!isset($this->reflections[$className])) {
            $this->reflections[$className] = new ReflectionClass($className);
        }

        return $this->reflections[$className];
    }
}
