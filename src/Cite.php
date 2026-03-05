<?php

namespace GlpiPlugin\Opencitaseg;

use CommonDBTM;

class Cite extends CommonDBTM
{
    // GLPI usará este método para saber qué tabla usar
    public static function getTable($classname = null)
    {
        return 'glpi_plugin_opencitaseg_cites';
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Cita de seguimiento', 'Citas de seguimientos', $nb, 'opencitaseg');
    }
}
