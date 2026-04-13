# PHPStan Dagger Module

A Dagger module for running [PHPStan](https://phpstan.org/) static analysis on PHP projects. Uses the official `ghcr.io/phpstan/phpstan` Docker image — no local PHP installation required.

## Requirements

- [Dagger](https://docs.dagger.io/install) >= v0.19.11

## Installation

```bash
dagger install github.com/Neirda24/dagger-php-module/phpstan
```

## Quick Start

```bash
# Analyse your project at level 5 (default)
dagger call analyse --sources=./src

# Analyse at level 8 with a config file
dagger call analyse --sources=. --level=8 --config=phpstan.neon

# Analyse specific paths only
dagger call analyse --sources=. --paths=src/ --paths=tests/
```

## Usage Examples

### Basic analysis

```php
dag()
    ->phpstan()
    ->analyse(sources: $sources, level: '5')
```

### With configuration file

```php
dag()
    ->phpstan()
    ->analyse(
        sources: $sources,
        level: 'max',
        config: 'phpstan.dist.neon',
    )
```

### Specific paths

```php
dag()
    ->phpstan()
    ->analyse(
        sources: $sources,
        level: '8',
        paths: ['src/', 'tests/'],
    )
```

## Function Reference

### `Phpstan` (entry point)

| Function | Description |
|---|---|
| `analyse(sources, level, config, paths)` | Run PHPStan analysis and return the report as a string |

**Parameters:**

| Parameter | Type | Default | Description |
|---|---|---|---|
| `sources` | `Directory` | `.` (current dir) | Source directory to analyse |
| `level` | `string` | `"5"` | Rule level: `0` (loose) to `9` (strict) or `"max"` |
| `config` | `string` | `""` | Path to `phpstan.neon` relative to sources root |
| `paths` | `string[]` | `[]` | Specific subdirectories or files to analyse |

## Integration with the PHP module

PHPStan is a separate module and does not depend on the `php` module. For a full CI pipeline combining both:

```php
// In your Dagger pipeline
$analysis = dag()->phpstan()->analyse(sources: $sources, level: '8');
$container = dag()->php('8.3-cli')
    ->withSources($sources)
    ->withComposer()->install()->endComposer()
    ->phpunit()
;
```

## Dagger Engine Compatibility

Requires Dagger engine `v0.19.11` or newer.
