<?php

class VikunjaClient {
    private $baseUrl;
    private $token;

    public function __construct($baseUrl, $token) {
        $this->baseUrl = rtrim((string) $baseUrl, '/');
        $this->token = trim((string) $token);
    }

    public function testConnection() {
        $this->request('GET', '/api/v1/projects');
        return true;
    }

    public function listProjects() {
        return $this->request('GET', '/api/v1/projects');
    }

    public function createProject($title) {
        return $this->request('PUT', '/api/v1/projects', array('title' => $title));
    }

    public function createTask($projectId, array $task) {
        return $this->request('PUT', '/api/v1/projects/' . rawurlencode($projectId) . '/tasks', $task);
    }

    private function request($method, $path, array $payload = null) {
        if (!$this->baseUrl || !$this->token) {
            throw new Exception('Vikunja URL and API token are required.');
        }

        $ch = curl_init($this->baseUrl . $path);
        $headers = array(
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token,
        );

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($payload !== null) {
            $body = json_encode($payload);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new Exception('Vikunja request failed: ' . $err);
        }

        $decoded = json_decode($body, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded) && isset($decoded['message']) ? $decoded['message'] : $body;
            throw new Exception('Vikunja API error HTTP ' . $status . ': ' . $message);
        }

        return $decoded === null ? array() : $decoded;
    }
}
