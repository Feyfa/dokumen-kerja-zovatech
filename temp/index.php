<?php

$array = [];
error_reporting(E_ALL);
ini_set('display_errors', 1);
$value = $array['a']['b']['c'] ?? "TESTING";
var_dump($value);
