<?php

require_once INCLUDE_DIR . 'class.plugin.php';
require_once __DIR__ . '/include/class.VikunjaClient.php';
require_once __DIR__ . '/include/class.FeatureRequestController.php';

class VikunjaFeatureRequestPluginConfig extends PluginConfig {
    public function getOptions() {
        return array(
            'vikunja_url' => new TextboxField(array(
                'label' => 'Vikunja URL',
                'configuration' => array('size' => 60, 'length' => 255),
                'hint' => 'Base URL of your Vikunja instance, e.g. http://192.168.2.180:8083',
                'required' => true,
            )),
            'vikunja_token' => new TextareaField(array(
                'label' => 'Vikunja API Token',
                'configuration' => array('html' => false, 'rows' => 4, 'cols' => 70),
                'hint' => 'A Vikunja API token with permission to list/create projects and create tasks.',
                'required' => true,
            )),
            'button_text' => new TextboxField(array(
                'label' => 'Ticket Button Text',
                'configuration' => array('size' => 40, 'length' => 80),
                'default' => 'Move to Projects',
                'hint' => 'Text shown on the ticket action button.',
                'required' => true,
            )),
            'feature_help_topic' => new TextboxField(array(
                'label' => 'Feature Request Help Topic',
                'configuration' => array('size' => 40, 'length' => 128),
                'default' => 'Feature Request',
                'hint' => 'Exact osTicket help topic name to set after exporting.',
                'required' => true,
            )),
            'resolved_status' => new TextboxField(array(
                'label' => 'Resolved Status',
                'configuration' => array('size' => 40, 'length' => 128),
                'default' => 'Resolved',
                'hint' => 'Exact osTicket status name to set after exporting.',
                'required' => true,
            )),
            'ticket_response' => new TextareaField(array(
                'label' => 'Ticket Response',
                'configuration' => array('html' => false, 'rows' => 5, 'cols' => 70),
                'default' => 'Since this is a feature request, we are moving it to our Project tracker and closing this ticket. Thank you for your suggestion.',
                'hint' => 'Public response posted to the ticket after the Vikunja task is created.',
                'required' => true,
            )),
        );
    }
}

class VikunjaFeatureRequestPlugin extends Plugin {
    public $config_class = 'VikunjaFeatureRequestPluginConfig';
    private $instanceConfig;

    public function bootstrap() {
        // osTicket side-loads plugin instance config only during bootstrap and
        // clears it afterwards. Keep a reference for Ajax callbacks registered
        // during this bootstrap cycle.
        $this->instanceConfig = $this->getConfig();
        $this->registerRoutes();
        $this->injectStaffAssets();
    }

    protected function registerRoutes() {
        Signal::connect('ajax.scp', array($this, 'ajax'));
    }

    protected function injectStaffAssets() {
        if (!defined('INCLUDE_DIR') || !isset($_SERVER['SCRIPT_NAME'])) {
            return;
        }

        $script = $_SERVER['SCRIPT_NAME'];
        $isTicketPage = strpos($script, '/scp/tickets.php') !== false;
        if (!$isTicketPage || empty($_GET['id'])) {
            return;
        }

        $css = @file_get_contents(__DIR__ . '/css/vikunja-feature.css');
        $js = @file_get_contents(__DIR__ . '/js/vikunja-feature.js');
        if ($css) {
            echo "\n<style id=\"vikunja-feature-request-css\">\n" . $css . "\n</style>\n";
        }
        $buttonText = trim((string) $this->getPluginConfig()->get('button_text')) ?: 'Move to Projects';
        echo sprintf("<script>window.VIKUNJA_FEATURE_REQUEST = {ticketId:%d, ajaxBase:%s, buttonText:%s};</script>\n", (int) $_GET['id'], json_encode($this->getAjaxBaseUrl()), json_encode($buttonText));
        if ($js) {
            echo "<script id=\"vikunja-feature-request-js\">\n" . $js . "\n</script>\n";
        }
    }

    protected function getAjaxBaseUrl() {
        return ROOT_PATH . 'scp/ajax.php/vikunja-feature-request';
    }

    public function ajax($object, $data) {
        $path = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';
        if (strpos($path, 'vikunja-feature-request') !== 0) {
            return;
        }

        $controller = new VikunjaFeatureRequestController($this->getPluginConfig());
        $controller->dispatch($path);
        exit;
    }

    protected function getPluginConfig() {
        return $this->instanceConfig ?: $this->getConfig();
    }
}
