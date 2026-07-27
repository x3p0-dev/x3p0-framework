<?php

/**
 * Singleton attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Container\Attributes;

use Attribute;

/**
 * Declares, on the class itself, which container tag it belongs to and any
 * attributes to record alongside it — the class-level counterpart to
 * `Container::tag()`. A class states its own tag membership once, in one
 * place, instead of every caller handing it to the container needing to
 * know the tag name and repeat attributes the class already exposes some
 * other way (a slug, say).
 *
 * Repeatable, so a single class may belong to more than one tag.
 *
 *     #[Tag('channels', ['slug' => 'email'])]
 *     final class Email extends Channel {}
 *
 * The class still has to be handed to the container once —
 * `Container::tagFromAttributes()` reads this attribute and calls `tag()`
 * on the class's behalf. This removes the need for the caller to know the
 * tag name or repeat its attributes.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Tag
{
	/**
	 * @param array<string, mixed> $attributes
	 */
	public function __construct(
		private readonly string $tag,
		private readonly array  $attributes = []
	) {}

	public function tag(): string
	{
		return $this->tag;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function attributes(): array
	{
		return $this->attributes;
	}
}
