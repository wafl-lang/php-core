# WAFL PHP Core

PHP implementation of the WAFL (Wider Attribute Formatting Language) configuration pipeline. It loads `.wafl` files,
resolves imports, evaluates expressions/tags, and validates the result against
schemas declared in `@schema` blocks.

## Package layout

| File | Role |
| --- | --- |
| `src/ConfigLoader.php` | High-level orchestrator that chains all processing stages |
| `src/Loader.php` | Reads files, resolves `@import`, extracts `@schema` / `@eval` blocks |
| `src/Parser.php` | Indentation-aware parser that builds the intermediate AST |
| `src/Resolver.php` | Resolves `__expr` nodes, conditional lists, and registered tags |
| `src/DocumentEvaluator.php` | Runs the final evaluation pass (with `$` symbols support) |
| `src/SchemaValidator.php` | Validates the resolved config against schema definitions |
| `src/TagRegistry.php` | Built-in tag registry (`!rgb`, `!file`) with extension points |
| `src/TypeMetadataExtractor.php` | Captures `key<Type>` annotations before resolution |
| `src/Utils.php` | Shared helpers (`deepMerge`, associative detection, etc.) |

## Installation

```bash
composer require wafl/php-core
```

## Usage

```php
use Wafl\Core\ConfigLoader;

$loader = new ConfigLoader();
$result = $loader->load(__DIR__ . '/app.wafl', [
    'env' => $_ENV,
]);

$config = $result['config'];
$meta = $result['meta'];
```

### WAFL file example

```
@schema:
  App:
    name: string
    version: string
    port: int
    debug?: bool
    theme:
      primary: string
      secondary: string
    features: list<string>

app<App>:
  name: "WAFL Demo"
  version: "1.0.0"

  port = $ENV.PORT || 3000
  debug = $ENV.NODE_ENV === "dev"

  theme:
    primary = !rgb(255, 200, 80)
    secondary = "#111"

  features:
    - login
    - analytics
    - if $ENV.NODE_ENV === "dev": monitoring
```

Loading this file with `ConfigLoader` resolves tags/expressions and validates against `schema.wafl`:

```php
[
    'app' => [
        'name' => 'WAFL Demo',
        'version' => '1.0.0',
        'port' => 3000,
        'debug' => false,
        'theme' => [
            'primary' => '#ffc850',
            'secondary' => '#111',
        ],
        'features' => ['login', 'analytics'],
    ],
]
```

Setting `NODE_ENV=dev PORT=8080` would flip `debug` to `true`, set `port` to `8080`, and include the `monitoring` feature. `TagRegistry` includes built-ins like `!rgb` and can be extended; `@schema` is optional but enforced when present.

## Running tests

```bash
composer install
composer test
```
