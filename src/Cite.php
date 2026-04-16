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
 * @link      https://github.com/pluginsGLPI/opencitaseg
 *-------------------------------------------------------------------------
 */

namespace GlpiPlugin\Opencitaseg;

use CommonDBTM;

class Cite extends CommonDBTM
{
    public static function getTable($classname = null)
    {
        return 'glpi_plugin_opencitaseg_cites';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Cita de seguimiento', 'Citas de seguimientos', $nb, 'opencitaseg');
    }
}
