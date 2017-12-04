<?php
error_reporting(E_ERROR);
require_once ROOT_PATH . 'wxpay/example/phpqrcode/phpqrcode.php';
$url = urldecode($_GET["data"]);
QRcode::png($url);
