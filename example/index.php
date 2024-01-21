<?php
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
