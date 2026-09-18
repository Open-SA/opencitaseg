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

use Change;
use CommonDBTM;
use Html;
use ITILFollowup;
use Notification;
use Notification_NotificationTemplate;
use NotificationEvent;
use NotificationTarget;
use NotificationTemplate;
use NotificationTemplateTranslation;
use Problem;
use Ticket;
use User;

/**
 * Notificación "te citaron en un seguimiento".
 *
 * Modelo: el evento se levanta sobre el objeto ITIL padre (Ticket / Change /
 * Problem), no sobre el ITILFollowup, porque GLPI resuelve la clase de target
 * con NotificationTarget::getInstanceClass($item::class) y sólo los objetos
 * ITIL tienen NotificationTarget propio con los tags de contexto
 * (##ticket.title##, ##ticket.url##, ...).
 *
 * Como no se puede sustituir NotificationTargetTicket desde un plugin, el
 * evento, el destinatario y los tags propios se inyectan con los cuatro hooks
 * de notificación de GLPI, cuya clave es la clase del NotificationTarget
 * (Plugin::doHook usa get_class($param)), no el itemtype:
 *
 *   item_get_events    -> addEvents()      declara el evento
 *   item_add_targets   -> addTargets()     declara el destinatario y los tags
 *   item_action_targets-> actionTargets()  resuelve el destinatario real
 *   item_get_datas     -> addData()        completa los tags ##opencitaseg.*##
 *
 * Decisión de seguridad (ver security/stride): si el seguimiento que cita es
 * privado no se notifica. GLPI resuelve "ver seguimientos privados" contra la
 * sesión activa y no hay forma barata de evaluar ese derecho en nombre del
 * destinatario, así que se falla cerrado en vez de arriesgar una divulgación.
 */
final class CiteNotification
{
    /** Nombre del evento registrado en glpi_notifications.event */
    public const EVENT = 'opencitaseg_cite';

    /**
     * items_id propio dentro de Notification::USER_TYPE.
     * El core usa 1..41; se elige un valor alto para no colisionar si GLPI
     * agrega targets nuevos en una minor.
     */
    public const CITED_FOLLOWUP_AUTHOR = 9021;

    /** Nombre de la Notification y del NotificationTemplate creados en el install */
    private const NOTIFICATION_NAME = 'Opencitaseg - seguimiento citado';

    /**
     * Objetos ITIL sobre los que el plugin inyecta el botón de citar y, por lo
     * tanto, sobre los que hay que registrar la notificación.
     *
     * @return list<class-string>
     */
    public static function getSupportedItemtypes(): array
    {
        return [Ticket::class, Change::class, Problem::class];
    }

    /**
     * Clases de NotificationTarget usadas como clave en $PLUGIN_HOOKS.
     *
     * @return list<string>
     */
    public static function getTargetClasses(): array
    {
        $classes = [];
        foreach (self::getSupportedItemtypes() as $itemtype) {
            $classes[] = NotificationTarget::getInstanceClass($itemtype);
        }

        return $classes;
    }

    // ---------------------------------------------------------------------
    // Hooks de notificación
    // ---------------------------------------------------------------------

    /**
     * Hook item_get_events: agrega el evento del plugin a la lista de eventos
     * del objeto ITIL, para que aparezca en Configuración > Notificaciones.
     */
    public static function addEvents(NotificationTarget $target): void
    {
        $target->events[self::EVENT] = __('Follow-up quoted', 'opencitaseg');
    }

    /**
     * Hook item_add_targets: se ejecuta dentro del constructor del target.
     *
     * Los tags se registran siempre porque la ayuda de la plantilla
     * (NotificationTemplateTranslation::showAvailableTags) instancia el target
     * sin evento. El destinatario, en cambio, sólo se ofrece para el evento
     * propio, para no ensuciar el desplegable del resto de las notificaciones
     * de ticket.
     */
    public static function addTargets(NotificationTarget $target): void
    {
        self::registerTags($target);

        if ($target->raiseevent !== '' && $target->raiseevent !== self::EVENT) {
            return;
        }

        $target->addTarget(
            self::CITED_FOLLOWUP_AUTHOR,
            __('Author of the quoted follow-up', 'opencitaseg'),
            Notification::USER_TYPE,
        );
    }

