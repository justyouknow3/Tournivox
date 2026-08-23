<?php

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tournivox_table_exists(mysqli $conn, string $table): bool
{
    $safeTable = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");

    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function bracket_type_label(string $type): string
{
    return match ($type) {
        'winners' => 'Winners Bracket',
        'losers' => 'Losers Bracket',
        'grand_finals' => 'Grand Finals',
        'round_robin' => 'Round Robin',
        default => ucwords(str_replace('_', ' ', $type)),
    };
}
