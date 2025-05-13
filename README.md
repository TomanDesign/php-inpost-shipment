# InPost API Shipment Creator (Sandbox)

Simple PHP script to create a "Courier standard" shipment and order a courier using the InPost ShipX API in the sandbox environment. The script is compatible with PHP 8.x.

## Requirements
- PHP 8.x
- Composer
- Guzzle HTTP Client library
- PHP Dotenv library
- InPost API token for the sandbox environment and organization ID

## Installation
1. Clone the repository or download the project files:
   ```bash
   git clone https://github.com/TomanDesign/php-inpost-shipment
   cd inpost-api-connector
   ```
2. Install dependencies using Composer:
   ```bash
   composer install
   ```
3. Configure environment variables:
   - Copy the `.env.example` file to `.env`:
     ```bash
     cp .env.example .env
     ```
   - Open the `.env` file and replace the `INPOST_API_TOKEN` and `INPOST_ORGANIZATION_ID` values with your InPost sandbox API token and organization ID.

## Running the Script
1. Ensure PHP 8.x is installed, all dependencies are installed, and the `.env` file is properly configured.
2. Run the script from the command line:
   ```bash
   php create_shipment.php
   ```
3. The script will create a shipment, order a courier, and save API responses to `log.txt`.
4. Labels will be generated in `tmp` folder

## License
MIT License