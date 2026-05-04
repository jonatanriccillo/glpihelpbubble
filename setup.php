<?php
define('PLUGIN_HELPBUBBLE_VERSION', '0.2.0');

function plugin_init_helpbubble() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['helpbubble'] = true;
   $PLUGIN_HOOKS['add_javascript']['helpbubble'] = ['public/helpbubble.js'];
   $PLUGIN_HOOKS['add_css']['helpbubble']        = ['public/helpbubble.css'];
   $PLUGIN_HOOKS['config_page']['helpbubble']    = 'front/config.form.php';
}

function plugin_version_helpbubble() {
   return [
      'name'         => 'HelpBubble',
      'version'      => PLUGIN_HELPBUBBLE_VERSION,
      'author'       => 'Jonatan Riccillo',
      'license'      => 'GPLv3',
      'homepage'     => '',
      'requirements' => [
         'glpi' => ['min' => '11.0'],
      ],
   ];
}

function plugin_helpbubble_check_prerequisites() { return true; }

// Devuelve true para que GLPI muestre la rueditas en la lista de plugins
function plugin_helpbubble_check_config() { return true; }