<?php

namespace Jcof;

require_once realpath('vendor/autoload.php');

$jsonString = file_get_contents(__DIR__.'/tests/data/madrid.json');

$data = json_decode($jsonString, true);

$jcofCompressed = Jcof::stringify($data);

// $jcofCompressedToArray = Jcof::parse($jcofCompressed);
