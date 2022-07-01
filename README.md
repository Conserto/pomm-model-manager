# Pomm ModelManager

[![Latest Stable Version](https://poser.pugx.org/conserto/pomm-model-manager/v/stable)](https://packagist.org/packages/conserto/pomm-model-manager)
![CI Status](https://github.com/conserto/pomm-model-manager/actions/workflows/ci.yml/badge.svg)
[![Monthly Downloads](https://poser.pugx.org/conserto/pomm-model-manager/d/monthly.png)](https://packagist.org/packages/conserto/pomm-model-manager) 
[![License](https://poser.pugx.org/conserto/pomm-model-manager/license.svg)](https://packagist.org/packages/conserto/pomm-model-manager)


This as fork of ModelManager, a [Pomm project](http://www.pomm-project.org) package. It makes developers able to manage entities upon the database through model classes. **It is not an ORM**, it grants developers with the ability to perform native queries using all of Postgres’SQL and use almost all its types. This makes model layer to meet with performances while staying lean.

This package will provide:

 * Model classes with all common built-in queries (CRUD but also, `count` and `exists`).
 * Flexible entities
 * Embedded entities converter
 * Model Layer to group model computations in transactions.

The model layer also proposes methods to leverage Postgres nice transaction settings (constraint deferring, isolation levels, read / write access modes etc.).

## Installation

Pomm components are available on [packagist](https://packagist.org/packages/conserto/) using [composer](https://packagist.org/). To install and use Pomm's model manager, add a require line to `"conserto/pomm-model-manager"` in your `composer.json` file. It is advised to install the [CLI package](https://github.com/conserto/pomm-cli) as well.

In order to load the model manager's poolers at startup, it is possible to use the provided `SessionBuilder` in Pomm's configuration:

```php
$pomm = new Pomm([
    'project_name' => [
        'dsn' => …,
        'class:session_builder' => '\PommProject\ModelManager\SessionBuilder',
    ],
    …
]);
```

It is better to provide dedicated session builders with your project.

## Documentation

The model manager’s documentation is available [either online](https://github.com/conserto/pomm-model-manager/blob/master/documentation/model_manager.rst) or directly in the `documentation` folder.

## Tests

This package uses Atoum as unit test framework. The tests are located in `sources/tests`. This package also provides a `ModelSessionAtoum` class so the test classes can directly get sessions with the `model` and `model layer` poolers loaded.
