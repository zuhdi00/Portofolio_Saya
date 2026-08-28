<?php
require 'config/_tulis_mdb.php';
$r = tulisPegawaiKeMDB('TEST DUMMY', '99999', 22, 'M', '2026-08-13');
var_dump($r);