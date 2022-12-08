--TEST--
Report a warning in case of missing PHP open tag
--FILE--
<?php

$checkRunner = require __DIR__ . '/init.php';

$checkRunner('tests/assets/return-type.php');

--EXPECTF--
. 1 / 1 (100%)


Time: %A
