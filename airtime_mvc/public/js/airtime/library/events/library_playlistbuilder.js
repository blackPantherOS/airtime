var AIRTIME = (function(AIRTIME){
	var mod;
	
	if (AIRTIME.library === undefined) {
		AIRTIME.library = {};
	}
	
	AIRTIME.library.events = {};
	mod = AIRTIME.library.events;
	
	mod.fnRowCallback = function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
		var $nRow = $(nRow);
		
		$nRow.attr("id", aData["tr_id"])
	    	.data("aData", aData)
	    	.data("screen", "playlist");
	};
	
	mod.fnDrawCallback = function() {
		
		$('#library_display tr[id ^= "au"]').draggable({
			helper: function(){
			    var selected = $('#library_display tr:not(:first) input:checked').parents('tr[id^="au"]'),
			    	container,
			    	message,
			    	li = $("#side_playlist ul li:first"),
			    	width = li.width(),
			    	height = li.height();
			    
			    if (selected.length === 0) {
			    	selected = $(this);
			    }
			    
			    if (selected.length === 1) {
			    	message = "Moving "+selected.length+" Item."
			    }
			    else {
			    	message = "Moving "+selected.length+" Items."
			    }
			    
			    container = $('<div class="helper"/>')
			    	.append("<li/>")
			    	.find("li")
				    	.addClass("ui-state-default")
			    		.append("<div/>")
			    		.find("div")
			    			.addClass("list-item-container")
			    			.append(message)
			    			.end()
				    	.width(width)
				    	.height(height)
				    	.end();
			        
			    return container; 
		    },
			cursor: 'pointer',
			connectToSortable: '#spl_sortable'
		});
	};
	
	/*
	 * @param oTable the datatables instance for the library.
	 */
	mod.setupLibraryToolbar = function( oLibTable ) {
		var aButtons,
			fnResetCol,
			fnAddSelectedItems;
		
		fnAddSelectedItems = function() {
			var oLibTT = TableTools.fnGetInstance('library_display'),
				aData = oLibTT.fnGetSelectedData(),
				i,
				temp,
				length,
				aMediaIds = [];
			
			//process selected files/playlists.
			for (i = 0, length = aData.length; i < length; i++) {
				temp = aData[i];
				if (temp.ftype === "audioclip") {
					aMediaIds.push(temp.id);
				}
			}
		
			AIRTIME.playlist.fnAddItems(aMediaIds, undefined, 'after');
		};
			
		//[0] = button text
		//[1] = id 
		//[2] = enabled
		//[3] = click event
		aButtons = [["Delete", "library_group_delete", true, AIRTIME.library.fnDeleteSelectedItems], 
	                ["Add", "library_group_add", true, fnAddSelectedItems]];
		
		addToolBarButtonsLibrary(aButtons);
	};
	

	return AIRTIME;
	
}(AIRTIME || {}));
