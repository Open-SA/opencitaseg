<?php

namespace GlpiPlugin\Opencitaseg;

use CommonDBTM;
use CommonGLPI;
use CommonITILObject;
use Entity;
use Glpi\Application\View\TemplateRenderer;
use Session;

class Config extends CommonDBTM
{
    public static $rightname = 'entity';

    /** Memo por request: la resolucion recorre la cadena de entidades. */
    private static array $resolved = [];

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_opencitaseg_configs';
    }

    public static function getTypeName($nb = 0)
    {
        return __('OpenCitaSeg', 'opencitaseg');
    }

    public static function getIcon()
    {
        return 'ti ti-quote';
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Entity && Session::haveRight(self::$rightname, READ)) {
            return self::getTypeName();
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Entity) {
            self::showForEntity((int) $item->getID());
        }

        return true;
    }

    /**
     * Resuelve si las citas estan activas para una entidad, subiendo por la
     * cadena de padres mientras la entidad herede o no tenga fila propia.
     *
     * Fail-open: sin ninguna fila en toda la cadena, las citas quedan activas,
     * para no alterar el comportamiento de instalaciones previas.
     */
    public static function isActiveForEntity(int $entities_id): bool
    {
        if (isset(self::$resolved[$entities_id])) {
            return self::$resolved[$entities_id];
        }

        $result  = true;
        $current = $entities_id;

        // Guard de profundidad: un arbol de entidades corrupto no debe colgar
        // el request.
        for ($depth = 0; $depth < 50; $depth++) {
            $config = new self();

            if ($config->getFromDBByCrit(['entities_id' => $current])) {
                if ($current === 0 || ! (int) $config->fields['use_parent_config']) {
                    $result = (bool) $config->fields['is_active'];
                    break;
                }
            }

            if ($current === 0) {
                break;
            }

            $entity = new Entity();
            if (! $entity->getFromDB($current)) {
                break;
            }

            $current = (int) $entity->fields['entities_id'];
        }

        self::$resolved[$entities_id] = $result;

        return $result;
    }

    /**
     * Igual que isActiveForEntity() pero partiendo del objeto ITIL. Incluye
     * el chequeo de lectura para que el endpoint AJAX no revele la existencia
     * de tickets que el usuario no puede ver.
     */
    public static function isActiveForItem(string $itemtype, int $items_id): bool
    {
        if (! is_a($itemtype, CommonITILObject::class, true)) {
            return false;
        }

        $item = new $itemtype();
        if (! $item->getFromDB($items_id) || ! $item->can($items_id, READ)) {
            return false;
        }

        return self::isActiveForEntity((int) $item->fields['entities_id']);
    }

    public static function saveForEntity(int $entities_id, array $input): bool
    {
        $config = new self();

        $data = [
            'entities_id'       => $entities_id,
            // La raiz nunca hereda.
            'use_parent_config' => $entities_id === 0 ? 0 : (int) ($input['use_parent_config'] ?? 0),
            'is_active'         => (int) ($input['is_active'] ?? 0),
            'default_private'   => (int) ($input['default_private'] ?? 0),
        ];

        if ($config->getFromDBByCrit(['entities_id' => $entities_id])) {
            $data['id'] = $config->fields['id'];

            return (bool) $config->update($data);
        }

        return (bool) $config->add($data);
    }

    public static function showForEntity(int $entities_id): void
    {
        $config = new self();
        $exists = $config->getFromDBByCrit(['entities_id' => $entities_id]);

        TemplateRenderer::getInstance()->display('@opencitaseg/config.html.twig', [
            'entities_id'       => $entities_id,
            'is_root'           => $entities_id === 0,
            'use_parent_config' => $exists ? (int) $config->fields['use_parent_config'] : ($entities_id === 0 ? 0 : 1),
            'is_active'         => $exists ? (int) $config->fields['is_active'] : 1,
            'default_private'   => $exists ? (int) $config->fields['default_private'] : 0,
            'resolved_active'   => self::isActiveForEntity($entities_id),
            'can_update'        => Session::haveRight(self::$rightname, UPDATE),
            'save_url' => \Plugin::getWebDir('opencitaseg') . '/front/config.form.php',
        ]);
    }
}