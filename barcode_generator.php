<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require 'config.php';

// Authorization
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'inventory_manager') {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

if (isset($_GET['sku']) && !empty($_GET['sku'])) {
    $sku = trim($_GET['sku']);
    
    require 'vendor/autoload.php';
    
    try {
        $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
        $barcodeData = $generator->getBarcode($sku, $generator::TYPE_CODE_128, 2, 50);
        
        // Clear output buffers
        while (ob_get_level()) ob_end_clean();
        
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="barcode_'.preg_replace('/[^a-zA-Z0-9-]/', '-', $sku).'.png"');
        header('Content-Length: ' . strlen($barcodeData));
        echo $barcodeData;
        exit;
        
    } catch (Exception $e) {
        error_log("Barcode Error: " . $e->getMessage());
        header("HTTP/1.1 500 Internal Server Error");
        exit("Barcode generation failed");
    }
}

header("HTTP/1.1 400 Bad Request");
exit("Invalid request");