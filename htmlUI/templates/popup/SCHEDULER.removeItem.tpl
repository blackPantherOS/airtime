{include file="popup/header.tpl"}

<center>
{tra str='Are you sure you want to remove "$1"?' 1=$playlistName}
<br><br>
<input type="button" class="button" onClick="window.close()" value="Cancel">
<input type="button" class="button" onClick="location.href='{$UI_HANDLER}?act=SCHEDULER.removeItem&scheduleId={$_REQUEST.scheduleId}'" value="OK">
</center>

</body>
</html>
