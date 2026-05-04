<?php
function plugin_helpbubble_install() {
   global $DB;
   if (!$DB->tableExists('glpi_plugin_helpbubble_config')) {
      $DB->doQuery("
         CREATE TABLE `glpi_plugin_helpbubble_config` (
            `k` VARCHAR(64) NOT NULL PRIMARY KEY,
            `v` TEXT
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
      ");

      $defaults = [
         'mode'        => 'local',
         'n8n_url'     => '',
         'xai_api_key' => '',
         'xai_model'   => 'grok-4-1-fast-non-reasoning',
      ];
      foreach ($defaults as $k => $v) {
         $kE = $DB->escape($k);
         $vE = $DB->escape($v);
         $DB->doQuery("INSERT IGNORE INTO glpi_plugin_helpbubble_config (k, v) VALUES ('$kE', '$vE')");
      }
   }
   return true;
}

function plugin_helpbubble_uninstall() {
   global $DB;
   $DB->doQuery("DROP TABLE IF EXISTS glpi_plugin_helpbubble_config");
   return true;
}