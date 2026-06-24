# osTicket → Vikunja Feature Request Exporter

A staff-side osTicket v1.18 plugin that adds a **Send to Vikunja** button to open tickets. Staff can select an existing Vikunja project, create a new project, and export the ticket as a Vikunja task. After successful export, the plugin automatically updates the osTicket ticket workflow.

Built for the DregonX2 Vikunja custom instance, but configurable for any compatible Vikunja deployment.

---

## What It Does

On open ticket pages in osTicket staff control panel, this plugin:

1. Adds a **Send to Vikunja** button into the ticket action area matching:
   - `.content .pull-right.flush-right`
2. Opens an osTicket modal with:
   - A dropdown of projects queried from Vikunja
   - An option to create a new Vikunja project
3. Creates a Vikunja task in the selected or newly-created project containing:
   - Ticket title / subject
   - Current osTicket assignee
   - Ticket number
   - Ticket staff URL
   - Full ticket thread text
4. Updates the osTicket ticket after successful Vikunja API creation:
   - Sets help topic to **Feature Request** by default
   - Assigns the ticket to the staff member who clicked the button
   - Posts this public response:

   > Since this is a feature request, we are moving it to our Project tracker and closing this ticket. Thank you for your suggestion.

   - Sets status to **Resolved** by default

---

## Repository Contents

```text
.
├── plugin.php                              # osTicket plugin manifest
├── vikunja.php                             # Plugin bootstrap/config/hooks
├── include/
│   ├── class.FeatureRequestController.php  # Ajax controller + ticket workflow
│   └── class.VikunjaClient.php             # Vikunja API client
├── js/
│   └── vikunja-feature.js                  # Button injection + modal UI
├── css/
│   └── vikunja-feature.css                 # Modal/button styling
└── README.md
```

---

## Requirements

- osTicket **v1.18.x**
- PHP with `curl` enabled
- Staff control panel access
- Vikunja API token with permission to:
  - List projects
  - Create projects
  - Create tasks
- A reachable Vikunja instance, e.g.:
  - `http://192.168.2.180:8083`

---

## Installation

### 1. Copy the Plugin

Copy this repository into your osTicket plugins directory using this folder name:

```bash
include/plugins/vikunja-feature-request
```

Example:

```bash
cd /path/to/osticket/include/plugins
git clone https://github.com/DregonX2/osticket-vikunja-feature-plugin.git vikunja-feature-request
```

### 2. Enable in osTicket

1. Log in to osTicket as an administrator.
2. Go to **Admin Panel → Manage → Plugins**.
3. Click **Add New Plugin** if required.
4. Enable **Vikunja Feature Request Exporter**.
5. Save changes.

### 3. Configure

Open the plugin configuration screen and set:

| Setting | Description | Example |
|---|---|---|
| Vikunja URL | Base URL for Vikunja | `http://192.168.2.180:8083` |
| Vikunja API Token | Bearer token used for API calls | `tk_...` |
| Feature Request Help Topic | Exact osTicket help topic name | `Feature Request` |
| Resolved Status | Exact osTicket ticket status name | `Resolved` |
| Ticket Response | Public reply posted before resolving | Default response text |

---

## Creating a Vikunja API Token

In Vikunja:

1. Log in as a user that can create tasks/projects.
2. Open user settings / API tokens.
3. Create a token with permissions for project and task operations.
4. Copy the token into the plugin configuration.

> Keep the token private. It gives osTicket permission to create tasks in Vikunja.

---

## Staff Workflow

1. Open an active ticket in osTicket staff panel.
2. Click **Send to Vikunja**.
3. Select a Vikunja project from the dropdown, or type a new project name.
4. Click **Send to Vikunja & Resolve**.
5. The plugin will:
   - Create the Vikunja task
   - Update the help topic
   - Assign the ticket to the current staff user
   - Post the configured response
   - Resolve the ticket
   - Reload the ticket page

---

## Vikunja Task Format

The created Vikunja task title uses:

```text
[OSTICKET_NUMBER] Ticket Subject
```

The task description includes:

