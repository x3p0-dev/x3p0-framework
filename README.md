# X3P0: Framework

![Nova, a blue alien, as a construction worker wearing a toolbelt and holding a wrench in a city construction zone.](https://repository-images.githubusercontent.com/1098370533/fc172954-cd4e-4669-be63-ef92774fcbbf)

A lightweight, modern dependency injection framework for WordPress plugins and themes. Built with PHP 8.1+, it provides a robust DI container and abstract application layer to help you write cleaner, more maintainable WordPress code.

[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net)

## Features

- **Autowiring container** — resolves constructor dependencies by type, including union and intersection types.
- **Declarative service providers** — describe bindings, aliases, tags, and bootables with simple class constants; drop to code only when you need it.
- **Attribute-driven injection** — `#[Get]`, `#[Defer]`, `#[Tagged]`, `#[DeferredTagged]`, `#[Make]`, `#[NoAutowire]`, and `#[Singleton]` configure resolution right at the point of use.
- **Flexible lifetimes** — singletons, transients, pre-built instances, aliases, and "register only if missing" defaults that extensions can override.
- **Contextual bindings** — give one consumer a different value or implementation than the rest of the app, by parameter name or by type.
- **Tagging** — group related services under a label and resolve them together, eagerly or lazily.
- **Lifecycle hooks** — observe (`resolving()`) or wrap (`decorate()`) services as they are built.
- **WordPress-friendly lifecycle** — register and boot across multiple load phases (`plugins_loaded`, `after_setup_theme`, …).
- **Type-safe** — full PHP 8.1+ type declarations for first-class IDE and static-analysis support.

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Service Providers](#service-providers)
- [The Container](#the-container)
  - [Binding services](#binding-services)
  - [Resolving services](#resolving-services)
  - [Autowiring](#autowiring)
  - [Attribute-based injection](#attribute-based-injection)
  - [Contextual bindings](#contextual-bindings)
  - [Tagging](#tagging)
  - [Lifecycle hooks](#lifecycle-hooks)
  - [Introspection](#introspection)
- [The Application](#the-application)
- [Contracts](#contracts)
- [Exceptions](#exceptions)
- [License](#license)

## Requirements

- PHP 8.1 or higher
- WordPress (latest version recommended)
- Composer

## Installation

```bash
composer require x3p0-dev/x3p0-framework
```

**Distributing a plugin or theme?** Vendor-prefix your dependencies with a tool like [PHP-Scoper](https://github.com/humbug/php-scoper) so your copy of the framework can't collide with another plugin's.

## Quick Start

The framework leans on **declarative configuration**: you describe what your providers contribute using class constants, list those providers on your application, and decide when registration and booting happen.

### 1. Define your services

Write plain classes. Constructor dependencies are autowired, so you rarely wire anything by hand.

```php
namespace Your\Project;

interface Cache {}

final class FileCache implements Cache {}

final class ReportBuilder
{
    // The container injects a Cache implementation automatically.
    public function __construct(private readonly Cache $cache) {}
}
```

### 2. Register them with a service provider

Prefer the declarative constants — `SINGLETONS`, `TRANSIENTS`, `ALIASES`, `TAGS`, `BOOTABLE` — over imperative calls. The base `register()` and `boot()` handle them for you.

```php
namespace Your\Project;

use X3P0\Framework\Core\ServiceProvider;

final class CacheServiceProvider extends ServiceProvider
{
    // Bind an interface to a concrete, shared across the request.
    protected const SINGLETONS = [
        Cache::class => FileCache::class
    ];

    // Resolve `'cache'` to the same binding as `Cache::class`.
    protected const ALIASES = [
        'cache' => Cache::class
    ];
}
```

### 3. Create your application

List your providers on the `PROVIDERS` constant. They're registered when the application is constructed.

```php
namespace Your\Project;

use X3P0\Framework\Core\Application;

final class Plugin extends Application
{
    protected const PROVIDERS = [
        CacheServiceProvider::class
    ];
}
```

### 4. Bootstrap it

The framework fires no hooks of its own — you choose when to register and boot. A typical plugin instantiates the application, fires a registration hook so third parties can add providers, then boots:

```php
namespace Your\Project;

use X3P0\Framework\Container\ServiceContainer;

require_once __DIR__ . '/vendor/autoload.php';

function plugin(): Plugin
{
    static $plugin;

    return $plugin ??= new Plugin(new ServiceContainer());
}

add_action('plugins_loaded', static function (): void {
    do_action('your/project/register', plugin());
    plugin()->boot();
}, -999);
```

That's the whole loop. The rest of this document covers what each piece can do.

## Service Providers

A service provider is the home for a slice of your project's wiring. Extend `ServiceProvider` and describe its contributions with constants. You only override `register()` or `boot()` when a binding needs real logic (a closure factory, a conditional, etc.).

```php
use X3P0\Framework\Core\ServiceProvider;

final class BlockServiceProvider extends ServiceProvider
{
    // Shared instances. A bare value is self-bound (the class is its own
    // concrete); a key => value pair binds an abstract to a concrete.
    protected const SINGLETONS = [
        BlockRegistry::class,
        Renderer::class => HtmlRenderer::class
    ];

    // New instance on every resolution. Same key conventions as SINGLETONS.
    protected const TRANSIENTS = [
        RequestContext::class
    ];

    // Overridable defaults: registered only if the abstract isn't already
    // bound, so an extension can replace them regardless of load order.
    protected const SINGLETONS_IF = [
        Logger::class => NullLogger::class
    ];
    protected const TRANSIENTS_IF = [
        View::class => PhpView::class
    ];

    // Alias => abstract. Resolving the alias resolves the abstract.
    protected const ALIASES = [
        'blocks' => BlockRegistry::class
    ];

    // Tag => list of abstracts, resolvable together via `tagged()`.
    protected const TAGS = [
        'theme.blocks' => [AlertBlock::class, CalloutBlock::class]
    ];

    // Services resolved and booted during the provider's boot phase. Each
    // must implement `Bootable`. They boot in the order listed.
    protected const BOOTABLE = [
        BlockRegistrar::class
    ];
}
```

### When you need code

Override `register()` for bindings that need a closure or runtime decisions, and call `parent::register()` to keep the constant-driven bindings:

```php
public function register(): void
{
    parent::register();

    $this->container->singleton(Connection::class, function (Container $c): Connection {
        return new Connection($c->get(Config::class)->dsn());
    });
}
```

Override `boot()` the same way (calling `parent::boot()`) when you need to do more than boot the `BOOTABLE` services — for example, hooking into WordPress:

```php
public function boot(): void
{
    parent::boot();

    add_action('init', $this->registerBlocks(...));
}
```

### Provider dependencies

Providers given by class name are resolved through the container, so they can type-hint their own dependencies. Accept the `Container`, pass it to the parent, and add whatever else you need:

```php
use X3P0\Framework\Container\Container;
use X3P0\Framework\Core\ServiceProvider;

final class ReportServiceProvider extends ServiceProvider
{
    public function __construct(Container $container, private readonly Clock $clock)
    {
        parent::__construct($container);
    }
}
```

## The Container

`ServiceContainer` is the framework's implementation of the `Container` contract. Inside a provider it's available as `$this->container`; elsewhere, via `plugin()->container()`.

### Binding services

```php
// Shared instance, built once and reused.
$container->singleton(Cache::class, FileCache::class);

// New instance on every resolution.
$container->transient(RequestContext::class);

// A value the container stores and returns as-is (never built or autowired).
$container->instance('config', new Config([...]));

// Alias one identifier to another (followed transitively).
$container->alias('cache', Cache::class);
```

The `*If` variants register a binding **only if the identifier isn't already bound**, which makes them ideal for defaults an extension may override regardless of load order:

```php
$container->singletonIf(Logger::class, NullLogger::class);
$container->transientIf(RequestContext::class);
```

Re-binding an identifier with `singleton()`/`transient()` replaces any existing binding and clears its cached instance, so the replacement takes effect on the next resolution.

### Resolving services

```php
// Resolve by identifier.
$cache = $container->get(Cache::class);

// Resolve a class, optionally overriding constructor parameters by name.
$report = $container->make(ReportBuilder::class, ['format' => 'pdf']);

// Invoke a callable with its parameters resolved from the container.
$result = $container->call([$controller, 'handle']);
$result = $container->call(SomeController::class . '::handle');

// Get a closure that resolves the service on each call — without the
// consumer needing the container itself.
$makeReport = $container->defer(ReportBuilder::class);
$report = $makeReport();
```

A parameterized `make()` (one given overrides) is never cached.

### Autowiring

When the container builds a class, it resolves each constructor parameter from its type — including union and intersection types, falling back to a default value or `null` when a parameter allows it. Most classes need no binding at all:

```php
final class ReportBuilder
{
    public function __construct(
        private readonly Cache $cache,      // resolved by type
        private readonly int $limit = 50,   // default used if not provided
    ) {}
}

$container->make(ReportBuilder::class);
```

Mark a class `#[Singleton]` to have the container share a single instance whenever it autowires the class, without an explicit binding:

```php
use X3P0\Framework\Container\Attributes\Singleton;

#[Singleton]
final class FileCache implements Cache {}
```

> **Note:** values that can't be constructed — enums, interfaces, and abstract classes — can't be autowired. Provide them with an explicit binding, a `make()` override, or an attribute (below).

### Attribute-based injection

Parameter attributes configure how a single dependency is resolved, right where it's declared.

```php
use X3P0\Framework\Container\Attributes\Get;
use X3P0\Framework\Container\Attributes\Defer;
use X3P0\Framework\Container\Attributes\Tagged;
use X3P0\Framework\Container\Attributes\DeferredTagged;
use X3P0\Framework\Container\Attributes\Make;
use X3P0\Framework\Container\Attributes\NoAutowire;

final class Dashboard
{
    public function __construct(
        // Resolve a specific identifier (a keyed binding or a chosen concrete).
        #[Get('config')] private readonly Config $config,

        // Inject a closure that resolves the service lazily, on demand.
        #[Defer(ReportBuilder::class)] private readonly Closure $makeReport,

        // Inject every service assigned to a tag, already resolved.
        #[Tagged('theme.blocks')] private readonly iterable $blocks,

        // Inject the tagged services as deferred resolvers, keyed by class,
        // so you build only the ones you actually use.
        #[DeferredTagged('report.sections')] private readonly array $sections,

        // Build a dependency with inline constructor overrides — a fresh,
        // unshared instance configured right here.
        #[Make(TransientCache::class, ['ttl' => 3600])] private readonly Cache $cache,

        // Skip autowiring so the parameter keeps its default instead of the
        // container building the type — here, leaving `$user` null rather
        // than constructing a WP_User.
        #[NoAutowire] private readonly ?WP_User $user = null
    ) {}
}
```

| Attribute                 | Target    | Injects                                                                 |
|---------------------------|-----------|-------------------------------------------------------------------------|
| `#[Get($id)]`             | parameter | the result of `get($id)`                                                |
| `#[Defer($id)]`           | parameter | a `Closure` that resolves `$id` on each call                            |
| `#[Tagged($tag)]`         | parameter | an array of the tag's resolved services                                 |
| `#[DeferredTagged($tag)]` | parameter | `array<class-string, Closure>` of deferred resolvers, keyed by abstract |
| `#[Make($id, $params)]`   | parameter | `make($id, $params)` — a fresh instance built with literal overrides    |
| `#[NoAutowire]`           | parameter | nothing — skips autowiring so the declared default (or `null`) is kept  |
| `#[Singleton]`            | class     | opts an autowired class into a shared lifetime                          |

You can build your own by implementing `ContextualAttribute`:

```php
use X3P0\Framework\Container\Attributes\ContextualAttribute;
use X3P0\Framework\Container\Container;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class CurrentUser implements ContextualAttribute
{
    public function resolve(Container $container): object
    {
        return $container->get(UserRepository::class)->current();
    }
}
```

### Contextual bindings

Sometimes a single consumer needs a value that differs from the rest of the app — a scalar the container can't autowire, or a different implementation of an interface. Contextual bindings say "when the container builds *this* class, supply *this* for that parameter." The consumer is the concrete class being built.

Bind **by parameter name** to supply a value the container can't resolve by type (a scalar, an array). The name is given without a leading `$`, and the value is passed as-is — or, if it's a closure, its return value is used:

```php
$container->whenNeedsParam(Mailer::class, 'apiKey', 'secret');
$container->whenNeedsParam(Mailer::class, 'timeout',
    fn ($container) => $container->get('config')->get('mail.timeout'));
```

Bind **by type** to give one consumer a different implementation than everyone else. The concrete is a class-string resolved through the container (honoring its own binding, lifetime, and hooks), or a closure:

```php
// Cache resolves to FileCache application-wide, but ExportJob gets RedisCache.
$container->singleton(Cache::class, FileCache::class);
$container->whenNeedsType(ExportJob::class, Cache::class, RedisCache::class);
```

A contextual binding sits below an explicit `make()` override and any parameter attribute, but above ordinary type autowiring — so `make(Mailer::class, ['apiKey' => '…'])` still wins, and a binding registered for one consumer never leaks to another.

### Tagging

Tagging groups related abstracts under a label so they can be resolved together — blocks, widgets, REST controllers, CLI commands, and the like — without maintaining a master list by hand.

```php
$container->tag([AlertBlock::class, CalloutBlock::class], 'theme.blocks');

foreach ($container->tagged('theme.blocks') as $block) {
    $block->register();
}
```

Tagged abstracts resolve through the container like anything else, so singletons stay shared and unbound classes are autowired. An unknown tag resolves to an empty array.

| Method                    | Returns                                         |
|---------------------------|-------------------------------------------------|
| `tag($abstracts, $tag)`   | — assigns one or more abstracts to a tag        |
| `untag($abstracts, $tag)` | — removes abstracts from a tag                  |
| `tagged($tag)`            | the tag's services, resolved                    |
| `taggedAbstracts($tag)`   | the tag's abstracts, **without** resolving them |
| `hasTag($tag)`            | whether any abstracts are currently assigned    |

Because tags accumulate, several providers — or third-party code hooking your registration action — can contribute to the same tag without touching the provider that consumes it:

```php
add_action('your/project/register', static function ($app): void {
    $app->container()->singleton(TestimonialBlock::class);
    $app->container()->tag(TestimonialBlock::class, 'theme.blocks');
});
```

For large or expensive collections, pair a tag with `#[DeferredTagged]` so consumers receive per-service resolver closures (keyed by class name) and build only what they need.

### Lifecycle hooks

Observe or transform services as they're built.

```php
// Run after the service is built, to mutate it in place. Runs once per build,
// so a singleton is observed only the first time it's created.
$container->resolving(ReportBuilder::class, function (object $builder, Container $c): void {
    $builder->setTimezone($c->get(Config::class)->timezone());
});

// Wrap or replace the service with something honoring the same contract.
// Decorators stack in registration order. If the service is already
// resolved, the decorator is applied to the stored instance immediately.
$container->decorate(Cache::class, function (Cache $cache, Container $c): Cache {
    return new LoggingCache($cache, $c->get(Logger::class));
});
```

### Introspection

```php
$container->has(Cache::class);            // resolvable? (bound or an autowirable class)
$container->registered(Cache::class);     // explicitly bound or instance-registered?
$container->resolved(Cache::class);       // already built and cached?
$container->forgetInstance(Cache::class); // drop the cached instance; rebuild next time
```

## The Application

`Application` is the hub that registers and boots your service providers. Subclass it, list providers on `PROVIDERS`, and drive the lifecycle from your plugin or theme.

```php
final class Plugin extends Application
{
    protected const PROVIDERS = [
        CoreServiceProvider::class,
        AdminServiceProvider::class,
        FrontendServiceProvider::class
    ];
}
```

### Registering and booting

`register()` is variadic and accepts provider instances or class names. Class names are resolved through the container, so providers can declare their own dependencies.

```php
$app->register(AdminServiceProvider::class, RestServiceProvider::class);
```

`boot()` boots every registered-but-unbooted provider and is safe to call repeatedly — each provider boots only once. Once the application has booted, a provider registered afterward boots immediately, so nothing registered late is left dormant. A batch passed to `register()` is registered in full before any of it boots.

### Multiple load phases

To register across more than one WordPress phase, call `begin()` to open each pass. It clears the booted state (so that pass's providers register as a batch before booting) and returns the application, ready to hand to a registration hook:

```php
add_action('plugins_loaded', static function (): void {
    do_action('your/project/register/plugin', plugin()->begin());
    plugin()->boot();
}, -999);

add_action('after_setup_theme', static function (): void {
    do_action('your/project/register/theme', plugin()->begin());
    plugin()->boot();
}, -999);
```

A single register-then-boot pass doesn't need `begin()`; it's required only to open each additional pass (and harmless on the first).

## Contracts

The `X3P0\Framework\Contracts` namespace holds small, dependency-free interfaces.

- **`Bootable`** — a `boot(): void` method for deferred setup that shouldn't live in a constructor (registering hooks, etc.). Service providers implement it, and any abstract listed in a provider's `BOOTABLE` constant must too.
- **`Renderable`** — a `render(): string` method for classes that produce escaped, safe HTML.
- **`ClassRegistry`** — a registry of class *names* (not instances) indexed by key: `register()`, `unregister()`, `isRegistered()`, and `get()`.

## Exceptions

All container failures surface as `X3P0\Framework\Container\ContainerException`; an unknown identifier throws `NotFoundException` (a subtype, so catching the base covers both). On the application side, `InvalidProviderException` is thrown when a registered class isn't a `ServiceProvider`, and `UnbootableServiceException` when a `BOOTABLE` entry doesn't implement `Bootable`. Both extend `ApplicationException`.

## License

X3P0 Framework is licensed under the [GPL-2.0-or-later](LICENSE.md) license.

## Credits

Created and maintained by [Justin Tadlock](https://github.com/justintadlock) under the [X3P0](https://github.com/x3p0-dev) umbrella.

## Support

- [GitHub Issues](https://github.com/x3p0-dev/x3p0-framework/issues)
- [Packagist](https://packagist.org/packages/x3p0-dev/x3p0-framework)
