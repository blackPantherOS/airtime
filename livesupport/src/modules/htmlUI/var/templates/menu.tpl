{literal}
<script type='text/javascript'><!--//--><![CDATA[//><!--
sfHover = function() {
    var sfEls = document.getElementById("nav").getElementsByTagName("LI");
    for (var i=0; i<sfEls.length; i++) {
        sfEls[i].onmouseover=function() {
            this.className+=" sfhover";
        }
        sfEls[i].onmouseout=function() {
            this.className=this.className.replace(new RegExp(" sfhover\\b"), "");
        }
    }
}
if (window.attachEvent) window.attachEvent("onload", sfHover);
//--><!]]></script>
{/literal}

<div class="container_nav">
{if $USER.userid}
    <ul id="nav">
        <li class="nav-main"><a href="{$UI_BROWSER}?folderId={$START.fid}&act=addFileData">##Add Audio##</a>
            <ul>
                <li><a href="{$UI_BROWSER}?folderId={$START.fid}&act=addFileData">##Audioclip##</a></li>
                {* <li><a href="{$UI_BROWSER}?folderId={$START.fid}&act=addWebstreamData">##Webstream##</a></li> *}
            </ul>
        </li>
        <li class="nav-main"><a>##Media Library##</a>
            <ul>
                <li><a href="{$UI_BROWSER}?id={$START.id}&act=BROWSE">##Browse##</a></li>
                <li><a href="{$UI_BROWSER}?id={$START.id}&act=SEARCH">##Search##</a></li>
            </ul>
        </li>
        <li class="nav-main"><a>##Playlists##</a>
            <ul>
                {if $PL->getActiveArr()}
                    <li><a href="{$UI_BROWSER}?id={$START.fid}&act=PL.simpleManagement">##Edit Playlist##</a></li>
                {else}
                    {if $PL->reportLookedPL()}
                        <li><a onClick="hpopup('{$UI_HANDLER}?act=PL.unlook')">##Open last Playlist##</a></li>
                    {else}
                        <li><a onClick="hpopup('{$UI_HANDLER}?act=PL.create')">##New empty Playlist##</a></li>
                    {/if}
                {/if}
                <li><a href="{$UI_BROWSER}?act=PL.import">##Import Playlist##</a></li>
            </ul>
        </li>
        <li class="nav-main"><a href="{$UI_BROWSER}?act=SCHEDULER">##Scheduler##</a>
            <ul>
                <li><a href="javascript: hpopup('{$UI_HANDLER}?act=SCHEDULER.set&view=month');       location.href='{$UI_BROWSER}?act=SCHEDULER'">##Month##</a></li>
                <li><a href="javascript: hpopup('{$UI_HANDLER}?act=SCHEDULER.set&view=week');        location.href='{$UI_BROWSER}?act=SCHEDULER'">##Week##</a></li>
                <li><a href="javascript: hpopup('{$UI_HANDLER}?act=SCHEDULER.set&view=day');         location.href='{$UI_BROWSER}?act=SCHEDULER'">##Day##</a></li>
                <li><a href="javascript: hpopup('{$UI_HANDLER}?act=SCHEDULER.set&view=day&today=1'); location.href='{$UI_BROWSER}?act=SCHEDULER'">##Today##</a></li>
                {if $SUBJECTS->Base->gb->checkPerm($SUBJECTS->Base->userid, 'schedulerStatus')}
                    <li><a href="javascript: hpopup('{$UI_HANDLER}?act=SCHEDULER.set&view=status');  location.href='{$UI_BROWSER}?act=SCHEDULER'">##Status##</a></li>
                {/if}
            </ul>
        </li>

        <li class="nav-main"><a>##Preferences##</a>
            <ul>
            {if $SUBJECTS->isMemberOf('StationPrefs')}
                <li><a href="{$UI_BROWSER}?act=changeStationPrefs">##Station Settings##</a></li>
            {/if}
            {if $SUBJECTS->isMemberOf('Subjects')}
                <li><a href="{$UI_BROWSER}?act=SUBJECTS">##User/Groups##</a></li>
            {else}
                <li><a href="{$UI_BROWSER}?act=SUBJECTS.chgPasswd&id={$USER.userid}">##Change Password##</a></li>
            {/if}
            {if $SUBJECTS->isMemberOf('Admin')}
                <li><a href="{$UI_BROWSER}?act=fileList&id={$START.fid}">##File List##</a></li>
            {/if}
            {if $SUBJECTS->isMemberOf('Backup')}
                <li><a href="{$UI_BROWSER}?act=BACKUP">##Database Backup##</a></li>
            {/if}
            {if $SUBJECTS->isMemberOf('Restore')}
                <li><a href="{$UI_BROWSER}?act=RESTORE">##Database Restore##</a></li>
            {/if}
            </ul>
        </li>

        <li><a href="" onclick="window.open('{$UI_BROWSER}?popup[]=help', 'help', 'scrollbars=yes,resizable=yes,width=500,height=800')">##Help##</a></li>
    </ul>

{/if}
{include file='userinfo.tpl'}    
 </div>