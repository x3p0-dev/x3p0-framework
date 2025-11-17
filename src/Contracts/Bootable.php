<?php

/**
 * Bootable interface.
 *
 * @package   X3P0\Framework
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Contracts;

/**
 * Defines the contract that bootable classes should utilize. Bootable classes
 * should have a `boot()` method with the singular purpose of "booting" any
 * necessary code that needs to run. Most bootable classes are meant to be
 * single-instance classes that get loaded once per page request.
 */
interface Bootable
{
	/**
	 * This is often useful for adding actions or filters in WordPress, but
	 * it can also be used to bootstrap any other code that you wouldn't
	 * normally add to the class constructor.
	 */
	public function boot(): void;
}
