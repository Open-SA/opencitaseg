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
     * @return array{is_active: bool, default_private: bool}
     */
    public static function resolveForEntity(int $entities_id): array
    {
        if (isset(self::$resolved[$entities_id])) {
            return self::$resolved[$entities_id];
        }

        // Fail-open: sin ninguna fila en la cadena, las citas quedan activas
        // y publicas, que es el comportamiento previo a este plugin.
        $result  = ['is_active' => true, 'default_private' => false];
        $current = $entities_id;

        for ($depth = 0; $depth < 50; $depth++) {
            $config = new self();

            if ($config->getFromDBByCrit(['entities_id' => $current])) {
                if ($current === 0 || ! (int) $config->fields['use_parent_config']) {
                    $result = [
                        'is_active'       => (bool) $config->fields['is_active'],
                        'default_private' => (bool) $config->fields['default_private'],
                    ];
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
     * @return array{is_active: bool, default_private: bool}|null null si el
     *         usuario no puede leer el objeto ITIL.
     */
    public static function resolveForItem(string $itemtype, int $items_id): ?array
    {
        if (! is_a($itemtype, CommonITILObject::class, true)) {
            return null;
        }

        $item = new $itemtype();
        if (! $item->getFromDB($items_id) || ! $item->can($items_id, READ)) {
            return null;
        }

        return self::resolveForEntity((int) $item->fields['entities_id']);
    }

    public static function isActiveForEntity(int $entities_id): bool
    {
        return self::resolveForEntity($entities_id)['is_active'];
    }

    public static function isActiveForItem(string $itemtype, int $items_id): bool
    {
        $resolved = self::resolveForItem($itemtype, $items_id);

        return $resolved !== null && $resolved['is_active'];
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
