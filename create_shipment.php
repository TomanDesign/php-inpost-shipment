<?php

require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get API token and organization ID from environment variables
$apiToken = $_ENV['INPOST_API_TOKEN'] ?? null;
$organizationId = $_ENV['INPOST_ORGANIZATION_ID'] ?? null;
$apiBaseUrl = "https://sandbox-api-shipx-pl.easypack24.net/v1";
$logFile = 'log.txt'; // Log file for API responses

// Check if API token and organization ID are set
if(!$apiToken || !$organizationId){
    throw new Exception('API token or organization ID not found');
}

// Initialize Guzzle client
$client = new Client([
    'base_uri' => $apiBaseUrl,
    'headers' => [
        'Authorization' => 'Bearer ' . $apiToken,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ],
]);

/**
 * Function to log API responses to log.txt
 * @param string $message Message to log
 * @param mixed $data Data to log
 */
function logToFile(string $message, $data): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n" . print_r($data, true) . "\n\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

try {
    // Shipment data
    $shipmentData = [
        'receiver' => [
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan.kowalski@example.com',
            'phone' => '123456789',
            'address' => [
                'street' => 'ul. Testowa 1',
                'building_number' => '1',
                'city' => 'Warszawa',
                'post_code' => '00-001',
                'country_code' => 'PL',
            ],
        ],
        'sender' => [
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'email' => 'anna.nowak@example.com',
            'phone' => '987654321',
            'address' => [
                'street' => 'ul. Przykładowa 2',
                'building_number' => '2',
                'city' => 'Kraków',
                'post_code' => '30-002',
                'country_code' => 'PL',
            ],
        ],
        'parcels' => [
            [
                'dimensions' => [
                    'length' => 300, // mm
                    'width' => 200,  // mm
                    'height' => 100, // mm
                    'unit' => 'mm',
                ],
                'weight' => [
                    'amount' => 2.5, // kg
                    'unit' => 'kg',
                ],
                'is_non_standard' => false,
            ],
        ],
        'service' => 'inpost_courier_standard', // Shipment type: Courier standard
        'reference' => 'ORDER_12345', // Reference number
        'comments' => 'Please handle the package with care',
    ];

    // Create shipment
    $response = $client->post("$apiBaseUrl/organizations/$organizationId/shipments", [
        'json' => $shipmentData,
    ]);

    $shipmentResult = json_decode($response->getBody(), true);
    logToFile('Shipment created', $shipmentResult);
    $shipmentId = $shipmentResult['id'];

    // Order courier (Dispatch Order)
    $dispatchOrderData = [
        'shipments' => [$shipmentId],
        'address' => $shipmentData['sender']['address'], // Pickup address from sender
        'contact' => [
            'name' => $shipmentData['sender']['first_name'] . ' ' . $shipmentData['sender']['last_name'],
            'phone' => $shipmentData['sender']['phone'],
            'email' => $shipmentData['sender']['email'],
        ],
        'collection_date' => date('Y-m-d', strtotime('+1 day')), // Pickup date (next day)
    ];

    $dispatchResponse = $client->post("$apiBaseUrl/organizations/$organizationId/dispatch_orders", [
        'json' => $dispatchOrderData,
    ]);

    $dispatchResult = json_decode($dispatchResponse->getBody(), true);
    logToFile('Courier ordered', $dispatchResult);

    echo "Courier ordered for shipment ID: $shipmentId\n";

} catch (RequestException $e) {

    // Handle API errors
    $errorMessage = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
    logToFile('Error during API call', $errorMessage);
    echo "Error occurred: $errorMessage\n";

} catch (Exception $e) {

    // Handle other errors
    logToFile('General error', $e->getMessage());
    echo "Error occurred: " . $e->getMessage() . "\n";

}