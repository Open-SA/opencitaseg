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
use ITILFollowup;

function plugin_opencitaseg_install()
{
    global $DB;

    $table = 'glpi_plugin_opencitaseg_cites';

    if (! $DB->tableExists($table)) {
        $query = "CREATE TABLE `$table` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `itilfollowups_id_source` bigint unsigned NOT NULL COMMENT 'ID de la respuesta nueva',
            `itemtype_target` varchar(100) NOT NULL DEFAULT 'ITILFollowup' COMMENT 'Clase del objeto citado',
            `items_id_target` bigint unsigned NOT NULL COMMENT 'ID del objeto citado',
            PRIMARY KEY (`id`),
            KEY `source` (`itilfollowups_id_source`),
            KEY `target` (`itemtype_target`, `items_id_target`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $DB->doQueryOrDie($query, $DB->error());
    } else {
        if (! $DB->fieldExists($table, 'itemtype_target')) {
            $DB->doQueryOrDie(
                "ALTER TABLE `$table`
                    ADD COLUMN `itemtype_target` varchar(100) NOT NULL DEFAULT 'ITILFollowup'
                        COMMENT 'Clase del objeto citado'
                    AFTER `itilfollowups_id_source`",
                $DB->error()
            );
        }

        if ($DB->fieldExists($table, 'itilfollowups_id_target')) {
            $DB->doQueryOrDie(
                "ALTER TABLE `$table`
                    CHANGE COLUMN `itilfollowups_id_target` `items_id_target`
                        bigint unsigned NOT NULL COMMENT 'ID del objeto citado'",
                $DB->error()
            );
        }
    }

    $configTable = 'glpi_plugin_opencitaseg_configs';

    if (! $DB->tableExists($configTable)) {
        $query = "CREATE TABLE `$configTable` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `entities_id` int unsigned NOT NULL DEFAULT 0,
            `use_parent_config` tinyint NOT NULL DEFAULT 1 COMMENT 'Hereda de la entidad padre',
            `is_active` tinyint NOT NULL DEFAULT 1 COMMENT 'Citas habilitadas en la entidad',
            `default_private` tinyint NOT NULL DEFAULT 0 COMMENT 'Valor inicial de is_private en la cita',
            `is_active_tasks` tinyint NOT NULL DEFAULT 1 COMMENT 'Citas habilitadas sobre tareas',
            PRIMARY KEY (`id`),
            UNIQUE KEY `entities_id` (`entities_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $DB->doQueryOrDie($query, $DB->error());
    }

    if (! $DB->fieldExists($configTable, 'is_active_tasks')) {
        $DB->doQueryOrDie(
            "ALTER TABLE `$configTable`
                ADD COLUMN `is_active_tasks` tinyint NOT NULL DEFAULT 1
                    COMMENT 'Citas habilitadas sobre tareas'
                AFTER `is_active`",
            $DB->error()
        );
    }

    $DB->doQueryOrDie(
        "INSERT IGNORE INTO `$configTable`
            (`entities_id`, `use_parent_config`, `is_active`, `default_private`)
         VALUES (0, 0, 1, 0)",
        $DB->error()
    );

    if (! CiteNotification::install()) {
        return false;
    }

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

    CiteNotification::uninstall();

    return true;
}


function plugin_opencitaseg_item_add(object $item)
{
    if (! $item instanceof ITILFollowup) {
        return;
    }

    if (! isset($_POST['_quoted_followup_id']) || empty($_POST['_quoted_followup_id'])) {
        return;
    }

    $targetId = (int) $_POST['_quoted_followup_id'];


    $targetType = (string) ($_POST['_quoted_itemtype'] ?? 'ITILFollowup');

    $target = Cite::loadQuotable($targetType, $targetId);
    if ($target === null) {
        return;
    }

    if (
        $target['parent_itemtype'] !== $item->fields['itemtype']
        || $target['parent_id'] !== (int) $item->fields['items_id']
    ) {
        return;
    }

    if (! $target['item']->canViewItem()) {
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

    if (
        ! \GlpiPlugin\Opencitaseg\Config::acceptsQuote(
            (string) $item->fields['itemtype'],
            (int) $item->fields['items_id'],
            $targetType
        )
    ) {
        return;
    }

    $cite = new Cite();
    $cite->add([
        'itilfollowups_id_source' => $item->fields['id'],
        'itemtype_target'         => $targetType,
        'items_id_target'         => $targetId,
    ]);

    if ($target['item'] instanceof ITILFollowup) {
        CiteNotification::raiseForCite($item, $target['item']);
    }
}

/**
 * Fuerza la privacidad de la cita cuando el seguimiento citado es privado.
 *
 * Corre en pre_item_add porque necesita pisar el input antes de que GLPI
 * escriba la fila. El checkbox que marca el JS es solo UX: este es el control
 * real, y es el que impide que alguien publique contenido privado mandando
 * el POST a mano.
 */
function plugin_opencitaseg_pre_item_add(object $item)
{
    if (empty($item->input['_quoted_followup_id'])) {
        return $item;
    }

    $target = Cite::loadQuotable(
        (string) ($item->input['_quoted_itemtype'] ?? 'ITILFollowup'),
        (int) $item->input['_quoted_followup_id']
    );

    if ($target === null) {
        return $item;
    }

    if (
        $target['parent_itemtype'] !== ($item->input['itemtype'] ?? null)
        || $target['parent_id'] !== (int) ($item->input['items_id'] ?? 0)
    ) {
        return $item;
    }

    if ($target['is_private']) {
        $item->input['is_private'] = 1;
    }

    return $item;
}
