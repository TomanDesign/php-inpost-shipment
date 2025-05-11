<?php

require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get API token and organization ID from environment variables
$apiToken = $_ENV['INPOST_API_TOKEN'] ?? null;
$organizationId = $_ENV['INPOST_ORGANIZATION_ID'] ?? null;

// Check if API token and organization ID are set
if(!$apiToken || !$organizationId){
    throw new Exception('API token or organization ID not found');
}

// Create Guzzle client
$client = new GuzzleHttp\Client();



