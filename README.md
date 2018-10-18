# Почтовые индексы России

Получение/обновление файла `dbf` со всеми почтовыми индексами [отсюда](http://vinfo.russianpost.ru/database/ops.html) и преобразование в `csv` для дальнейшего использования.


```php
<?php

use GuzzleHttp\Exception\GuzzleException;
use PostIndex\PostIndex;
use PostIndex\PostIndexException;

require_once __DIR__ . '/vendor/autoload.php';

try {
    $pi = new PostIndex(__DIR__, new \DateTime('2018-01-01 00:00:00'));
    $pathCSV = $pi->refresh()->filepathCSV();
    print_r($pathCSV);
} catch (PostIndexException $e) {
    die($e->getMessage());
} catch (GuzzleException $e) {
    die($e->getMessage());
}
```