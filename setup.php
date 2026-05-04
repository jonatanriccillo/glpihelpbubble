<?php
use Glpi\Http\Firewall;

define('PLUGIN_HELPBUBBLE_VERSION', '0.2.1');

function plugin_init_helpbubble() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['helpbubble'] = true;
   $PLUGIN_HOOKS['add_javascript']['helpbubble'] = ['public/helpbubble.js'];
   $PLUGIN_HOOKS['add_css']['helpbubble']        = ['public/helpbubble.css'];
   $PLUGIN_HOOKS['config_page']['helpbubble']    = 'front/config.form.php';

   if (class_exists('\\Glpi\\Http\\Firewall')) {
      Firewall::addPluginStrategyForLegacyScripts(
         'helpbubble',
         '#.*#',
         Firewall::STRATEGY_NO_CHECK
      );
   }
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