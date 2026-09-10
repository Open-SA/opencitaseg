<?php

use GlpiPlugin\Opencitaseg\Config;

include('../../../inc/includes.php');

Session::checkRight('entity', UPDATE);

if (isset($_POST['update'])) {
    $entities_id = (int) ($_POST['entities_id'] ?? -1);

    if ($entities_id >= 0 && Session::haveAccessToEntity($entities_id)) {
        Config::saveForEntity($entities_id, $_POST);
        Session::addMessageAfterRedirect(__('Item successfully updated'), false, INFO);
    }
}

Html::back();
