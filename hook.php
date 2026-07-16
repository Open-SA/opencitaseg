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

/**
 * Emit the translatable UI strings used by citas.js as a JS global.
 *
 * Keeping the catalogue in PHP lets the strings go through GLPI's translation
 * pipeline (`__()` / the plugin `.mo` files) and honour the connected user's
 * locale, instead of hardcoding one language in the JS asset.
 */
function plugin_opencitaseg_post_init(): void
{
    if (isCommandLine()) {
        return;
    }

    $strings = [
        'quote'          => __('Quote', 'opencitaseg'),
        'quote_followup' => __('Quote this followup', 'opencitaseg'),
        // %s is replaced client-side with the quoted follow-up author's name.
        'quoting'        => __('Quoting %s', 'opencitaseg'),
        'user'           => __('User', 'opencitaseg'),
    ];

    echo Html::scriptBlock(
        'window.OPENCITASEG_I18N = '
        . json_encode($strings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . ';'
    );
}

function plugin_opencitaseg_item_add($item)
{
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

    $cite = new \GlpiPlugin\Opencitaseg\Cite();
    $cite->add([
        'itilfollowups_id_source' => $item->fields['id'],
        'itilfollowups_id_target' => $targetId,
    ]);
}
