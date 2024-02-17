--TEST--
Report a warning in case of missing PHP open tag
--FILE--
<?php

$checkRunner = require __DIR__ . '/init.php';

$checkRunner('tests/assets/return-type-off.php');

--EXPECTF--
E 1 / 1 (100%)



FILE: %s/tests/assets/return-type-off.php
%s----
FOUND 1 ERROR AFFECTING 1 LINE
%s----
 4 | ERROR | [x] Psalm tag not formatted.
%s----
PHPCBF CAN FIX THE 1 MARKED SNIFF VIOLATIONS AUTOMATICALLY
%s----

Time: %A
