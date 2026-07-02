<?php
header('Content-Type: application/json');
http_response_code(500);

echo json_encode([
    'success' => false,
    'error' => 'Internal Server Error',
    'message' => $message ?? 'Unknown error',
    'file' => $file ?? null,
    'line' => $line ?? null,
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);