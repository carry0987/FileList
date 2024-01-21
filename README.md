# FileList
[![Latest Stable Version](https://img.shields.io/packagist/v/carry0987/filelist.svg?style=flat-square)](https://packagist.org/packages/carry0987/filelist)  
FileList is a PHP library for getting files and directories recursively

## Installation
```bash
composer require carry0987/filelist
```

## Usage
```php
require dirname(__DIR__).'/vendor/autoload.php';

use carry0987\FileList\FileList as FileList;

$filelist = new FileList;

$result = $filelist->sortFile(false)
        ->setPath(dirname(__DIR__).'/test')
        ->setAllowedFileType(FileList::NO_FILTER)
        ->startRDI()
        ->getResult();

echo '<pre>';
var_export($result);
echo '</pre>';
```