```markdown
Imported from osTicket ticket #123456

Original ticket: /scp/tickets.php?id=123
Assigned to in osTicket: Agent Name
Exported by: Staff User

## Ticket Thread

### Requester - 2026-06-24 18:00:00

Original ticket message...

---

### Agent - 2026-06-24 18:05:00

Agent response...
```

---

## API Endpoints Used

The plugin calls Vikunja with Bearer token auth:

```http
GET /api/v1/projects
PUT /api/v1/projects
PUT /api/v1/projects/{project_id}/tasks
```

The plugin exposes staff-authenticated osTicket ajax routes:

```http
GET  /scp/ajax.php/vikunja-feature-request/projects
GET  /scp/ajax.php/vikunja-feature-request/test
POST /scp/ajax.php/vikunja-feature-request/export
```

---

## Configuration Notes

### Help Topic

The plugin looks up the help topic by exact name. Make sure **Feature Request** exists in:

```text
Admin Panel → Manage → Help Topics
```

If your help topic is named differently, update the plugin setting.

### Ticket Status

The plugin looks up the status by exact name. Make sure **Resolved** exists in:

```text
Admin Panel → Manage → Lists → Ticket Statuses
```

If your resolved status is named differently, update the plugin setting.

### Button Placement

The plugin injects the button into:

```css
.content .pull-right.flush-right
```

If your osTicket theme has customized the ticket header markup, adjust `js/vikunja-feature.js` in `addButton()`.

---

## Compatibility

This plugin targets osTicket **v1.18.x** and uses standard plugin/config classes plus staff ajax routing.

osTicket internals can differ between patch releases and local customizations. If your install uses customized ticket classes, verify these methods exist or adjust the controller:

- `Ticket::lookup()`
- `Ticket::getThread()`
- `Ticket::postReply()`
- `Ticket::setStatus()`
- `Ticket::save()`
- `Topic::lookup()`
- `TicketStatus::lookup()`

The implementation includes light fallbacks for assignment and response posting, but production installs should still be smoke-tested before enabling for all staff.

---

## Security

- Ajax routes require a valid authenticated staff session.
- Vikunja API token is stored in osTicket plugin configuration.
- Do not commit real API tokens.
- Use HTTPS for production Vikunja URLs.
- Create a dedicated Vikunja integration user with least-privilege project/task permissions.

---

## Troubleshooting

### Button does not appear

- Confirm the plugin is enabled.
- Confirm you are viewing an open ticket under `/scp/tickets.php?id=...`.
- Inspect the page and confirm `.content .pull-right.flush-right` exists.
- Check browser console for JavaScript errors.

### Project dropdown does not load

- Confirm the Vikunja URL is reachable from the osTicket server.
- Confirm the API token is valid.
- Confirm PHP `curl` is installed.
- Check osTicket/PHP error logs.

### Export creates task but ticket does not resolve

- Confirm the configured help topic exists exactly.
- Confirm the configured status exists exactly.
- Check whether your osTicket install customized ticket workflow APIs.

### Vikunja returns 401

- Regenerate the Vikunja API token.
- Confirm the token is pasted without extra whitespace.
- Confirm the token has permissions to list projects and create tasks.

---

## Development

### Local Checks

```bash
php -l plugin.php
php -l vikunja.php
php -l include/class.VikunjaClient.php
php -l include/class.FeatureRequestController.php
```

### Release Packaging

```bash
zip -r osticket-vikunja-feature-plugin.zip \
  plugin.php vikunja.php include js css README.md
```

Install the extracted folder as:

```text
include/plugins/vikunja-feature-request
```

---

## Roadmap

Potential future enhancements:

- Config-screen “Test Connection” button
- Optional private/internal note instead of public response
- Optional task labels such as `feature-request` and `osticket`
- Attachment export from osTicket to Vikunja
- Store Vikunja task ID back onto the ticket as dynamic field metadata
- OAuth/service-account support if Vikunja token scopes change

---

## License

AGPL-3.0-or-later. See [`LICENSE`](LICENSE).

---

## Credits

Built for DregonX2’s osTicket + Vikunja workflow.
