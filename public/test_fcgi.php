<?php
echo json_encode([
    'php_version' => PHP_VERSION,
    'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'N/A',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
]);
