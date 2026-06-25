# osTicket → Vikunja Feature Request Exporter

A staff-side osTicket v1.18 plugin that adds a configurable **Move to Projects** action to open tickets. Staff can select an existing Vikunja project, create a new project, and export the ticket as a rich Vikunja task. After successful export, the plugin labels the Vikunja task, updates the osTicket ticket workflow, and redirects the staff user back to the queue.

Built for the DregonX2 Vikunja custom instance, but configurable for any compatible Vikunja deployment.

---

## What It Does

On open ticket pages in osTicket staff control panel, this plugin:

1. Adds a configurable ticket action button, default **Move to Projects**, into the native osTicket ticket toolbar matching:
   - `.sticky.bar .content > .pull-right.flush-right`
2. Opens an osTicket modal with:
   - A dropdown of projects queried from Vikunja
   - An option to create a new Vikunja project
3. Creates a Vikunja task in the selected or newly-created project containing:
   - Ticket title / subject
   - Current osTicket assignee
   - Ticket number
   - Ticket staff URL
   - Full ticket thread rendered as Vikunja-friendly rich HTML
   - Configured Vikunja label, default `support`
4. Updates the osTicket ticket after successful Vikunja API creation:
   - Sets help topic to **Feature Request** by default
   - Assigns the ticket to the staff member who clicked the button
   - Posts this public response:

   > Since this is a feature request, we are moving it to our Project tracker and closing this ticket. Thank you for your suggestion.

   - Sets status to **Resolved** by default
   - Redirects the staff user back to `/scp/tickets.php?queue=1`

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
  - Read/create labels
  - Attach labels to tasks
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
| Ticket Button Text | Staff ticket action button label | `Move to Projects` |
| Vikunja Label | Label/tag added to every created Vikunja task | `support` |
| Feature Request Help Topic | Exact osTicket help topic name | `Feature Request` |
| Resolved Status | Exact osTicket ticket status name | `Resolved` |
| Ticket Response | Public reply posted before resolving | Default response text |

---

## Creating a Vikunja API Token

In Vikunja:

1. Log in as a user that can create tasks/projects.
2. Open user settings / API tokens.
3. Create a token with permissions for project, task, and label operations.
4. Copy the token into the plugin configuration.

> Keep the token private. It gives osTicket permission to create projects/tasks and apply labels in Vikunja.

Minimum practical permissions used by the plugin:

| Area | Needed permissions | Why |
|---|---|---|
| `projects` | `read_all`, `create` | Populate dropdown and create new project |
| `tasks` | `create` | Create Vikunja task |
| `labels` | `read_all`, `read_one`, `create` | Find or create the configured label |
| `tasks_labels` | `create` | Attach the configured label to the created task |

---

## Staff Workflow

1. Open an active ticket in osTicket staff panel.
2. Click **Move to Projects** — or your configured button label.
3. Select a Vikunja project from the dropdown, or type a new project name.
4. Click **Send to Vikunja & Resolve**.
5. The plugin will:
   - Create the Vikunja task
   - Ensure/apply the configured Vikunja label, default `support`
   - Update the help topic
   - Assign the ticket to the current staff user
   - Post the configured response
   - Resolve the ticket
   - Redirect to `/scp/tickets.php?queue=1`

---

## Vikunja Task Format

The created Vikunja task title uses:

```text
[OSTICKET_NUMBER] Ticket Subject
```

The task description/details are sent as **rich HTML**, because this Vikunja build stores and renders task descriptions as HTML rather than Markdown. This avoids literal `#`, `**`, and `>` characters showing in the task body.

Example structure:

```html
<h1>osTicket Feature Request</h1>
<ul>
  <li><strong>Ticket:</strong> #123456</li>
  <li><strong>Original ticket:</strong> <a href="/scp/tickets.php?id=123">/scp/tickets.php?id=123</a></li>
  <li><strong>Assigned to in osTicket:</strong> Agent Name</li>
  <li><strong>Exported by:</strong> Staff User</li>
</ul>

<h2>Ticket Thread</h2>

<h3>Requester — 2026-06-24 18:00:00</h3>
<blockquote>
  <p>Original ticket message...</p>
</blockquote>

<hr>

<h3>Agent — 2026-06-24 18:05:00</h3>
<blockquote>
  <p>Agent response...</p>
</blockquote>
```

Ticket thread content is escaped before insertion so requester/staff text cannot inject arbitrary HTML. Basic links from osTicket are preserved as readable text with their URL.

---

## API Endpoints Used

The plugin calls Vikunja with Bearer token auth:

```http
GET /api/v1/projects
PUT /api/v1/projects
PUT /api/v1/projects/{project_id}/tasks
GET /api/v1/labels?s=support
PUT /api/v1/labels
PUT /api/v1/tasks/{task_id}/labels
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

The plugin injects the button into the native ticket sticky toolbar:

```css
.sticky.bar .content > .pull-right.flush-right
```

The inserted control uses osTicket toolbar classes (`action-button pull-right`) so it preserves the surrounding button layout. If your production theme has customized the ticket header markup, adjust `js/vikunja-feature.js` in `addButton()` only; no osTicket core edit is required.

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
- Check Apache/PHP logs for fatal errors from `include/plugins/vikunja-feature-request`.

### Vikunja returns 401

- Regenerate the Vikunja API token.
- Confirm the token is pasted without extra whitespace.
- Confirm the token has permissions to list/create projects, create tasks, read/create labels, and attach labels to tasks.

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
- Attachment export from osTicket to Vikunja
- Store Vikunja task ID back onto the ticket as dynamic field metadata
- OAuth/service-account support if Vikunja token scopes change

---

## License

AGPL-3.0-or-later. See [`LICENSE`](LICENSE).

---

## Credits

Built for DregonX2’s osTicket + Vikunja workflow.
