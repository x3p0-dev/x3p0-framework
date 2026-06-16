<?php

/**
 * Container exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Container;

use Exception;

/**
 * Base exception for any container failure, such as a binding that cannot be
 * built or an unresolvable constructor dependency. Catch this to handle any
 * container-originated error.
 */
class ContainerException extends Exception
{
}
