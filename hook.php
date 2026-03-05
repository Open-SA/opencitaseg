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
 * @copyright Copyright (C) 2013-2023 by opencitaseg plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/opencitaseg
 *-------------------------------------------------------------------------
 */

function plugin_opencitaseg_install()
{
    global $DB;

    $table = 'glpi_plugin_opencitaseg_cites';

    // Si la tabla no existe, la creamos
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

    return true;
}

function plugin_opencitaseg_uninstall()
{
    global $DB;

    $table = 'glpi_plugin_opencitaseg_cites';
    if ($DB->tableExists($table)) {
        $DB->doQueryOrDie("DROP TABLE `$table`", $DB->error());
    }

    return true;
}

// Esta función se ejecuta justo después de que GLPI guarda un nuevo seguimiento
function plugin_opencitaseg_item_add($item)
{
    // Verificamos si la variable existe en el POST enviado por nuestro futuro JavaScript
    if (isset($_POST['_quoted_followup_id']) && ! empty($_POST['_quoted_followup_id'])) {
        // Instanciamos nuestra clase y guardamos la relación
        $cite = new \GlpiPlugin\Opencitaseg\Cite();
        $cite->add([
            'itilfollowups_id_source' => $item->fields['id'],
            'itilfollowups_id_target' => (int) $_POST['_quoted_followup_id'],
        ]);
    }
}
