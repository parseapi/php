<?php

declare(strict_types=1);

namespace ParseAPI\Tests;

use ParseAPI\Client;
use ParseAPI\ParseAPIError;

final class PublicApiSnapshot
{
	public static function capture(): array
	{
		$result = [];
		foreach ([Client::class, ParseAPIError::class] as $name) {
			$class = new \ReflectionClass($name);
			$methods = [];
			foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
				if ($method->getDeclaringClass()->getName() !== $name) {
					continue;
				}
				$parameters = [];
				foreach ($method->getParameters() as $parameter) {
					$entry = ['name' => $parameter->getName(), 'type' => (string) $parameter->getType()];
					if ($parameter->isDefaultValueAvailable()) {
						$entry['default'] = $parameter->getDefaultValue();
					}
					$parameters[] = $entry;
				}
				$methods[$method->getName()] = ['parameters' => $parameters, 'return' => (string) $method->getReturnType()];
			}
			ksort($methods);
			$properties = [];
			foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
				if ($property->getDeclaringClass()->getName() === $name) {
					$properties[$property->getName()] = ['type' => (string) $property->getType(), 'readonly' => $property->isReadOnly()];
				}
			}
			ksort($properties);
			$result[$name] = ['final' => $class->isFinal(), 'methods' => $methods, 'properties' => $properties];
		}
		return $result;
	}
}
