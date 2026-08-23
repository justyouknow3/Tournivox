<?php

$host = 'localhost';
$dbUser = 'root';
$dbPassword = '';
$dbName = 'tournivox';

$conn = new mysqli($host, $dbUser, $dbPassword, $dbName);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
