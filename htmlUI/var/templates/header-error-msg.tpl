{* <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">  *}
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="de" lang="de">
<html>

<head>
    <title>Campcaster</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

    <link href="styles_campcaster.css" rel="stylesheet" type="text/css" />
    <link href="assets/plupload/plupload.queue.css" rel="stylesheet" type="text/css" />
    <link href="css/playlist.css" rel="stylesheet" type="text/css" />

	<script type="text/javascript" src="assets/jquery-1.3.2.min.js"></script>
	<script type="text/javascript" src="assets/jquery-ui-1.7.3.custom.min.js"></script>

	<script type="text/javascript" src="assets/plupload/plupload.full.min.js"></script>
	<script type="text/javascript" src="assets/plupload/jquery.plupload.queue.min.js"></script>
	<script type="text/javascript" src="assets/qtip/jquery.qtip.min.js"></script>

    {include file="script/basics.js.tpl"}
    {include file="script/contextmenu.js.tpl"}
    {include file="script/collector.js.tpl"}
    {include file="script/alttext.js.tpl"}

    <script type="text/javascript" src="js/playlist.js"></script>

</head>

<body>
    {if $USER_ERROR neq ""}
      <script type="text/javascript">
      CC_USER_ERROR = '{$USER_ERROR}';
      {literal}
      $(document).ready(function() {
        var $dialog = $('<div></div>')
          .html(CC_USER_ERROR)
          .dialog({
            autoOpen: false,
            title: 'Error'
          });

        $('#opener').click(function() {
          $dialog.dialog('open');
          // prevent the default action, e.g., following a link
          return false;
        });
      });
      {/literal}
      </script>
    {/if}

    <div class="container">
