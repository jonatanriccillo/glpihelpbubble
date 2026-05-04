<?php
include('../../../inc/includes.php');

Session::checkRight('config', READ);

Html::header(
   'HelpBubble',
   $_SERVER['PHP_SELF'],
   'config',
   'PluginHelpbubbleConfig'
);
?>
<div style="padding:16px">
   <iframe
      src="/helpbubble-api/config.php"
      style="width:100%; height:720px; border:0; border-radius:8px; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.08);"
      title="HelpBubble — Configuración">
   </iframe>
</div>
<?php
Html::footer();
