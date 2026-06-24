<?php

class VikunjaFeatureRequestController {
    private $config;

    public function __construct($config) {
        $this->config = $config;
    }

    public function dispatch($path) {
        $this->requireStaff();
        $segments = explode('/', trim($path, '/'));
        $action = isset($segments[1]) ? $segments[1] : '';

        try {
            switch ($action) {
                case 'projects':
                    $this->json($this->projects());
                    return;
                case 'test':
                    $this->json($this->test());
                    return;
                case 'export':
                    $this->json($this->export());
                    return;
                default:
                    $this->jsonError('Unknown endpoint.', 404);
                    return;
            }
        } catch (Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    private function requireStaff() {
        global $thisstaff;
        if (!$thisstaff || !$thisstaff->isValid()) {
            $this->jsonError('Authentication required.', 401);
            exit;
        }
    }

    private function client() {
        return new VikunjaClient(
            $this->config->get('vikunja_url'),
            $this->config->get('vikunja_token')
        );
    }

    private function projects() {
        $projects = $this->client()->listProjects();
        return array('projects' => $this->flattenProjects($projects));
    }

    private function test() {
        $this->client()->testConnection();
        return array('ok' => true, 'message' => 'Connected to Vikunja successfully.');
    }

    private function export() {
        global $thisstaff;

        $ticketId = (int) $this->input('ticket_id');
        $projectId = (int) $this->input('project_id');
        $newProjectTitle = trim((string) $this->input('new_project_title'));

        if (!$ticketId) {
            throw new Exception('Ticket id is required.');
        }

        $ticket = Ticket::lookup($ticketId);
        if (!$ticket) {
            throw new Exception('Ticket not found.');
        }
        if ($ticket->isClosed()) {
            throw new Exception('Only open tickets can be exported.');
        }

        $client = $this->client();
        if (!$projectId) {
            if ($newProjectTitle === '') {
                throw new Exception('Select a project or enter a new project name.');
            }
            $project = $client->createProject($newProjectTitle);
            $projectId = isset($project['id']) ? (int) $project['id'] : 0;
            if (!$projectId) {
                throw new Exception('Vikunja did not return a project id.');
            }
        }

        $task = $this->buildTaskPayload($ticket, $thisstaff);
        $createdTask = $client->createTask($projectId, $task);

        $this->updateTicketAfterExport($ticket, $thisstaff, $createdTask);

        return array(
            'ok' => true,
            'message' => 'Ticket exported to Vikunja and resolved.',
            'task' => $createdTask,
        );
    }

    private function buildTaskPayload($ticket, $staff) {
        $assigned = $ticket->getAssignee();
        $assignedTo = $assigned ? $assigned->getName() : 'Unassigned';
        $ticketUrl = $this->ticketUrl($ticket);
        $thread = $this->ticketThreadText($ticket);

        $description = "Imported from osTicket ticket #" . $ticket->getNumber() . "\n\n";
        $description .= "Original ticket: " . $ticketUrl . "\n";
        $description .= "Assigned to in osTicket: " . $assignedTo . "\n";
        $description .= "Exported by: " . $staff->getName() . "\n\n";
        $description .= "## Ticket Thread\n\n" . $thread;

        return array(
            'title' => '[' . $ticket->getNumber() . '] ' . $ticket->getSubject(),
            'description' => $description,
        );
    }

    private function updateTicketAfterExport($ticket, $staff, array $task) {
        $errors = array();

        $helpTopicName = trim((string) $this->config->get('feature_help_topic')) ?: 'Feature Request';
        $statusName = trim((string) $this->config->get('resolved_status')) ?: 'Resolved';
        $response = (string) $this->config->get('ticket_response');

        $topic = Topic::lookup(array('topic' => $helpTopicName));
        if ($topic) {
            $ticket->setHelpTopicId($topic->getId());
        }

        if (method_exists($ticket, 'assignToStaff')) {
            $ticket->assignToStaff($staff->getId(), 'Assigned automatically while exporting feature request to Vikunja.', true);
        } elseif (method_exists($ticket, 'assign')) {
            $ticket->assign($staff, 'Assigned automatically while exporting feature request to Vikunja.', true);
        }

        $taskUrl = isset($task['id']) ? $this->taskUrl($task) : '';
        if ($taskUrl) {
            $response .= "\n\nProject tracker task: " . $taskUrl;
        }

        if (method_exists($ticket, 'postReply')) {
            $vars = array(
                'response' => $response,
                'signature' => 'none',
                'reply-to' => '',
            );
            $ticket->postReply($vars, $errors, $staff);
        } elseif (method_exists($ticket, 'postNote')) {
            $ticket->postNote('Feature request moved to Vikunja', $response, $staff);
        }

        $status = TicketStatus::lookup(array('name' => $statusName));
        if ($status && method_exists($ticket, 'setStatus')) {
            $ticket->setStatus($status);
        }

        if (method_exists($ticket, 'save')) {
            $ticket->save();
        }
    }

    private function ticketThreadText($ticket) {
        $thread = $ticket->getThread();
        if (!$thread) {
            return '_No thread content found._';
        }

        $lines = array();
        foreach ($thread->getEntries() as $entry) {
            $author = method_exists($entry, 'getName') ? $entry->getName() : 'Unknown';
            $created = method_exists($entry, 'getCreateDate') ? $entry->getCreateDate() : '';
            $body = method_exists($entry, 'getBody') ? $entry->getBody() : '';
            $body = trim(html_entity_decode(strip_tags($body), ENT_QUOTES, 'UTF-8'));
            if ($body === '') {
                continue;
            }
            $lines[] = '### ' . $author . ($created ? ' - ' . $created : '') . "\n\n" . $body;
        }

        return $lines ? implode("\n\n---\n\n", $lines) : '_No thread content found._';
    }

    private function flattenProjects(array $projects) {
        $flat = array();
        foreach ($projects as $project) {
            $this->appendProject($flat, $project);
        }
        usort($flat, function ($a, $b) { return strcasecmp($a['title'], $b['title']); });
        return $flat;
    }

    private function appendProject(array &$flat, array $project, $prefix = '') {
        if (isset($project['id'], $project['title'])) {
            $flat[] = array('id' => $project['id'], 'title' => $prefix . $project['title']);
        }
        foreach (array('children', 'child_projects') as $key) {
            if (!empty($project[$key]) && is_array($project[$key])) {
                foreach ($project[$key] as $child) {
                    $this->appendProject($flat, $child, $prefix . '— ');
                }
            }
        }
    }

    private function ticketUrl($ticket) {
        $base = defined('ROOT_PATH') ? ROOT_PATH : '/';
        return $base . 'scp/tickets.php?id=' . $ticket->getId();
    }

    private function taskUrl(array $task) {
        $base = rtrim((string) $this->config->get('vikunja_url'), '/');
        if (!isset($task['id'])) {
            return '';
        }
        return $base . '/tasks/' . rawurlencode($task['id']);
    }

    private function input($key) {
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        return is_array($json) && array_key_exists($key, $json) ? $json[$key] : null;
    }

    private function json($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    private function jsonError($message, $status) {
        $this->json(array('ok' => false, 'error' => $message), $status);
    }
}
