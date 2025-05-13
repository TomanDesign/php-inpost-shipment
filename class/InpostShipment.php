<?php

declare(strict_types=1);

namespace App\Inpost\Class;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Dotenv\Dotenv;
use Exception;

/**
 * Class InpostShipment
 * Handles InPost shipment creation, label generation, and dispatch order processing.
 */
class InpostShipment
{
    private Client $client;
    private string $apiToken;
    private string $organizationId;
    private string $apiBaseUrl;
    private string $logFile;
    private string $labelFolder;
    private bool $debugMode;

    /**
     * InpostShipment constructor.
     * Initializes the environment, Guzzle client, and configuration.
     *
     * @param bool $debugMode Enable debug logging for detailed request/response info
     * @throws Exception If API token or organization ID is missing
     */
    public function __construct(bool $debugMode = false)
    {
        // Load environment variables
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();

        // Initialize configuration
        $this->apiToken = $_ENV['INPOST_API_TOKEN'] ?? '';
        $this->organizationId = $_ENV['INPOST_ORGANIZATION_ID'] ?? '';
        $this->apiBaseUrl = 'https://sandbox-api-shipx-pl.easypack24.net/v1'; // Update to production if needed
        $this->logFile = __DIR__ . '/../log.txt';
        $this->labelFolder = __DIR__ . '/../tmp';
        $this->debugMode = $debugMode;

        // Validate required environment variables
        if (empty($this->apiToken) || empty($this->organizationId)) {
            throw new Exception('API token or organization ID not found');
        }

        // Initialize Guzzle client
        $this->client = new Client([
            'base_uri' => '', // Removed base_uri to manually prepend apiBaseUrl
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Logs a message and data to the log file.
     *
     * @param string $message The message to log
     * @param mixed $data The data to log
     */
    private function logToFile(string $message, $data): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n" . print_r($data, true) . "\n\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Ensures the label folder exists, creating it if necessary.
     */
    private function ensureLabelFolderExists(): void
    {
        if (!is_dir($this->labelFolder)) {
            mkdir($this->labelFolder, 0755, true);
        }
    }

    /**
     * Prepares the shipment data structure.
     *
     * @return array<string, mixed> Shipment data
     */
    private function prepareShipmentData(): array
    {
        return [
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
                        'length' => '300',
                        'width' => '200',
                        'height' => '100',
                        'unit' => 'mm',
                    ],
                    'weight' => [
                        'amount' => '2.5',
                        'unit' => 'kg',
                    ],
                    'is_non_standard' => false,
                ],
            ],
            'insurance' => [
                'amount' => '25',
                'currency' => 'PLN',
            ],
            'custom_attributes' => [
                'sending_method' => 'dispatch_order',
                'target_point' => 'KRA012',
            ],
            'service' => 'inpost_courier_standard',
            'reference' => 'ORDER_12345',
            'comments' => 'Please handle the package with care',
        ];
    }

    /**
     * Creates a new shipment via the InPost API.
     *
     * @param array<string, mixed> $shipmentData Shipment data
     * @return array<string, mixed> Shipment result containing ID, status, and sender details
     * @throws RequestException If the API call fails
     */
    private function createShipment(array $shipmentData): array
    {
        $endpoint = $this->apiBaseUrl . "/organizations/{$this->organizationId}/shipments";
        if ($this->debugMode) {
            $this->logToFile('Shipment Request', [
                'endpoint' => $endpoint,
                'data' => $shipmentData,
            ]);
        }

        try {
            $response = $this->client->post($endpoint, [
                'json' => $shipmentData,
                'verify' => false, // TODO: Enable in production
            ]);
            $shipmentResult = json_decode($response->getBody()->__toString(), true);
            $this->logToFile('Shipment created', $shipmentResult);
            return $shipmentResult;
        } catch (RequestException $e) {
            $errorDetails = $e->hasResponse() ? json_decode($e->getResponse()->getBody()->__toString(), true) : ['message' => $e->getMessage()];
            $this->logToFile('Shipment creation failed', [
                'endpoint' => $endpoint,
                'error' => $errorDetails,
            ]);
            throw $e;
        }
    }

    /**
     * Waits for the shipment status to become confirmed, displaying a spinning loader.
     *
     * @param string $shipmentId Shipment ID
     * @return array<string, mixed> Final shipment result
     * @throws RequestException If the API call fails
     */
    private function waitForShipmentConfirmation(string $shipmentId): array
    {
        $spinner = ['|', '/', '-', '\\'];
        $spinnerIndex = 0;
        $shipmentResult = [];

        while (!isset($shipmentResult['status']) || $shipmentResult['status'] !== 'confirmed') {
            sleep(1);
            $response = $this->client->get($this->apiBaseUrl . "/shipments/$shipmentId", [
                'verify' => false, // TODO: Enable in production
            ]);
            $shipmentResult = json_decode($response->getBody()->__toString(), true);

            // Display spinning loader
            echo "\rWaiting for shipment confirmation... " . $spinner[$spinnerIndex];
            $spinnerIndex = ($spinnerIndex + 1) % 4;

            // Log status for debugging
            $this->logToFile('Shipment status', $shipmentResult['status']);
        }

        // Clear loader and display final status
        echo "\r" . str_repeat(' ', 50) . "\r"; // Clear the line
        echo "Shipment confirmed\n";
        $this->logToFile('Shipment details', $shipmentResult);

        return $shipmentResult;
    }