    /**
     * Hook item_action_targets: resuelve el destinatario.
     *
     * En este punto $target->data contiene la fila de glpi_notificationtargets
     * que se está procesando (NotificationTarget::addForTarget la pisa justo
     * antes de invocar el hook), y $target->options son las opciones con las
     * que se levantó el evento.
     */
    public static function actionTargets(NotificationTarget $target): void
    {
        if ($target->raiseevent !== self::EVENT) {
            return;
        }

        if ((int) ($target->data['type'] ?? 0) !== Notification::USER_TYPE) {
            return;
        }

        if ((int) ($target->data['items_id'] ?? 0) !== self::CITED_FOLLOWUP_AUTHOR) {
            return;
        }

        $usersId = (int) ($target->options['users_id'] ?? 0);
        if ($usersId <= 0) {
            return;
        }

        $user = new User();
        if (! $user->getFromDB($usersId)) {
            return;
        }

        // addToRecipientsList descarta por su cuenta usuarios borrados,
        // inactivos, fuera de vigencia o sin perfil en la entidad del objeto.
        $target->addToRecipientsList([
            'language' => $user->fields['language'],
            'users_id' => $user->fields['id'],
        ]);
    }

    /**
     * Hook item_get_datas: completa los tags propios.
     *
     * Corre al final de NotificationTarget::getForTemplate(), después de que el
     * core llenó los ##ticket.*## y los ##lang.*##, así que sólo hay que
     * agregar los valores del plugin.
     */
    public static function addData(NotificationTarget $target): void
    {
        if ($target->raiseevent !== self::EVENT) {
            return;
        }

        $context = $target->options['_opencitaseg'] ?? null;
        if (! is_array($context)) {
            return;
        }

        $target->data['##opencitaseg.citedby##']   = $context['citedby'] ?? '';
        $target->data['##opencitaseg.citedate##']  = $context['citedate'] ?? '';
    }

    /**
     * Descripción de los tags propios, para la ayuda de la plantilla.
     */
    private static function registerTags(NotificationTarget $target): void
    {
        $target->addTagToList([
            'tag'    => 'opencitaseg.citedby',
            'label'  => __('User who quoted the follow-up', 'opencitaseg'),
            'value'  => true,
            'events' => \NotificationTarget::TAG_FOR_ALL_EVENTS,
        ]);

        $target->addTagToList([
            'tag'    => 'opencitaseg.citedate',
            'label'  => __('Date of the quote', 'opencitaseg'),
            'value'  => true,
            'events' => \NotificationTarget::TAG_FOR_ALL_EVENTS,
        ]);
    }

    // ---------------------------------------------------------------------
    // Disparo del evento
    // ---------------------------------------------------------------------

    /**
     * Levanta el evento para el autor del seguimiento citado.
     *
     * @param ITILFollowup $source Seguimiento nuevo (el que cita)
     * @param ITILFollowup $quoted Seguimiento citado
     */
    public static function raiseForCite(ITILFollowup $source, ITILFollowup $quoted): void
    {
        // Fail-closed: no notificamos desde un seguimiento privado porque no
        // podemos evaluar el derecho SEEPRIVATE en nombre del destinatario.
        if ((int) ($source->fields['is_private'] ?? 0) === 1) {
            return;
        }

        $recipientId = (int) ($quoted->fields['users_id'] ?? 0);
        if ($recipientId <= 0) {
            // Seguimiento sin autor (p. ej. creado por el colector de correo).
            return;
        }

        $authorId = (int) ($source->fields['users_id'] ?? 0);
        if ($authorId === $recipientId) {
            // Autocita: no tiene sentido notificar.
            return;
        }

        $itemtype = $source->fields['itemtype'];
        if (! in_array($itemtype, self::getSupportedItemtypes(), true)) {
            return;
        }

        $mainItem = getItemForItemtype($itemtype);
        if (! $mainItem instanceof CommonDBTM || ! $mainItem->getFromDB((int) $source->fields['items_id'])) {
            return;
        }

        $author = new User();
        $authorName = $author->getFromDB($authorId)
            ? $author->getFriendlyName()
            : __('Unknown user', 'opencitaseg');

        // El nombre real de un usuario es dato de entrada (LDAP o alta manual)
        // y el reemplazo de tags de NotificationTemplate no escapa: se escapa
        // acá para que no pueda inyectar HTML en el cuerpo del mail.
        //
        // htmlspecialchars() en lugar de htmlescape(), que solo existe en
        // GLPI 11.
        $authorName = htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8');

        NotificationEvent::raiseEvent(
            self::EVENT,
            $mainItem,
            [
                'users_id'     => $recipientId,
                'followup_id'  => (int) $source->fields['id'],
                'is_private'   => (int) ($source->fields['is_private'] ?? 0),
                '_opencitaseg' => [
                    'citedby'  => $authorName,
                    'citedate' => Html::convDateTime($source->fields['date'] ?? null),
                ],
            ],
            $source,
        );
    }

