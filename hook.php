<?php

/**
 * -------------------------------------------------------------------------
 * opencitaseg plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of opencitaseg.
 *
 * opencitaseg is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * any later version.
 *
 * opencitaseg is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with opencitaseg. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2013-2026 by opencitaseg plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/Open-SA/opencitaseg
 * -------------------------------------------------------------------------
 */

use GlpiPlugin\Opencitaseg\Cite;
use GlpiPlugin\Opencitaseg\CiteNotification;

function plugin_opencitaseg_install()
{
    global $DB;

    $table = 'glpi_plugin_opencitaseg_cites';

    if (! $DB->tableExists($table)) {
        $query = "CREATE TABLE `$table` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `itilfollowups_id_source` bigint unsigned NOT NULL COMMENT 'ID de la respuesta nueva',
            `itilfollowups_id_target` bigint unsigned NOT NULL COMMENT 'ID del seguimiento citado',
            PRIMARY KEY (`id`),
            KEY `source` (`itilfollowups_id_source`),
            KEY `target` (`itilfollowups_id_target`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $DB->doQueryOrDie($query, $DB->error());
    }

    $configTable = 'glpi_plugin_opencitaseg_configs';

    if (! $DB->tableExists($configTable)) {
        $query = "CREATE TABLE `$configTable` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `entities_id` int unsigned NOT NULL DEFAULT 0,
            `use_parent_config` tinyint NOT NULL DEFAULT 1 COMMENT 'Hereda de la entidad padre',
            `is_active` tinyint NOT NULL DEFAULT 1 COMMENT 'Citas habilitadas en la entidad',
            `default_private` tinyint NOT NULL DEFAULT 0 COMMENT 'Valor inicial de is_private en la cita',
            PRIMARY KEY (`id`),
            UNIQUE KEY `entities_id` (`entities_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $DB->doQueryOrDie($query, $DB->error());
    }

    // La entidad raiz no puede heredar: siempre define su propio valor.
    // Se siembra activa para no cambiar el comportamiento de instalaciones
    // que vienen de una version anterior sin configuracion.
    $DB->doQueryOrDie(
        "INSERT IGNORE INTO `$configTable`
            (`entities_id`, `use_parent_config`, `is_active`, `default_private`)
         VALUES (0, 0, 1, 0)",
        $DB->error()
    );

    return true;
}

function plugin_opencitaseg_uninstall()
{
    global $DB;

    foreach (['glpi_plugin_opencitaseg_cites', 'glpi_plugin_opencitaseg_configs'] as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQueryOrDie("DROP TABLE `$table`", $DB->error());
        }
    }

    return true;
}


function plugin_opencitaseg_item_add($item)
{
    if (! $item instanceof ITILFollowup) {
        return;
    }

    if (! isset($_POST['_quoted_followup_id']) || empty($_POST['_quoted_followup_id'])) {
        return;
    }

    $targetId = (int) $_POST['_quoted_followup_id'];

    $targetFollowup = new ITILFollowup();
    if (! $targetFollowup->getFromDB($targetId)) {
        return;
    }

    if (
        $targetFollowup->fields['itemtype'] !== $item->fields['itemtype']
        || (int) $targetFollowup->fields['items_id'] !== (int) $item->fields['items_id']
    ) {
        return;
    }

    if (! $targetFollowup->canViewItem()) {
        return;
    }

    if (
        ! \GlpiPlugin\Opencitaseg\Config::isActiveForItem(
            (string) $item->fields['itemtype'],
            (int) $item->fields['items_id']
        )
    ) {
        return;
    }

    $cite = new Cite();
    $cite->add([
        'itilfollowups_id_source' => $item->fields['id'],
        'itilfollowups_id_target' => $targetId,
    ]);

    // La notificación se levanta después de persistir la relación y después de
    // que canViewItem() confirmó que quien cita tenía derecho a ver el
    // seguimiento citado. CiteNotification aplica sus propios filtros
    // (seguimiento privado, autocita, autor inexistente).
    CiteNotification::raiseForCite($item, $targetFollowup);
}
