<?php

/**
 * Hookable trait.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Support\Hooks;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * A trait for defining attribute-based actions and filters with class members.
 * It satisfies the framework's `Bootable` contract by registering the hooks in
 * its `boot()` method, so a class can simply `use Hookable` and implement
 * `Bootable` to have its `#[Action]`/`#[Filter]` members wired automatically.
 */
trait Hookable
{
	/**
	 * Stores the instance of the reflected class.
	 */
	protected ReflectionClass $reflector;

	/**
	 * Boots the component, running its actions/filters.
	 */
	#[\Override]
	public function boot(): void
	{
		$this->hookMembers();
	}

	/**
	 * Returns the reflection of the current class.
	 */
	protected function getReflector(): ReflectionClass
	{
		if (! isset($this->reflector)) {
			$this->reflector = new ReflectionClass($this);
		}

		return $this->reflector;
	}

	/**
	 * Adds all class members with attributes that have the `Hook` contract
	 * as actions or filters.
	 *
	 * @throws ReflectionException
	 */
	protected function hookMembers(): void
	{
		$this->hookMethods();
		$this->hookProperties();
		$this->hookConstants();
	}

	/**
	 * Adds constants with attributes that have the `Hook` contract as
	 * anonymous actions or filters.
	 */
	protected function hookConstants(): void
	{
		foreach ($this->getReflector()->getReflectionConstants() as $constant) {
			$attributes = $constant->getAttributes(
				Hook::class,
				ReflectionAttribute::IS_INSTANCEOF
			);

			foreach ($attributes as $attribute) {
				$attribute->newInstance()->register(
					fn() => $constant->getValue()
				);
			}
		}
	}

	/**
	 * Adds methods with attributes that have the `Hook` contract as actions
	 * or filters.
	 *
	 * @throws ReflectionException
	 */
	protected function hookMethods(): void
	{
		// Grab methods of any visibility, excluding the constructor.
		$methods = array_filter(
			$this->getReflector()->getMethods(
				ReflectionMethod::IS_PUBLIC
				| ReflectionMethod::IS_PROTECTED
				| ReflectionMethod::IS_PRIVATE
			),
			fn($method) => ! $method->isConstructor()
		);

		foreach ($methods as $method) {
			$attributes = $method->getAttributes(
				Hook::class,
				ReflectionAttribute::IS_INSTANCEOF
			);

			foreach ($attributes as $attribute) {
				// Register a bound closure rather so that
				// protected and private methods can be used as
				// hook callbacks.
				$attribute->newInstance()->register(
					$method->getClosure($this),
					$method->getNumberOfParameters()
				);
			}
		}
	}

	/**
	 * Adds properties with attributes that have the `Hook` contract as
	 * anonymous actions or filters.
	 */
	protected function hookProperties(): void
	{
		foreach ($this->getReflector()->getProperties() as $property) {
			$attributes = $property->getAttributes(
				Hook::class,
				ReflectionAttribute::IS_INSTANCEOF
			);

			foreach ($attributes as $attribute) {
				$attribute->newInstance()->register(
					fn() => $property->getValue($this)
				);
			}
		}
	}
}
