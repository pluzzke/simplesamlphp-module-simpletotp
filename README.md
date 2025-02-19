# SimpleTOTP Module

## Installation

Once you have installed SimpleSAMLphp, installing this module is very simple. Just execute the following command SimpleSAMLphp installation:

```bash
composer require pluzzke/simplesamlphp-module-simpletotp
```

Next thing you need to do is to enable the module: in `config.php`,
search for the `module.enable` key and set `simpletotp` to true:

```php
    'module.enable' => [
         'simpletotp' => true,
         …
    ],
```