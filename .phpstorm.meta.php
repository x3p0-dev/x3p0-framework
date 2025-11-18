<?php

/**
 * PhpStorm metadata, particularly for the service container.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framwework
 */

declare (strict_types = 1);

namespace PHPSTORM_META
{
	// For get() method.
	override(\X3P0\Framework\Container\Container::get(0), map(['' => '@']));

	// For resolve() method.
	override(\X3P0\Framework\Container\Container::resolve(0), map(['' => '@']));

	// For make() method.
	override(\X3P0\Framework\Container\Container::make(0), map(['' => '@']));
}