    /**
     * Generates and saves the shipment label.
     *
     * @param string $shipmentId Shipment ID
     * @return string Path to the saved label file
     * @throws RequestException If the API call fails
     */
    private function generateShipmentLabel(string $shipmentId): string
    {
        $labelResponse = $this->client->get($this->apiBaseUrl . "/shipments/$shipmentId/label", [
            'json' => [
                'format' => 'Pdf',
                'type' => 'A6',
            ],
            'verify' => false, // TODO: Enable in production
        ]);

        $this->ensureLabelFolderExists();
        $labelPath = "{$this->labelFolder}/{$shipmentId}_inpost_label.pdf";
        file_put_contents($labelPath, $labelResponse->getBody()->__toString());
        echo "Label Generated: {$shipmentId}_inpost_label.pdf\n";
        $this->logToFile('Label Generated', $labelPath);
        return $labelPath;
    }

    /**
     * Creates a dispatch order for the shipment.
     *
     * @param array<string, mixed> $shipmentData Shipment data
     * @param string $shipmentId Shipment ID
     * @param string $shipmentStatus Shipment status
 Definition: The status of a shipment.
 Example: created, confirmed
     * @param string $dispatchPointID Dispatch point ID
     * @return string Dispatch order ID
     * @throws RequestException If the API call fails
     */
    private function createDispatchOrder(
        array $shipmentData,
        string $shipmentId,
        string $shipmentStatus,
        string $dispatchPointID
    ): string {
        $dispatchOrderData = [
            'status' => $shipmentStatus,
            'shipments' => [$shipmentId],
            'dispatch_point_id' => [$dispatchPointID],
            'address' => $shipmentData['receiver']['address'],
            'contact' => [
                'name' => $shipmentData['sender']['first_name'] . ' ' . $shipmentData['sender']['last_name'],
                'phone' => $shipmentData['sender']['phone'],
                'email' => $shipmentData['sender']['email'],
            ],
            'collection_date' => date('Y-m-d', strtotime('+1 day')),
        ];

        $endpoint = $this->apiBaseUrl . "/organizations/{$this->organizationId}/dispatch_orders";
        if ($this->debugMode) {
            $this->logToFile('Dispatch Order Request', [
                'endpoint' => $endpoint,
                'data' => $dispatchOrderData,
            ]);
        }

        try {
            $dispatchResponse = $this->client->post($endpoint, [
                'json' => $dispatchOrderData,
                'verify' => false, // TODO: Enable in production
            ]);
            $dispatchResult = json_decode($dispatchResponse->getBody()->__toString(), true);
            $this->logToFile('Courier ordered', $dispatchResult);
            return (string) $dispatchResult['id']; // Cast to string
        } catch (RequestException $e) {
            $errorDetails = $e->hasResponse() ? json_decode($e->getResponse()->getBody()->__toString(), true) : ['message' => $e->getMessage()];
            $this->logToFile('Dispatch order creation failed', [
                'endpoint' => $endpoint,
                'error' => $errorDetails,
            ]);
            throw $e;
        }
    }

    /**
     * Generates and saves the dispatch order printout.
     *
     * @param string $dispatchId Dispatch order ID
     * @param string $shipmentId Shipment ID
     * @return string Path to the saved printout file
     * @throws RequestException If the API call fails
     */
    private function generateDispatchPrintout(string $dispatchId, string $shipmentId): string
    {
        $endpoint = $this->apiBaseUrl . "/dispatch_orders/$dispatchId/printout";
        if ($this->debugMode) {
            $this->logToFile('Dispatch Printout Request', [
                'endpoint' => $endpoint,
                'params' => ['format' => 'Pdf'],
            ]);
        }

        try {
            $printoutResponse = $this->client->get($endpoint, [
                'json' => [
                    'format' => 'Pdf',
                ],
                'verify' => false, // TODO: Enable in production
            ]);
            $this->ensureLabelFolderExists();
            $printoutPath = "{$this->labelFolder}/{$shipmentId}_inpost_printout.pdf";
            file_put_contents($printoutPath, $printoutResponse->getBody()->__toString());
            echo "Printout Generated: {$shipmentId}_inpost_printout.pdf\n";
            $this->logToFile('Printout Generated', $printoutPath);
            return $printoutPath;
        } catch (RequestException $e) {
            $errorDetails = $e->hasResponse() ? json_decode($e->getResponse()->getBody()->__toString(), true) : ['message' => $e->getMessage()];
            $this->logToFile('Dispatch printout generation failed', [
                'endpoint' => $endpoint,
                'error' => $errorDetails,
            ]);
            throw $e;
        }
    }

    /**
     * Processes the entire shipment workflow.
     *
     * @return bool True on success, false on failure
     */
    public function processShipment(): bool
    {
        try {
            // Prepare shipment data
            $shipmentData = $this->prepareShipmentData();
            $this->logToFile('Receiver address', $shipmentData['receiver']['address']);

            // Create shipment
            $shipmentResult = $this->createShipment($shipmentData);
            $shipmentId = (string) $shipmentResult['id']; // Cast to string
            $dispatchPointID = (string) $shipmentResult['sender']['id']; // Cast to string

            // Wait for confirmation
            $shipmentResult = $this->waitForShipmentConfirmation($shipmentId);
            $shipmentStatus = $shipmentResult['status']; // Update status to 'confirmed'

            // Generate label
            $this->generateShipmentLabel($shipmentId);

            // Create dispatch order
            $dispatchId = $this->createDispatchOrder($shipmentData, $shipmentId, $shipmentStatus, $dispatchPointID);

            // Generate dispatch printout
            $this->generateDispatchPrintout($dispatchId, $shipmentId);

            echo "Courier ordered for shipment ID: $shipmentId\n";
            return true;
        } catch (RequestException $e) {
            $errorMessage = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            $this->logToFile('Error during API call', $errorMessage);
            echo "Error occurred: $errorMessage\n";
            return false;
        } catch (Exception $e) {
            $this->logToFile('General error', $e->getMessage());
            echo "Error occurred: {$e->getMessage()}\n";
            return false;
        }
    }
}