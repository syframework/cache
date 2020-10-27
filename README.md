# sy/cache

Simple caching library.

The cache is saved on the filesystem using var_export when storing cache and include when loading cache.

Should primarily be used for caching arrays and objects. There is no performance benefit over APCu for storing strings.

Keys support the character / (not fully PSR-16 compliant) and do not support the character *

Empty values are not stored in the cache.

No TTL support yet (to do).

## Installation

Install the latest version with

```bash
$ composer require sy/cache
```

## Basic Usage

```php
<?php

use Sy\Cache\SimpleCache;

$cache = new SimpleCache(__DIR__);

$cache->set('array', ['one', 'two', 'three']);

$array = $cache->get('cache');
```