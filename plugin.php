<?php
/**
 * Plugin metadata for osTicket.
 */

return array(
    'id' => 'dregonx2:vikunja-feature-request',
    'version' => '1.0.0',
    'name' => 'Vikunja Feature Request Exporter',
    'author' => 'DregonX2',
    'description' => 'Adds a staff ticket button that exports open osTicket tickets to Vikunja as feature-request tasks, then resolves the ticket.',
    'url' => 'https://github.com/DregonX2/osticket-vikunja-feature-plugin',
    'plugin' => 'vikunja.php:VikunjaFeatureRequestPlugin',
);
