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

namespace GlpiPlugin\Opencitaseg;

use CommonDBTM;
use CommonITILTask;
use ITILFollowup;

/**
 * Internal link table mapping a new follow-up (source) to the follow-up it
 * quotes (target).
 *
 * This class is intentionally never exposed through a route, search option or
 * front/ajax controller. Rows are written only as a side effect of the
 * `item_add` hook, after GLPI core has already authorised the follow-up
 * creation and the plugin has verified `canViewItem()` on the quoted target
 * (see plugin_opencitaseg_item_add() in hook.php). For that reason it defines
 * no canView()/canCreate() rights on purpose: there is no stateless path from
 * which an unauthorised write or read could originate.
 */
class Cite extends CommonDBTM
{
    public const QUOTABLE_TYPES = [
        'ITILFollowup',
        'TicketTask',
        'ChangeTask',
        'ProblemTask',
    ];

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_opencitaseg_cites';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Follow-up quote', 'Follow-up quotes', $nb, 'opencitaseg');
    }

    /**
     * Carga un objeto citable y devuelve, normalizados, los datos que el resto
     * del plugin necesita.
     *
     * Existe porque los seguimientos y las tareas guardan el objeto ITIL padre
     * de forma distinta: ITILFollowup usa itemtype/items_id, mientras que las
     * tareas usan una columna propia (tickets_id, changes_id, problems_id).
     *
     * @return array{item: CommonDBTM, parent_itemtype: string, parent_id: int, is_private: bool}|null
     */
    public static function loadQuotable(string $itemtype, int $items_id): ?array
    {
        if (! in_array($itemtype, self::QUOTABLE_TYPES, true)) {
            return null;
        }

        $item = getItemForItemtype($itemtype);
        if (! $item instanceof CommonDBTM || ! $item->getFromDB($items_id)) {
            return null;
        }

        if ($item instanceof ITILFollowup) {
            $parentType = (string) $item->fields['itemtype'];
            $parentId   = (int) $item->fields['items_id'];
        } elseif ($item instanceof CommonITILTask) {
            // GLPI 10 no expone $items_id como propiedad estatica en las
            // tareas. La columna del padre se deriva del itemtype, que es lo
            // que hace el core (ver CommonITILTask), y funciona igual en 11.
            $parentType = $item->getItilObjectItemType();
            $parentId   = (int) $item->fields[getForeignKeyFieldForItemType($parentType)];
        } else {
            return null;
        }

        return [
            'item'            => $item,
            'parent_itemtype' => $parentType,
            'parent_id'       => $parentId,
            'is_private'      => (bool) ($item->fields['is_private'] ?? false),
        ];
    }
}
