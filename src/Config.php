<?php

namespace GlpiPlugin\Opencitaseg;

use CommonDBTM;
use CommonGLPI;
use CommonITILObject;
use Entity;
use Glpi\Application\View\TemplateRenderer;
use Session;
use CommonITILTask;

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

        $result  = ['is_active' => true, 'is_active_tasks' => true, 'default_private' => false];
        $current = $entities_id;

        for ($depth = 0; $depth < 50; $depth++) {
            $config = new self();

            if ($config->getFromDBByCrit(['entities_id' => $current])) {
                if ($current === 0 || ! (int) $config->fields['use_parent_config']) {
                    $result = [
                        'is_active'       => (bool) $config->fields['is_active'],
                        'is_active_tasks' => (bool) $config->fields['is_active_tasks'],
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
     * @return array{is_active: bool, default_private: bool, accepts_quotes: bool}|null
     *         null si el usuario no puede leer el objeto ITIL.
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

        // Un ticket resuelto o cerrado no debe ofrecer el boton de citar.
        // GLPI permite el seguimiento nativo igual (y eso reabre el ticket),
        // pero no queremos que la cita sea el atajo para ese camino.
        $bloqueados = array_merge(
            $item::getClosedStatusArray(),
            $item::getSolvedStatusArray()
        );

        $config = self::resolveForEntity((int) $item->fields['entities_id']);

        return [
            'is_active'       => $config['is_active'],
            'default_private' => $config['default_private'],
            'accepts_quotes'  => ! in_array((int) $item->fields['status'], $bloqueados, true),
            'is_active_tasks' => $config['is_active_tasks'],
        ];
    }

    public static function isActiveForEntity(int $entities_id): bool
    {
        return self::resolveForEntity($entities_id)['is_active'];
    }

    public static function isActiveForItem(string $itemtype, int $items_id): bool
    {
        $resolved = self::resolveForItem($itemtype, $items_id);

        return $resolved !== null
            && $resolved['is_active']
            && $resolved['accepts_quotes'];
    }

    public static function saveForEntity(int $entities_id, array $input): bool
    {
        $config = new self();
        $existe = $config->getFromDBByCrit(['entities_id' => $entities_id]);

        // La raiz nunca hereda.
        $hereda = $entities_id !== 0 && (int) ($input['use_parent_config'] ?? 0) === 1;

        $data = [
            'entities_id'       => $entities_id,
            'use_parent_config' => $hereda ? 1 : 0,
        ];

        if ($hereda) {
            // Cuando se hereda, los campos propios no se tocan: quedan como
            // estaban para que desmarcar la herencia devuelva la configuracion
            // anterior y no lo que el navegador haya mandado en un select
            // deshabilitado.
            $data['is_active']       = $existe ? (int) $config->fields['is_active'] : 1;
            $data['is_active_tasks'] = $existe ? (int) $config->fields['is_active_tasks'] : 1;
            $data['default_private'] = $existe ? (int) $config->fields['default_private'] : 0;
        } else {
            $data['is_active']       = (int) ($input['is_active'] ?? 0);
            $data['default_private'] = (int) ($input['default_private'] ?? 0);
            $data['is_active_tasks'] = (int) ($input['is_active_tasks'] ?? 0);
        }

        if ($existe) {
            $data['id'] = $config->fields['id'];

            return (bool) $config->update($data);
        }

        return (bool) $config->add($data);
    }

    public static function showForEntity(int $entities_id): void
    {
        $config = new self();
        $exists = $config->getFromDBByCrit(['entities_id' => $entities_id]);

        $hereda = $entities_id !== 0
            && ($exists ? (int) $config->fields['use_parent_config'] === 1 : true);

        $resuelto = self::resolveForEntity($entities_id);

        // Cuando se hereda, los selects muestran el valor resuelto y no el
        // propio de la entidad: de otro modo la pantalla contradice al cartel.
        // El valor propio se conserva igual en la base, saveForEntity() no lo
        // pisa mientras la herencia siga activa.
        TemplateRenderer::getInstance()->display('@opencitaseg/config.html.twig', [
            'entities_id'       => $entities_id,
            'is_root'           => $entities_id === 0,
            'use_parent_config' => $hereda ? 1 : 0,
            'is_active'         => $hereda
                ? (int) $resuelto['is_active']
                : ($exists ? (int) $config->fields['is_active'] : 1),
            'is_active_tasks'   => $hereda
                ? (int) $resuelto['is_active_tasks']
                : ($exists ? (int) $config->fields['is_active_tasks'] : 1),
            'default_private'   => $hereda
                ? (int) $resuelto['default_private']
                : ($exists ? (int) $config->fields['default_private'] : 0),
            'resolved'          => $resuelto,
            'can_update'        => Session::haveRight(self::$rightname, UPDATE),
            'save_url'          => \Plugin::getWebDir('opencitaseg') . '/front/config.form.php',
        ]);
    }

    /**
     * Si el objeto ITIL acepta una cita de este tipo de destino. Combina el
     * estado del ticket (resuelto/cerrado) con el toggle que corresponda.
     */
    public static function acceptsQuote(string $itemtype, int $items_id, string $targetType): bool
    {
        $resolved = self::resolveForItem($itemtype, $items_id);

        if ($resolved === null || ! $resolved['accepts_quotes']) {
            return false;
        }

        return is_a($targetType, CommonITILTask::class, true)
            ? $resolved['is_active_tasks']
            : $resolved['is_active'];
    }
}
