# PHP Dagger Module

A Dagger module for building PHP/Symfony CI/CD pipelines. Provides a fluent API to configure PHP containers with Composer, PIE, PECL, Symfony CLI, and PHPUnit support — all from a single, composable module.

## Requirements

- [Dagger](https://docs.dagger.io/install) >= v0.19.11

## Installation

```bash
dagger install github.com/Neirda24/dagger-php-module/php
```

## Quick Start

```bash
# Run composer install on your project
dagger call with-sources --sources=. with-composer install end-composer container terminal

# Run PHPUnit tests
dagger call with-sources --sources=. with-composer install end-composer phpunit stdout
```

## Usage Examples

### Basic PHP + Composer

```php
dag()
    ->php('8.3-cli')
    ->withSources($sources)
    ->withComposer()
        ->install()
    ->endComposer()
    ->container()
```

```bash
dagger call \
  with-sources --sources=. \
  with-composer install end-composer \
  container terminal
```

### PHP with Extensions (PIE)

```php
dag()
    ->php('8.3-cli')
    ->withSources($sources)
    ->withPie()
        ->install(['xdebug/xdebug', 'xdebug/xdebug:^3.4'])
    ->endPie()
    ->withComposer()
        ->install()
    ->endComposer()
    ->container()
```

### PHP with Legacy Extensions (PECL)

```php
dag()
    ->php('8.3-cli')
    ->withPecl()
        ->install(['redis', 'imagick'])
    ->endPecl()
    ->container()
```

### Symfony Pipeline

```php
dag()
    ->php('8.3-fpm')
    ->withSources($sources)
    ->withComposer()
        ->install(dev: false, optimizeAutoloader: true)
    ->endComposer()
    ->withSymfony(appEnv: 'prod')
        ->clearCache()
        ->migrate()
    ->endSymfony()
    ->container()
```

```bash
dagger call \
  with-sources --sources=. \
  with-composer install --dev=false end-composer \
  with-symfony --app-env=prod clear-cache migrate end-symfony \
  container terminal
```

### Run PHPUnit Tests

```php
dag()
    ->php('8.3-cli')
    ->withSources($sources)
    ->withComposer()
        ->install()
    ->endComposer()
    ->phpunit(['--testsuite=unit'])
    ->stdout()
```

```bash
dagger call \
  with-sources --sources=. \
  with-composer install end-composer \
  phpunit stdout
```

### Production Build

```php
dag()
    ->php('8.3-fpm')
    ->withSources($sources)
    ->withComposer()
        ->install(
            dev: false,
            optimizeAutoloader: true,
            classMapAuthoritative: true,
            audit: AuditFormat::Summary,
        )
    ->endComposer()
    ->container()
```

## Function Reference

### `Php` (entry point)

| Function | Description |
|---|---|
| `__construct(phpTagOrVersion, repository)` | Create a PHP container from an image tag |
| `withSources(sources, path)` | Mount source code into the container |
| `withVersion(phpTagOrVersion, repository)` | Switch PHP version |
| `withComposer()` | Enter Composer sub-context |
| `withPie()` | Enter PIE extension installer sub-context |
| `withPecl()` | Enter PECL legacy extension installer sub-context |
| `withSymfony(appEnv)` | Enter Symfony tooling sub-context |
| `withEnvVariable(name, value)` | Set an environment variable |
| `withContainer(container)` | Replace the base container |
| `phpunit(args)` | Run PHPUnit (returns Container) |
| `container()` | Export the underlying container |
| `stdout()` / `stderr()` | Get container output |

### `Composer` sub-context (`withComposer()`)

| Function | Description |
|---|---|
| `install(packages, dev, autoloader, optimizeAutoloader, classMapAuthoritative, progress, audit, preferInstall)` | Run `composer install` |
| `run(args)` | Run any Composer command |
| `endComposer()` | Return to `Php` chain |
| `container()` | Export container directly |

### `Pie` sub-context (`withPie()`)

| Function | Description |
|---|---|
| `install(packages, force)` | Install PIE-compatible extensions |
| `endPie()` | Return to `Php` chain |
| `container()` | Export container directly |

### `Pecl` sub-context (`withPecl()`)

| Function | Description |
|---|---|
| `install(packages, force)` | Install PECL extensions |
| `endPecl()` | Return to `Php` chain |
| `container()` | Export container directly |

### `Symfony` sub-context (`withSymfony()`)

| Function | Description |
|---|---|
| `withCli()` | Install the Symfony CLI binary |
| `console(args)` | Run `php bin/console <args>` |
| `clearCache()` | Run `cache:clear` |
| `migrate()` | Run `doctrine:migrations:migrate` |
| `endSymfony()` | Return to `Php` chain |
| `container()` | Export container directly |

## Dagger Engine Compatibility

Requires Dagger engine `v0.19.11` or newer.
