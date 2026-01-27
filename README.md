# ProtegeId PHP SDK
PHP SDK for integrating with the ProtegeId API (Age Verification).

[![codecov](https://codecov.io/gh/Protege-ID/php-client/branch/main/graph/badge.svg)](https://codecov.io/gh/Protege-ID/php-client)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg)](https://phpstan.org/)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Requirements

- PHP 8.0+

## Installation

```bash
composer require protege-id/php-client
```

## Basic usage

```php
<?php

use ProtegeId\ProtegeIdClient;
use ProtegeId\Exceptions\ApiException;
use ProtegeId\Exceptions\ConfigException;
use ProtegeId\Exceptions\ValidationException;

try {
    $client = new ProtegeIdClient('your-api-key');

    $session = $client->createSession(
        userRef: 'user-123',
        returnUrl: 'https://yoursite.com/path-to-return',
        metadata: ['additional' => 'infos']
    );

    $verification = $client->verifySession('user-123');
} catch (ValidationException | ConfigException | ApiException $e) {
    echo $e::class . ': ' . $e->getMessage() . PHP_EOL;
}
```

## Exceptions

- `ProtegeId\Exceptions\ConfigException` for invalid configuration.
- `ProtegeId\Exceptions\ValidationException` for invalid method input.
- `ProtegeId\Exceptions\ApiException` for API errors.

## Testing and code quality

Run the test suite:

```bash
./vendor/bin/phpunit
```

Run the PHPCS code style checks:

```bash
./vendor/bin/phpcs
```

Run the PHPStan static analysis:

```bash
vendor/bin/phpstan analyse
```

Generate code coverage (requires Xdebug or PCOV enabled):

```bash
composer test:coverage
```

The HTML report is generated in `build/coverage/`.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.

## Support

For questions, issues, or to request an API key, please contact:

**ProtegeId Sales Team**
Email: vendas@protegeid.com.br

For bug reports and feature requests, please use the [GitHub Issues](https://github.com/protege-id/php-client/issues) page.
