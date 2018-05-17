# NS API PHP Wrapper

Provides a PHP wrapper for the Nederlandse Spoorwegen API.

View the online documentation at: [https://www.ns.nl/reisinformatie/ns-api](https://www.ns.nl/reisinformatie/ns-api).

## Installation

`composer require silvanwakker/ns-api-php-wrapper`

## Usage

Creating an instance:

```php
$username = 'YOUR NS API USERNAME';
$password = 'YOUR NS API PASSWORD';

$client = new Client($username, $password);
```

### Examples

Get the prices:

```php
$from = 'Kampen';
$to = 'Zwolle';

$prices = $client->getPrices($from, $to);
```

## Contributing

This is my first ever attempt at creating a wrapper for an API. If you have any suggestions, questions or improvements please create an issue and/or pull request on this repository.
