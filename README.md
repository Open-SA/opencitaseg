# Opencitaseg — GLPI Plugin

Allows quoting ITIL follow-ups in GLPI tickets with a single click, improving communication and tracking within the ticket timeline.

## Features

* Adds a "Quote" (Citar) button to every ITIL followup in the ticket timeline.
* Seamlessly integrates with GLPI's native TinyMCE rich text editor.
* Automatically inserts a formatted blockquote with the author's name and the original text.
* Interactive citations: Clicking the citation link smoothly scrolls to and highlights the original quoted followup.
* Maintains data integrity by securely linking source and target followups in the database.

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
git clone [https://github.com/Open-SA/opencitaseg.git](https://github.com/Open-SA/opencitaseg.git) opencitaseg
```
## Usage

1. Open an existing ticket that contains followups.
2. Locate the followup you want to reply to and click the **"Citar"** (Quote) button next to it.
3. The new followup panel will open automatically, and the rich text editor will be populated with the cited text.
4. Type your reply below the quote and click **Add**.

## Permissions

The quote button is injected purely on the frontend. Server-side, the plugin natively respects GLPI's visibility rules. A user can only quote a followup if they have the necessary rights to view it (`canViewItem()`) and the right to add a new followup to the ticket.

## File Structure

```text
opencitaseg/
├── hook.php                        # Install/uninstall DB hooks & item_add logic
├── setup.php                       # Plugin registration (version, hooks, assets)
├── opencitaseg.xml                 # Marketplace metadata
├── src/
│   └── Cite.php                    # DB object class for citation relations
└── public/
    └── js/
        └── citas.js                # Client-side logic (DOM injection, TinyMCE, scrolling)
```

## License
This plugin is distributed under the GNU General Public License v2.0 or later (GPLv2+).

## Author
Open-SA https://github.com/Open-SA
