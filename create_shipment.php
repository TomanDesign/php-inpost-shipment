<?php

require_once 'vendor/autoload.php';
use App\Inpost\Class\InpostShipment;

try {
    $inpost = new InpostShipment();
    $inpost->processShipment();
} catch (Exception $e) {
    echo "Initialization error: {$e->getMessage()}\n";
}