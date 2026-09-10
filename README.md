# Opencitaseg — GLPI Plugin

Allows quoting ITIL follow-ups and tasks in GLPI changes, problems and tickets with a single click, improving communication and tracking within the timeline.

## Features

* Adds a "Quote" (Citar) button to every ITIL followup in the timeline.
* Seamlessly integrates with GLPI's native TinyMCE rich text editor.
* Automatically inserts a formatted blockquote with the author's name and the original text.
* Interactive citations: Clicking the citation link smoothly scrolls to and highlights the original quoted followup.
* Notifies the author of a follow-up when someone quotes it.
* Maintains data integrity by securely linking source and target followups in the database.
* Quotes tasks as well as follow-ups, on tickets, changes and problems.
* Per-entity configuration with inheritance from the root entity.
* Each answer allows only one citation. If you add another one before saving, the previous citation will be replaced, but the text will be preserved.

## Requirements

| Requirement | Version |
| :--- | :--- |
| **GLPI** | `>= 11.0.0, < 12.0.0` |
| **PHP** | `>= 8.2` |

## Installation

1. Download the latest release or clone this repository into the `<GLPI_ROOT>/plugins/opencitaseg/` directory.
2. Navigate to **Setup > Plugins** in your GLPI interface.
3. Locate **opencitaseg** and click **Install**, then **Enable**.

**Using Git:**

```bash
cd /var/www/glpi/plugins
git clone https://github.com/Open-SA/opencitaseg.git opencitaseg
```

## Configuration

Quotes are configured per entity, in the **Follow-up quotes** tab of the entity form:

* **Inherit from parent entity** — resolves the setting up the entity tree. The root entity always defines its own value.
* **Enable quotes** — master switch. When disabled, the quote button is not shown and quotes are rejected server-side.
* **Quotes private by default** — initial value of the private flag on a new quote.

A quote of a private follow-up or task is always forced to private, regardless of this setting.

## Usage

1. Open an existing change, problem or ticket that contains follow-ups or tasks.
2. Locate the follow-up or task you want to reply to and click the **"Quote"** button next to it.
3. The new followup panel will open automatically, and the rich text editor will be populated with the cited text.
4. Type your reply below the quote and click **Add**.

## Permissions

The quote button is injected purely on the frontend. Server-side, the plugin natively respects GLPI's visibility rules. A user can only quote a followup if they have the necessary rights to view it (`canViewItem()`) and the right to add a new followup to the ITIL object. Quotes are not offered on resolved or closed objects.

## Notifications

When a follow-up is quoted, the author of the quoted follow-up is notified.

* The notification is registered on install for Tickets, Changes and Problems, under the event
  **Follow-up quoted**, and can be edited or disabled in **Setup > Notifications**.
* Both delivery modes are registered: e-mail and browser notification.
* The body never contains the text of any follow-up — only who quoted, when, the object title and its URL.
* **Private follow-ups do not trigger a notification.** GLPI evaluates the "see private follow-ups" right
  against the active session, so the plugin cannot verify it on behalf of the recipient and fails closed.
* No notification is sent when a user quotes their own follow-up, or when the quoted follow-up has no
  author (e.g. created by the mail collector).

## File Structure

```text
opencitaseg/
├── hook.php                        # Install/uninstall hooks, DB schema & item_add logic
├── setup.php                       # Plugin registration (version, hooks, assets)
├── opencitaseg.xml                 # Marketplace metadata
├── ajax/
│   └── isactive.php                # Resolves the per-entity configuration for the client
├── front/
│   └── config.form.php             # Handles the entity configuration form submit
├── locales/                        # gettext catalogues (.po / .mo) for the 5 supported languages
├── templates/
│   └── config.html.twig            # Entity configuration tab
├── tools/
│   └── build-js-locales.py         # Builds the JS dictionaries from the .po files
├── src/
│   ├── Cite.php                    # DB object class for citation relations
│   ├── CiteNotification.php        # Notification event, recipient resolution and templates
│   └── Config.php                  # Per-entity configuration and inheritance resolution
└── public/
    ├── css/
    │   └── citas.css               # Timeline quote styling and highlight
    └── js/
        ├── citas.js                # Client-side logic (DOM injection, TinyMCE, scrolling)
        └── locales/                # Per-language JS dictionaries (GLPI does not expose plugin
                                    # gettext domains to the frontend)
```

## License

This plugin is distributed under the GNU General Public License v2.0 or later (GPLv2+).

## Author

Open-SA https://github.com/Open-SA