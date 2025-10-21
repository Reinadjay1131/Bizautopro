<?php
require 'vendor/autoload.php';

$generator = new Picqer\Barcode\BarcodeGeneratorPNG();
header('Content-Type: image/png');
echo $generator->getBarcode('TEST123', $generator::TYPE_CODE_128);