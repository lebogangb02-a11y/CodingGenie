<?php
/**
 * Environment Variable Loader
 * Load .env file and populate $_ENV and $_SERVER
 */

// Load composer autoloader and environment variables
require_once __DIR__ . '/vendor/autoload.php';

// Load .env file from project root
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Optional: Define strict required variables
// $dotenv->required(['DB_HOST', 'DB_USER', 'DB_PASS'])->notEmpty();
