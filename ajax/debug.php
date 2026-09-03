<?php

use GlpiPlugin\Opencitaseg\Config;

Session::checkLoginUser();

header('Content-Type: text/plain');

$config = new Config();
$found  = $config->getFromDBByCrit(['entities_id' => 0]);
global $DB;
$raw = $DB->request(['FROM' => Config::getTable(), 'WHERE' => ['entities_id' => 0]]);
foreach ($raw as $row) {
    echo "SQL crudo: " . var_export($row, true) . "\n";
}
echo "getTable(): " . Config::getTable() . "\n";
echo "getFromDBByCrit(0): " . var_export($found, true) . "\n";
echo "fields: " . var_export($config->fields, true) . "\n";
echo "isActiveForEntity(0): " . var_export(Config::isActiveForEntity(0), true) . "\n";
echo "isActiveForItem(Ticket,11): " . var_export(Config::isActiveForItem('Ticket', 11), true) . "\n";