    // ---------------------------------------------------------------------
    // Install / uninstall
    // ---------------------------------------------------------------------

    public static function install(): bool
    {
        foreach (self::getSupportedItemtypes() as $itemtype) {
            if (! self::installForItemtype($itemtype)) {
                return false;
            }
        }

        return true;
    }

    public static function uninstall(): bool
    {
        // El purge de Notification encadena Notification_NotificationTemplate
        // y NotificationTarget (Notification::cleanDBonPurge).
        $notification = new Notification();
        foreach ($notification->find(['event' => self::EVENT]) as $row) {
            $notification->delete(['id' => $row['id']], true);
        }

        $template = new NotificationTemplate();
        foreach ($template->find(['name' => ['LIKE', self::NOTIFICATION_NAME . '%']]) as $row) {
            $template->delete(['id' => $row['id']], true);
        }

        return true;
    }

    private static function installForItemtype(string $itemtype): bool
    {
        $notification = new Notification();

        // Idempotente: en una reinstalación no duplicamos la configuración
        // (y respetamos los cambios que el administrador haya hecho).
        if (count($notification->find(['itemtype' => $itemtype, 'event' => self::EVENT])) > 0) {
            return true;
        }

        $label  = self::NOTIFICATION_NAME . ' (' . $itemtype . ')';
        $prefix = strtolower($itemtype);

        $template   = new NotificationTemplate();
        $templateId = (int) $template->add([
            'name'     => $label,
            'itemtype' => $itemtype,
            'comment'  => '',
            'css'      => '',
        ]);

        if ($templateId <= 0) {
            return false;
        }

        $translation = new NotificationTemplateTranslation();
        foreach (self::getTemplateTranslations() as $language => $texts) {
            $translation->add([
                'notificationtemplates_id' => $templateId,
                'language'                 => $language,
                'subject'                  => str_replace('%TYPE%', $prefix, $texts['subject']),
                'content_text'             => str_replace('%TYPE%', $prefix, $texts['content_text']),
                'content_html'             => str_replace('%TYPE%', $prefix, $texts['content_html']),
            ]);
        }

        $notificationId = (int) $notification->add([
            'name'           => $label,
            'entities_id'    => 0,
            'is_recursive'   => 1,
            'itemtype'       => $itemtype,
            'event'          => self::EVENT,
            'comment'        => '',
            'is_active'      => 1,
            'allow_response' => 0,
        ]);

        if ($notificationId <= 0) {
            return false;
        }

        $link = new Notification_NotificationTemplate();
        foreach (
            [
                Notification_NotificationTemplate::MODE_MAIL,
                Notification_NotificationTemplate::MODE_AJAX,
            ] as $mode
        ) {
            $link->add([
                'notifications_id'         => $notificationId,
                'notificationtemplates_id' => $templateId,
                'mode'                     => $mode,
            ]);
        }

        $target = new NotificationTarget();
        $target->add([
            'notifications_id' => $notificationId,
            'type'             => Notification::USER_TYPE,
            'items_id'         => self::CITED_FOLLOWUP_AUTHOR,
        ]);

        return true;
    }

