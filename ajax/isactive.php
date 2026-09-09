<?php

use GlpiPlugin\Opencitaseg\Config;

Session::checkLoginUser();

header('Content-Type: application/json');

$itemtype = (string) ($_GET['itemtype'] ?? '');
$items_id = (int) ($_GET['items_id'] ?? 0);

// Whitelist explicita en lugar de confiar el nombre de clase al autoloader.
if ($items_id <= 0 || ! in_array($itemtype, ['Ticket', 'Change', 'Problem'], true)) {
    http_response_code(400);
    echo json_encode(['active' => false]);
    exit;
}

$resolved = Config::resolveForItem($itemtype, $items_id);

echo json_encode([
    'active'          => $resolved !== null
                         && $resolved['is_active']
                         && $resolved['accepts_quotes'],
    'default_private' => $resolved !== null && $resolved['default_private'],
]);
