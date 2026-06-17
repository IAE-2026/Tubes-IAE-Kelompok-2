<?php
try {
    echo "Connecting to MySQL...\n";
    $pdo = new PDO("mysql:host=mysql;dbname=laravel;port=3306", "sail", "password", [
        PDO::ATTR_TIMEOUT => 2,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "Connected successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