    /**
     * Traducciones de la plantilla.
     *
     * Las plantillas viven en la base, no en gettext: si usáramos __() acá,
     * todas las traducciones quedarían en el idioma del que instaló el plugin.
     * `%TYPE%` se reemplaza por el itemtype en minúscula (ticket/change/problem)
     * para que los tags del core resuelvan.
     *
     * La clave '' es la traducción por defecto que GLPI usa como fallback
     * (NotificationTemplate::getByLanguage busca `language IN (<lang>, '')`).
     *
     * @return array<string, array{subject: string, content_text: string, content_html: string}>
     */
    private static function getTemplateTranslations(): array
    {
        $translations = [
            '' => [
                'subject'      => '##%TYPE%.action## : ##%TYPE%.title##',
                'content_text' => "##opencitaseg.citedby## quoted one of your follow-ups"
                    . " (##opencitaseg.citedate##).\n\n"
                    . "##lang.%TYPE%.title## : ##%TYPE%.title##\n"
                    . "##lang.%TYPE%.url## : ##%TYPE%.url##\n",
                'content_html' => '<p>##opencitaseg.citedby## quoted one of your follow-ups'
                    . " (##opencitaseg.citedate##).</p>\n"
                    . "<p>##lang.%TYPE%.title## : ##%TYPE%.title##</p>\n"
                    . '<p><a href="##%TYPE%.url##">##%TYPE%.url##</a></p>',
            ],
            'es_AR' => [
                'subject'      => '##%TYPE%.action## : ##%TYPE%.title##',
                'content_text' => "##opencitaseg.citedby## citó uno de tus seguimientos"
                    . " (##opencitaseg.citedate##).\n\n"
                    . "##lang.%TYPE%.title## : ##%TYPE%.title##\n"
                    . "##lang.%TYPE%.url## : ##%TYPE%.url##\n",
                'content_html' => '<p>##opencitaseg.citedby## citó uno de tus seguimientos'
                    . " (##opencitaseg.citedate##).</p>\n"
                    . "<p>##lang.%TYPE%.title## : ##%TYPE%.title##</p>\n"
                    . '<p><a href="##%TYPE%.url##">##%TYPE%.url##</a></p>',
            ],
            'fr_FR' => [
                'subject'      => '##%TYPE%.action## : ##%TYPE%.title##',
                'content_text' => "##opencitaseg.citedby## a cité l'un de vos suivis"
                    . " (##opencitaseg.citedate##).\n\n"
                    . "##lang.%TYPE%.title## : ##%TYPE%.title##\n"
                    . "##lang.%TYPE%.url## : ##%TYPE%.url##\n",
                'content_html' => "<p>##opencitaseg.citedby## a cité l'un de vos suivis"
                    . " (##opencitaseg.citedate##).</p>\n"
                    . "<p>##lang.%TYPE%.title## : ##%TYPE%.title##</p>\n"
                    . '<p><a href="##%TYPE%.url##">##%TYPE%.url##</a></p>',
            ],
            'pt_BR' => [
                'subject'      => '##%TYPE%.action## : ##%TYPE%.title##',
                'content_text' => "##opencitaseg.citedby## citou um dos seus acompanhamentos"
                    . " (##opencitaseg.citedate##).\n\n"
                    . "##lang.%TYPE%.title## : ##%TYPE%.title##\n"
                    . "##lang.%TYPE%.url## : ##%TYPE%.url##\n",
                'content_html' => '<p>##opencitaseg.citedby## citou um dos seus acompanhamentos'
                    . " (##opencitaseg.citedate##).</p>\n"
                    . "<p>##lang.%TYPE%.title## : ##%TYPE%.title##</p>\n"
                    . '<p><a href="##%TYPE%.url##">##%TYPE%.url##</a></p>',
            ],
        ];

        // es_ES y en_GB comparten texto con es_AR / la traducción por defecto.
        $translations['es_ES'] = $translations['es_AR'];
        $translations['en_GB'] = $translations[''];

        return $translations;
    }
}
