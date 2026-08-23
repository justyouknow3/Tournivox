<?php

require_once "database.php";

header("Content-Type: application/json");

$type = $_GET["type"] ?? "";
$value = trim($_GET["value"] ?? "");

if ($value === "") {

    echo json_encode([
        "taken" => false
    ]);

    exit;
}


if ($type === "username") {

    $check = $conn->prepare(
        "SELECT user_id
         FROM users
         WHERE username = ?
         LIMIT 1"
    );

} elseif ($type === "email") {

    $check = $conn->prepare(
        "SELECT user_id
         FROM users
         WHERE email = ?
         LIMIT 1"
    );

} else {

    echo json_encode([
        "taken" => false
    ]);

    exit;
}


$check->bind_param(
    "s",
    $value
);

$check->execute();

$check->store_result();


echo json_encode([
    "taken" => $check->num_rows > 0
]);


$check->close();
?>