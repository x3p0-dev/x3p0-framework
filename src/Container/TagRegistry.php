<?php

/**
 * Tag registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Container;

/**
 * Stores tag membership, per-member attributes, and per-tag contracts on
 * behalf of the container. Holds only abstracts — resolving a tagged member
 * into an instance is the container's job, since this registry has no way to
 * build objects.
 */
final class TagRegistry
{
	/**
	 * Maps tag names to the list of abstracts assigned to them.
	 *
	 * @var array<string, array<string>>
	 */
	private array $tags = [];

	/**
	 * Maps a tag name and abstract to the attributes it was tagged with.
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	private array $tagAttributes = [];

	/**
	 * Maps a tag name to the abstract every member must be a concrete class
	 * of, set via `setContract()`. Tags without a contract are absent from
	 * this map.
	 *
	 * @var array<string, class-string>
	 */
	private array $tagContracts = [];

	/**
	 * Assign one or more abstracts to a tag so they can be resolved together.
	 * Abstracts already assigned to the tag are ignored, so a tag never holds
	 * duplicates.
	 *
	 * If the tag has a contract (see `setContract()`), each member is
	 * validated as it is tagged and a mistagged one is rejected up front.
	 *
	 * @param string|array<string> $abstracts
	 * @throws ContainerException When a member violates the tag's contract.
	 */
	public function tag(string|array $abstracts, string $tag, array $attributes = []): void
	{
		$contract = $this->tagContracts[$tag] ?? null;

		foreach ((array) $abstracts as $abstract) {
			// A typed tag holds concrete implementations of its
			// contract, so each new member is validated as it is
			// added rather than deferred to resolution.
			if ($contract !== null) {
				$this->assertConcreteOf($tag, $abstract, $contract);
			}

			if (! in_array($abstract, $this->tags[$tag] ?? [], true)) {
				$this->tags[$tag][] = $abstract;
			}

			if ($attributes !== []) {
				$this->tagAttributes[$tag][$abstract] = $attributes;
			}
		}
	}

	/**
	 * Type a tag: declare that every member must be a concrete class of the
	 * given contract. This makes the tag a homogeneous set of implementations
	 * — `abstracts()` and `abstractsWith()` are guaranteed to only ever yield
	 * concrete classes of `$contract`.
	 *
	 * The contract is enforced at every point it can be: members already
	 * tagged are validated now, members tagged later are validated by
	 * `tag()`, and every read (`abstracts()`, `abstractsWith()`) validates as
	 * a backstop — so a violation is caught regardless of when the offending
	 * member was tagged.
	 *
	 * The contract is set once; a later call naming a different contract for
	 * the same tag is an error.
	 *
	 * @param class-string $contract
	 * @throws ContainerException When a member already tagged violates the
	 *                            contract, or a conflicting contract is
	 *                            declared.
	 */
	public function setContract(string $tag, string $contract): void
	{
		// The contract is set once. A later call naming a different
		// contract for the same tag is a conflict, not a silent override.
		if (($this->tagContracts[$tag] ?? $contract) !== $contract) {
			throw new ContainerException(esc_html(sprintf(
				'Tag "%s" is already typed as "%s"; cannot retype it as "%s".',
				$tag,
				$this->tagContracts[$tag],
				$contract
			)));
		}

		$this->tagContracts[$tag] = $contract;

		// Validate members tagged before the contract was declared, so a
		// mistake surfaces here rather than waiting until resolution.
		$this->assertContract($tag);
	}

	/**
	 * Remove one or more abstracts from a tag, leaving the rest in place.
	 *
	 * @param string|array<string> $abstracts
	 */
	public function untag(string|array $abstracts, string $tag): void
	{
		if (! isset($this->tags[$tag])) {
			return;
		}

		$this->tags[$tag] = array_values(array_diff(
			$this->tags[$tag],
			(array) $abstracts
		));

		foreach ((array) $abstracts as $abstract) {
			unset($this->tagAttributes[$tag][$abstract]);
		}
	}

	/**
	 * Return the abstracts assigned to the given tag, without resolving
	 * them. The order matches assignment order, and an unknown tag yields an
	 * empty array. For a typed tag (see `setContract()`), every member is a
	 * concrete class of that contract.
	 *
	 * @return array<string>
	 * @throws ContainerException
	 */
	public function abstracts(string $tag): array
	{
		$this->assertContract($tag);

		return $this->tags[$tag] ?? [];
	}

	/**
	 * Returns a map from a chosen attribute's value to its abstract, for
	 * every member of `$tag` that was given that attribute.
	 *
	 * @return array<mixed, string>
	 * @throws ContainerException
	 */
	public function abstractsWith(string $tag, string $attribute): array
	{
		$this->assertContract($tag);

		$map = [];

		foreach ($this->tags[$tag] ?? [] as $abstract) {
			$value = $this->tagAttributes[$tag][$abstract][$attribute] ?? null;

			if ($value !== null) {
				$map[$value] = $abstract;
			}
		}

		return $map;
	}

	/**
	 * Check whether any abstracts are currently assigned to the given tag. A
	 * tag whose abstracts have all been removed reports `false`, the same as
	 * a tag that was never assigned.
	 */
	public function has(string $tag): bool
	{
		return ! empty($this->tags[$tag]);
	}

	/**
	 * Assert that a single abstract is a concrete class of the tag's
	 * contract, throwing otherwise. Shared by every enforcement point.
	 *
	 * @throws ContainerException
	 */
	private function assertConcreteOf(string $tag, string $abstract, string $contract): void
	{
		if (! class_exists($abstract) || ! is_a($abstract, $contract, true)) {
			throw new ContainerException(esc_html(sprintf(
				'Tag "%s" requires each member to be a concrete class of "%s"; "%s" is not.',
				$tag,
				$contract,
				$abstract
			)));
		}
	}

	/**
	 * Assert that every member currently assigned to the tag satisfies its
	 * contract. A no-op for an untyped tag, so it is safe to call from any
	 * read path as a backstop against members tagged before the contract was
	 * declared.
	 *
	 * @throws ContainerException
	 */
	private function assertContract(string $tag): void
	{
		$contract = $this->tagContracts[$tag] ?? null;

		if ($contract === null) {
			return;
		}

		foreach ($this->tags[$tag] ?? [] as $abstract) {
			$this->assertConcreteOf($tag, $abstract, $contract);
		}
	}
}
