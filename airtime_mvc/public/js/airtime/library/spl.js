//--------------------------------------------------------------------------------------------------------------------------------
// Playlist Functions
//--------------------------------------------------------------------------------------------------------------------------------

var AIRTIME = (function(AIRTIME){
	
	AIRTIME.playlist = {};
	
	var mod = AIRTIME.playlist;
	
	function isTimeValid(time) {
		var regExpr = new RegExp("^\\d{2}[:]\\d{2}[:]\\d{2}([.]\\d{1,6})?$");
		
		return regExpr.test(time);
	}
	
	function isFadeValid(fade) {
        var regExpr = new RegExp("^\\d{1}(\\d{1})?([.]\\d{1,6})?$");

        return regExpr.test(fade);
	}
	
	function playlistError(json) {
		alert(json.error);
		openPlaylist(json);
	}
	
	function stopAudioPreview() {
		// stop any preview playing
		$('#jquery_jplayer_1').jPlayer('stop');
	}
	
	function highlightActive(el) {

		$(el).addClass("ui-state-active");
	}

	function unHighlightActive(el) {

		$(el).removeClass("ui-state-active");
	}

	function showError(el, error) {
	    $(el).parent().next()
	        .empty()
	        .append(error)
	        .show();
	}

	function hideError(el) {
	     $(el).parent().next()
	        .empty()
	        .hide();
	}

	function changeCueIn(event) {
	    event.stopPropagation();

		var span = $(this),
			id = span.parent().attr("id").split("_").pop(),
			url = "/Playlist/set-cue",
			cueIn = $.trim(span.text()),
			li = span.parents("li"),
			unqid = li.attr("unqid"),
			lastMod = getModified();

		if (!isTimeValid(cueIn)){
	        showError(span, "please put in a time '00:00:00 (.000000)'");
	        return;
		}

		$.post(url, 
			{format: "json", cueIn: cueIn, id: id, modified: lastMod}, 
			function(json){
		   
				if (json.error !== undefined){
	            	playlistError(json);
	            	return;
                }
		        if (json.cue_error !== undefined) {
		            showError(span, json.cue_error);
					return;
				}
		        
		        setPlaylistContent(json);
		        
		        li = $('#side_playlist li[unqid='+unqid+']');
		        li.find(".cue-edit").toggle();
		    	highlightActive(li);
		    	highlightActive(li.find('.spl_cue'));
			});
	}

	function changeCueOut(event) {
	    event.stopPropagation();

		var span = $(this),
			id = span.parent().attr("id").split("_").pop(),
			url = "/Playlist/set-cue",
			cueOut = $.trim(span.text()),
			li = span.parents("li"),
			unqid = li.attr("unqid"),
			lastMod = getModified();

		if (!isTimeValid(cueOut)){
	        showError(span, "please put in a time '00:00:00 (.000000)'");
			return;
		}

		$.post(url, 
			{format: "json", cueOut: cueOut, id: id, modified: lastMod}, 
			function(json){
		   
				if (json.error !== undefined){
	            	playlistError(json);
	            	return;
                }
				if (json.cue_error !== undefined) {
		            showError(span, json.cue_error);
					return;
				}
	
				setPlaylistContent(json);
		        
		        li = $('#side_playlist li[unqid='+unqid+']');
		        li.find(".cue-edit").toggle();
		    	highlightActive(li);
		    	highlightActive(li.find('.spl_cue'));
			});
	}

	function changeFadeIn(event) {
	    event.stopPropagation();

		var span = $(this),
			id = span.parent().attr("id").split("_").pop(),
			url = "/Playlist/set-fade",
			fadeIn = $.trim(span.text()),
			li = span.parents("li"),
			unqid = li.attr("unqid"),
			lastMod = getModified();

		if (!isFadeValid(fadeIn)){
	        showError(span, "please put in a time in seconds '00 (.000000)'");
			return;
		}

		$.post(url, 
			{format: "json", fadeIn: fadeIn, id: id, modified: lastMod}, 
			function(json){
			
				if (json.error !== undefined){
	            	playlistError(json);
	            	return;
                }
				if (json.fade_error !== undefined) {
					showError(span, json.fade_error);
					return;
				}
	
				setPlaylistContent(json);
		        
		        li = $('#side_playlist li[unqid='+unqid+']');
		        li.find('.crossfade').toggle();
		        highlightActive(li.find('.spl_fade_control'));
			});
	}

	function changeFadeOut(event) {
	    event.stopPropagation();

		var span = $(this),
			id = span.parent().attr("id").split("_").pop(),
			url = "/Playlist/set-fade",
			fadeOut = $.trim(span.text()),
			li = span.parents("li"),
			unqid = li.attr("unqid"),
			lastMod = getModified();

		if (!isFadeValid(fadeOut)){
	        showError(span, "please put in a time in seconds '00 (.000000)'");
			return;
		}

		$.post(url, 
			{format: "json", fadeOut: fadeOut, id: id, modified: lastMod}, 
			function(json){
			
				if (json.error !== undefined){
	            	playlistError(json);
	            	return;
                }
				if (json.fade_error !== undefined) {
		            showError(span, json.fade_error);
					return;
				}
	
				setPlaylistContent(json);
		        
		        li = $('#side_playlist li[unqid='+unqid+']');
		        li.find('.crossfade').toggle();
		        highlightActive(li.find('.spl_fade_control'));
			});
	}

	function submitOnEnter(event) {
		//enter was pressed
		if(event.keyCode === 13) {
	        event.preventDefault();
			$(this).blur();
		}
	}

	function openFadeEditor(event) {
		var li;
		
		event.stopPropagation();  

	    li = $(this).parents("li");
	    li.find(".crossfade").toggle();

		if ($(this).hasClass("ui-state-active")) {
			unHighlightActive(this);
		}
		else {
			highlightActive(this);
		}
	}

	function openCueEditor(event) {
		var li, icon;
		
		event.stopPropagation();

		icon = $(this);
		li = $(this).parents("li"); 
	    li.find(".cue-edit").toggle();

		if (li.hasClass("ui-state-active")) {
			unHighlightActive(li);
			unHighlightActive(icon);
		}
		else {
			highlightActive(li);
			highlightActive(icon);
		}
	}
    
	function editName() {
	    var nameElement = $(this),
	    	lastMod = getModified();

    	url = '/Playlist/set-playlist-name';

	    $.post(url, 
	    	{format: "json", name: nameElement.text(), modified: lastMod}, 
	    	function(json){
	    	
	            if (json.error !== undefined) {
	            	playlistError(json);
	            }
	            else {
	            	setModified(json.modified);
	                nameElement.text(json.playlistName);
	                redrawLib();
	            }
	        });
	}
		
	function redrawLib() {
	    var dt = $("#library_display").dataTable(),
	    	oLibTT = TableTools.fnGetInstance('library_display');
	    
	    oLibTT.fnSelectNone();
	    dt.fnStandingRedraw();
	}
	
	function setPlaylistContent(json) {
		
		$('#spl_name > a')
			.empty()
			.append(json.name);
		$('#spl_length')
			.empty()
			.append(json.length);
	    $('#fieldset-metadate_change textarea')
	        .empty()
	        .val(json.description);
		$('#spl_sortable')
			.empty()
			.append(json.html);
		
		setModified(json.modified);
		redrawLib();
	}
	
	function getId() {
		return parseInt($("#pl_id").val(), 10);
	}
	
	function getModified() {
		return parseInt($("#pl_lastMod").val(), 10);
	}
	
	function setModified(modified) {
		$("#pl_lastMod").val(modified);
	}
	
	function openPlaylist(json) {
		
		$("#side_playlist")
			.empty()
			.append(json.html);
		
		setUpPlaylist();
	}
	
	//sets events dynamically for playlist entries (each row in the playlist)
	function setPlaylistEntryEvents(el) {
		
		$(el).delegate("#spl_sortable .ui-icon-closethick", 
				{"click": function(ev){
					var id;
					id = parseInt($(this).attr("id").split("_").pop(), 10);
					AIRTIME.playlist.fnDeleteItems([id]);
				}});

		$(el).delegate(".spl_fade_control", 
	    		{"click": openFadeEditor});
		
		$(el).delegate(".spl_cue", 
				{"click": openCueEditor});

        //add the play function to the play icon
        $(el).delegate(".big_play",
            {"click": openAudioPreview});
	}
	
	//sets events dynamically for the cue editor.
	function setCueEvents(el) {
	
	    $(el).delegate(".spl_cue_in span", 
	    		{"focusout": changeCueIn, 
	    		"keydown": submitOnEnter});
	    
	    $(el).delegate(".spl_cue_out span", 
	    		{"focusout": changeCueOut, 
	    		"keydown": submitOnEnter});
	}
	
	//sets events dynamically for the fade editor.
	function setFadeEvents(el) {
	
	    $(el).delegate(".spl_fade_in span", 
	    		{"focusout": changeFadeIn, 
	    		"keydown": submitOnEnter});
	    
	    $(el).delegate(".spl_fade_out span", 
	    		{"focusout": changeFadeOut, 
	    		"keydown": submitOnEnter});
	}
	
	function initialEvents() {	
		var playlist = $("#side_playlist"),
			cachedDescription;
		
		
		//main playlist fades events
		playlist.on("click", "#spl_crossfade", function() {
	    	var lastMod = getModified();

	        if ($(this).hasClass("ui-state-active")) {
	            $(this).removeClass("ui-state-active");
	            playlist.find("#crossfade_main").hide();
	        }
	        else {
	            $(this).addClass("ui-state-active");

	            var url = '/Playlist/get-playlist-fades';

		        $.get(url, 
		        	{format: "json", modified: lastMod}, 
		        	function(json){
			            if (json.error !== undefined){
			            	playlistError(json);
		                }
			            else {
				            playlist.find("span.spl_main_fade_in")
			                    .empty()
			                    .append(json.fadeIn);
				            
				            playlist.find("span.spl_main_fade_out")
			                    .empty()
			                    .append(json.fadeOut);
		
				            playlist.find("#crossfade_main").show();
			            }
		            });
	        }
	    });
		
		playlist.on("blur", "span.spl_main_fade_in", function(event){
	        event.stopPropagation();

		    var url = "/Playlist/set-playlist-fades",
			    span = $(this),
			    fadeIn = $.trim(span.text()), 
			    lastMod = getModified();
		    
		    if (!isFadeValid(fadeIn)){
	            showError(span, "please put in a time in seconds '00 (.000000)'");
			    return;
		    }

		    $.post(url, 
	    		{format: "json", fadeIn: fadeIn, modified: lastMod}, 
	    		function(json){       
		            hideError(span);
		            if (json.modified !== undefined) {
		            	setModified(json.modified);
		            }
			    });
	    });

		playlist.on("blur", "span.spl_main_fade_out", function(event){
	        event.stopPropagation();

		    var url = "/Playlist/set-playlist-fades",
		    	span = $(this),
		    	fadeOut = $.trim(span.text()), 
		    	lastMod = getModified();

		    if (!isFadeValid(fadeOut)){
	            showError(span, "please put in a time in seconds '00 (.000000)'");
			    return;
		    }

		    $.post(url, 
		    	{format: "json", fadeOut: fadeOut, modified: lastMod}, 
		    	function(json){
		            hideError(span);
		            if (json.modified !== undefined) {
		            	setModified(json.modified);
		            }
			    });
	    });

		playlist.on("keydown", "span.spl_main_fade_in, span.spl_main_fade_out", submitOnEnter);

		playlist.on("click", "#crossfade_main > .ui-icon-closethick", function(){
			playlist.find("#spl_crossfade").removeClass("ui-state-active");
			playlist.find("#crossfade_main").hide();
	    });
		//end main playlist fades.

		//edit playlist name event
		playlist.on("keydown", "#playlist_name_display", submitOnEnter);
		playlist.on("blur", "#playlist_name_display", editName);
		
		//edit playlist description events
		playlist.on("click", "fieldset", function(){
	        var $fs = $(this);

	        if ($fs.hasClass("closed")) {
	        	cachedDescription = $fs.find("textarea").val();
	        	$fs.removeClass("closed");
	        }
	        else {
	        	$fs.addClass("closed");
	        }
	    });
		
		playlist.on("click", "#description_save", function(){
	        var textarea = playlist.find("#fieldset-metadate_change textarea"),
	        	description = textarea.val(),
	        	url,
	        	lastMod = getModified();;
	        
	        url = '/Playlist/set-playlist-description';

	        $.post(url, 
        		{format: "json", description: description, modified: lastMod}, 
        		function(json){
		            if (json.error !== undefined){
		            	playlistError(json);
		            }
		            else{
		            	setModified(json.modified);
		                textarea.val(json.description);
		                playlist.find("#fieldset-metadate_change").addClass("closed");
			            redrawLib();
		            }      
		        });
	    });

		playlist.on("click", "#description_cancel", function(){
	        var textarea = playlist.find("#fieldset-metadate_change textarea");
	        
	        textarea.val(cachedDescription);   
	        playlist.find("#fieldset-metadate_change").addClass("closed");
	    });
		//end edit playlist description events.
		
	}
	
	function setUpPlaylist(playlist) {
		var playlist = $("#side_playlist"),
			sortableConf;
		
		sortableConf = (function(){
			var aReceiveItems,
				html,
				fnReceive,
				fnUpdate;		
			
			fnReceive = function(event, ui) {
				var aItems = [],
					aSelected,
					oLibTT = TableTools.fnGetInstance('library_display'),
					i,
					length;
				
				//filter out anything that isn't an audiofile.
				aSelected = oLibTT.fnGetSelectedData();
				//if nothing is checked select the dragged item.
			    if (aSelected.length === 0) {
			    	aSelected.push(ui.item.data("aData"));
			    }
			    
				for (i = 0, length = aSelected.length; i < length; i++) {
					if (aSelected[i].ftype === "audioclip") {
						aItems.push(aSelected[i].id);
					}
				}
	
			    aReceiveItems = aItems;
				html = ui.helper.html();
			};
			
			fnUpdate = function(event, ui) {
				var prev,
					aItems = [],
					iAfter,
					sAddType;
				
				prev = ui.item.prev();
				if (prev.hasClass("spl_empty") || prev.length === 0) {
					iAfter = undefined;
					sAddType = 'before';
				}
				else {
					iAfter = parseInt(prev.attr("id").split("_").pop(), 10);
					sAddType = 'after';
				}
				
				//item was dragged in from library datatable
				if (aReceiveItems !== undefined) {
					
					playlist.find("tr.ui-draggable")
						.after(html)
						.empty();
					
					aItems = aReceiveItems;
					aReceiveItems = undefined;
					
					AIRTIME.playlist.fnAddItems(aItems, iAfter, sAddType);
				}
				//item was reordered.
				else {
					aItems.push(parseInt(ui.item.attr("id").split("_").pop(), 10));
					AIRTIME.playlist.fnMoveItems(aItems, iAfter);
				}
			};
			
			return {
				items: 'li',
				//hack taken from
				//http://stackoverflow.com/questions/2150002/jquery-ui-sortable-how-can-i-change-the-appearance-of-the-placeholder-object
				placeholder: {
			        element: function(currentItem) {
						
			            return $('<li class="placeholder ui-state-highlight"></li>')[0];
			        },
			        update: function(container, p) {
			            return;
			        }
			    },
				forcePlaceholderSize: true,
				handle: 'div.list-item-container',
				start: function(event, ui) {
					ui.placeholder.height(56);
				},
				receive: fnReceive,
				update: fnUpdate
			};
		}());

	    playlist.find("#spl_sortable").sortable(sortableConf);
	}
	
	mod.fnNew = function() {
		var url = '/Playlist/new';

		stopAudioPreview();
		
		$.post(url, 
			{format: "json"}, 
			function(json){
				openPlaylist(json);
				redrawLib();
			});
	};
	
	mod.fnEdit = function(id) {
		var url = '/Playlist/edit';;
		
		stopAudioPreview();	
		
		$.post(url, 
			{format: "json", id: id}, 
			function(json){
				openPlaylist(json);
			});
	};
	
	mod.fnDelete = function(plid) {
		var url, id, lastMod;
		
		stopAudioPreview();	
		id = (plid === undefined) ? getId() : plid;
		lastMod = getModified(); 
		url = '/Playlist/delete';

		$.post(url, 
			{format: "json", ids: id, modified: lastMod}, 
			function(json){
				openPlaylist(json);
				redrawLib();
			});
	};
	
	function playlistRequest(sUrl, oData) {
		var lastMod = getModified(),
			plContent = $("#side_playlist"),
			offset = plContent.offset(),
			plHeight = plContent.height(),
			plWidth = plContent.width(),
			overlay,
			loading;
		
		oData["modified"] = lastMod;
		oData["format"] = "json";
		

		overlay = $("<div />", {
    		"class": "pl-overlay ui-widget-content",
    		"css": {
    			"position": "absolute",
    			"top": offset.top,
    			"left": offset.left,
    			"height": plHeight + 16,
    			"width": plWidth + 16
    		}
    	}).click(function(){
    		return false;
    	});
		
		loading = $("<div />", {
    		"class": "pl-loading",
    		"css": {
    			"position": "absolute",
    			"top": offset.top + plHeight/2 - 32 - 8,
    			"left": offset.left + plWidth/2 - 32 -8,
    			"height": 64,
    			"width": 64
    		}
    	});
		
		$("body")
			.append(overlay)
			.append(loading);

		$.post(
			sUrl, 
			oData, 
			function(json){	
					
				if (json.error !== undefined) {
					playlistError(json);
				}
				else {
					setPlaylistContent(json);
				}
				
				loading.remove();
				overlay.remove();
			}
		);
	}
	
	mod.fnAddItems = function(aItems, iAfter, sAddType) {
		var sUrl = "/playlist/add-items";
			oData = {"ids": aItems, "afterItem": iAfter, "type": sAddType};
		
		playlistRequest(sUrl, oData);
	};
	
	mod.fnMoveItems = function(aIds, iAfter) {
		var sUrl = "/playlist/move-items",
			oData = {"ids": aIds, "afterItem": iAfter};
		
		playlistRequest(sUrl, oData);
	};
	
	mod.fnDeleteItems = function(aItems) {
		var sUrl = "/playlist/delete-items",
			oData = {"ids": aItems};
		
		playlistRequest(sUrl, oData);
	};
	
	mod.init = function() {
		var playlist = $("#side_playlist");
		
		$(playlist).delegate("#spl_new", 
	    		{"click": AIRTIME.playlist.fnNew});

		$(playlist).delegate("#spl_delete", {"click": function(ev){
			AIRTIME.playlist.fnDelete();
		}});
		
		setPlaylistEntryEvents(playlist);
		setCueEvents(playlist);
		setFadeEvents(playlist);
		
		initialEvents();
		setUpPlaylist(playlist);
	};
	
	return AIRTIME;
	
}(AIRTIME || {}));


$(document).ready(function() {
	
	var viewport = AIRTIME.utilities.findViewportDimensions(),
		lib = $("#library_content"),
		pl = $("#side_playlist"),
		widgetHeight = viewport.height - 185,
		width = Math.floor(viewport.width - 110);

	lib.height(widgetHeight)
		.width(Math.floor(width * 0.55));
		
	pl.height(widgetHeight)
		.width(Math.floor(width * 0.45));

	AIRTIME.library.libraryInit();
	AIRTIME.playlist.init();
	
	pl.find(".ui-icon-alert").qtip({
        content: {
            text: "File does not exist on disk..."
        },
        position:{
            adjust: {
            resize: true,
            method: "flip flip"
            },
            at: "right center",
            my: "left top",
            viewport: $(window)
        },
        style: {
            classes: "ui-tooltip-dark"
        },
        show: 'mouseover',
        hide: 'mouseout'
    });
});
