var AIRTIME = (function(AIRTIME) {
    var mod;
    
    if (AIRTIME.history === undefined) {
        AIRTIME.history = {};
    }
    mod = AIRTIME.history;
    
    var $historyContentDiv;
    
    var oTableTools = {
        "sSwfPath": baseUrl+"js/datatables/plugin/TableTools-2.1.5/swf/copy_csv_xls_pdf.swf",
        "aButtons": [
             {
                 "sExtends": "copy",
                 "fnComplete": function(nButton, oConfig, oFlash, text) {
                     var lines = text.split('\n').length,
                         len = this.s.dt.nTFoot === null ? lines-1 : lines-2,
                         plural = (len==1) ? "" : "s";
                     alert(sprintf($.i18n._('Copied %s row%s to the clipboard'), len, plural));
                 },
                 //set because only the checkbox row is not sortable.
                 "mColumns": "sortable"
             },
             {
                 "sExtends": "csv",
                 "fnClick": setFlashFileName,
                 //set because only the checkbox row is not sortable.
                 "mColumns": "sortable"
             },
             {
                 "sExtends": "pdf",
                 "fnClick": setFlashFileName,
                 "sPdfOrientation": "landscape",
                 //set because only the checkbox row is not sortable.
                 "mColumns": "sortable"
             },
             {
                 "sExtends": "print",
                 "sInfo" : sprintf($.i18n._("%sPrint view%sPlease use your browser's print function to print this table. Press escape when finished."), "<h6>", "</h6><p>"),
                 //set because only the checkbox row is not sortable.
                 "mColumns": "sortable"
             }
         ]
    };
    
    var lengthMenu = [[10, 25, 50, 100, 500, -1], [10, 25, 50, 100, 500, $.i18n._("All")]];
    
    var sDom = 'l<"dt-process-rel"r><"H"T><"dataTables_scrolling"t><"F"ip>';
    
    var selectedLogItems = {};
    
    function getSelectedLogItems() {
    	var items = Object.keys(selectedLogItems);
    	
    	return items;
    }
    
    function addSelectedLogItem($el) {
    	var id;
    	
    	$el.addClass("his-selected");
    	id = $el.data("his-id");
    	selectedLogItems[id] = "";
    }
    
    function removeSelectedLogItem($el) {
    	var id;
    	
    	$el.removeClass("his-selected");
    	id = $el.data("his-id");
    	delete selectedLogItems[id];
    }
    
    function emptySelectedLogItems() {
    	var $inputs = $historyContentDiv.find(".his_checkbox").find("input");
    	
    	$inputs.prop('checked', false);
    	$inputs.parents("tr").removeClass("his-selected");
		
    	selectedLogItems = {};
    }
    
    function selectCurrentPage() {
    	var $inputs = $historyContentDiv.find(".his_checkbox").find("input"),
    		$tr, 
    		$input;
    	
    	$.each($inputs, function(index, input) {
    		$input = $(input);
    		$input.prop('checked', true);
    		$tr = $input.parents("tr");
    		addSelectedLogItem($tr);
		});
    }
    
    function deselectCurrentPage() {
    	var $inputs = $historyContentDiv.find(".his_checkbox").find("input"),
			$tr, 
			$input;
		
		$.each($inputs, function(index, input) {
			$input = $(input);
			$input.prop('checked', false);
			$tr = $input.parents("tr");
			removeSelectedLogItem($tr);
		});
    }
    
    function getFileName(ext){
        var filename = $("#his_date_start").val()+"_"+$("#his_time_start").val()+"m--"+$("#his_date_end").val()+"_"+$("#his_time_end").val()+"m";
        filename = filename.replace(/:/g,"h");
        
        if (ext == "pdf"){
            filename = filename+".pdf";
        }
        else {
            filename = filename+".csv";
        }
        return filename;
    }

    function setFlashFileName( nButton, oConfig, oFlash ) {
        var filename = getFileName(oConfig.sExtends);
        oFlash.setFileName( filename );
        
        if (oConfig.sExtends == "pdf") {
            this.fnSetText( oFlash,
                "title:"+ this.fnGetTitle(oConfig) +"\n"+
                "message:"+ oConfig.sPdfMessage +"\n"+
                "colWidth:"+ this.fnCalcColRatios(oConfig) +"\n"+
                "orientation:"+ oConfig.sPdfOrientation +"\n"+
                "size:"+ oConfig.sPdfSize +"\n"+
                "--/TableToolsOpts--\n" +
                this.fnGetTableData(oConfig));
        }
        else {
            this.fnSetText(oFlash, this.fnGetTableData(oConfig));
        }
    }
    
    /* This callback can be used for all history tables */
    function fnServerData( sSource, aoData, fnCallback ) {
    	
    	if (fnServerData.hasOwnProperty("start")) {
			aoData.push( { name: "start", value: fnServerData.start} );
		}
		if (fnServerData.hasOwnProperty("end")) {
			aoData.push( { name: "end", value: fnServerData.end} );
		}
       
        aoData.push( { name: "format", value: "json"} );
        
        $.ajax( {
            "dataType": 'json',
            "type": "GET",
            "url": sSource,
            "data": aoData,
            "success": fnCallback
        } );
    }
    
    function createToolbarButtons ($el) {
        var $menu = $("<div class='btn-toolbar' />");
        
        $menu.append("<div class='btn-group'>" +
            "<button class='btn btn-small dropdown-toggle' data-toggle='dropdown'>" +
                $.i18n._("Select")+" <span class='caret'></span>" +
            "</button>" +
            "<ul class='dropdown-menu'>" +
                "<li id='his-select-page'><a href='#'>"+$.i18n._("Select this page")+"</a></li>" +
                "<li id='his-dselect-page'><a href='#'>"+$.i18n._("Deselect this page")+"</a></li>" +
                "<li id='his-dselect-all'><a href='#'>"+$.i18n._("Deselect all")+"</a></li>" +
            "</ul>" +
        "</div>");
        
        $menu.append("<div class='btn-group'>" +
            "<button class='btn btn-small' id='his_create'>" +
                "<i class='icon-white icon-plus'></i>" +
                $.i18n._("Create Entry") +
            "</button>" +
        "</div>");
        
        $menu.append("<div class='btn-group'>" +
            "<button class='btn btn-small' id='his_trash'>" +
                "<i class='icon-white icon-trash'></i>" +
            "</button>" +
        "</div>");
                  
        $el.append($menu);
    }
    
    function aggregateHistoryTable() {
        var oTable,
        	$historyTableDiv = $historyContentDiv.find("#history_table_aggregate"),
        	columns,
        	fnRowCallback;

        fnRowCallback = function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
        	var editUrl = baseUrl+"playouthistory/edit-file-item/id/"+aData.file_id,
        		$nRow = $(nRow);
        		
        	$nRow.data('url-edit', editUrl);
        };
        
        columns = JSON.parse(localStorage.getItem('datatables-historyfile-aoColumns'));
        
        oTable = $historyTableDiv.dataTable( {
            
            "aoColumns": columns,
                          
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": baseUrl+"playouthistory/file-history-feed",
            "sAjaxDataProp": "history",
            "fnServerData": fnServerData,
            "fnRowCallback": fnRowCallback,
            "oLanguage": datatables_dict,
            "aLengthMenu": lengthMenu,
            "iDisplayLength": 25,
            "sPaginationType": "full_numbers",
            "bJQueryUI": true,
            "bAutoWidth": true,
            "sDom": sDom, 
            "oTableTools": oTableTools
        });
        oTable.fnSetFilteringDelay(350);
       
        return oTable;
    }
    
    function itemHistoryTable() {
        var oTable,
        	$historyTableDiv = $historyContentDiv.find("#history_table_list"),
        	$toolbar,
        	columns,
        	fnRowCallback,
        	booleans = {},
        	i, c;
        
        columns = JSON.parse(localStorage.getItem('datatables-historyitem-aoColumns'));
        
        for (i in columns) {
        	
        	c = columns[i];
        	if (c["sDataType"] === "boolean") {
        		booleans[c["mDataProp"]] = c["sTitle"];
        	}
        }

        fnRowCallback = function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
        	var editUrl = baseUrl+"playouthistory/edit-list-item/id/"+aData.history_id,
        		deleteUrl = baseUrl+"playouthistory/delete-list-item/id/"+aData.history_id,
        		emptyCheckBox = String.fromCharCode(parseInt(2610, 16)),
        		checkedCheckBox = String.fromCharCode(parseInt(2612, 16)),
        		b, 
        		text,
        		$nRow = $(nRow);
        	
        	 // add checkbox
            $nRow.find('td.his_checkbox').html("<input type='checkbox' name='cb_"+aData.history_id+"'>");
	
            $nRow.data('his-id', aData.history_id);
            $nRow.data('url-edit', editUrl);
            $nRow.data('url-delete', deleteUrl);
        	
        	for (b in booleans) {
            	
            	text = aData[b] ? checkedCheckBox : emptyCheckBox;
            	text = text + " " + booleans[b];
            	
            	$nRow.find(".his_"+b).html(text);
            }
        };
        	
        oTable = $historyTableDiv.dataTable( {
            
            "aoColumns": columns,             
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": baseUrl+"playouthistory/item-history-feed",
            "sAjaxDataProp": "history",
            "fnServerData": fnServerData,
            "fnRowCallback": fnRowCallback,
            "oLanguage": datatables_dict,
            "aLengthMenu": lengthMenu,
            "iDisplayLength": 25,
            "sPaginationType": "full_numbers",
            "bJQueryUI": true,
            "bAutoWidth": true,
            "sDom": sDom, 
            "oTableTools": oTableTools
        });
        oTable.fnSetFilteringDelay(350);
        
        $toolbar = $historyTableDiv.parents(".dataTables_wrapper").find(".fg-toolbar:first");
        createToolbarButtons($toolbar);
        
        $("#his-select-page").click(selectCurrentPage);
        $("#his-dselect-page").click(deselectCurrentPage);
        $("#his-dselect-all").click(emptySelectedLogItems);
        
        return oTable;
    }
    
    mod.onReady = function() {
    	
    	var oBaseDatePickerSettings,
    		oBaseTimePickerSettings,
    		oTableAgg,
    		oTableItem,
    		dateStartId = "#his_date_start",
    		timeStartId = "#his_time_start",
    		dateEndId = "#his_date_end",
    		timeEndId = "#his_time_end",
    		$hisDialogEl,
    		
    		tabsInit = [
    		    {
    		    	initialized: false,
    		    	initialize: function() {
    		    		oTableItem = itemHistoryTable();
    		    	}
    		    },
    		    {
    		    	initialized: false,
    		    	initialize: function() {
    		    		oTableAgg = aggregateHistoryTable();
    		    	}
    		    }
    		];
    	
    	
    	$historyContentDiv = $("#history_content");
    	
    	function redrawTables() {
    		oTableAgg && oTableAgg.fnDraw();
    		oTableItem && oTableItem.fnDraw();
    	}
    	
    	function removeHistoryDialog() {
    		$hisDialogEl.dialog("destroy");
        	$hisDialogEl.remove();
    	}
    	
    	function initializeDialog() {
    		var $startPicker = $hisDialogEl.find('#his_item_starts_datetimepicker'),
    			$endPicker = $hisDialogEl.find('#his_item_ends_datetimepicker');
    		
        	$startPicker.datetimepicker();

        	$endPicker.datetimepicker({
        		showTimeFirst: true
        	});
        	
        	$startPicker.on('changeDate', function(e) {
        		$endPicker.data('datetimepicker').setLocalDate(e.localDate);	
    		});
    	}
    	
    	function makeHistoryDialog(html) {
    		$hisDialogEl = $(html);
    		
    		$hisDialogEl.dialog({	       
    	        title: $.i18n._("Edit History Record"),
    	        modal: false,
    	        open: function( event, ui ) {
    	        	initializeDialog();	
    	        },
    	        close: function() {
    	        	removeHistoryDialog();
    	        }
    	    });
    	}
    	
    	/*
         * Icon hover states for search.
         */
    	$historyContentDiv.on("mouseenter", ".his-timerange .ui-button", function(ev) {
        	$(this).addClass("ui-state-hover"); 	
        });
    	$historyContentDiv.on("mouseleave", ".his-timerange .ui-button", function(ev) {
        	$(this).removeClass("ui-state-hover");
        });
    	
    	oBaseDatePickerSettings = {
    		dateFormat: 'yy-mm-dd',
            //i18n_months, i18n_days_short are in common.js
            monthNames: i18n_months,
            dayNamesMin: i18n_days_short,
    		onSelect: function(sDate, oDatePicker) {		
    			$(this).datepicker( "setDate", sDate );
    		}
    	};
    	
    	oBaseTimePickerSettings = {
    		showPeriodLabels: false,
    		showCloseButton: true,
            closeButtonText: $.i18n._("Done"),
    		showLeadingZero: false,
    		defaultTime: '0:00',
            hourText: $.i18n._("Hour"),
            minuteText: $.i18n._("Minute")
    	};

    	$historyContentDiv.find(dateStartId).datepicker(oBaseDatePickerSettings);
    	$historyContentDiv.find(timeStartId).timepicker(oBaseTimePickerSettings);
    	$historyContentDiv.find(dateEndId).datepicker(oBaseDatePickerSettings);
    	$historyContentDiv.find(timeEndId).timepicker(oBaseTimePickerSettings);
    	
    	$historyContentDiv.on("click", "#his_create", function(e) {
    		var url = baseUrl+"playouthistory/edit-list-item/format/json"	;
    		
    		e.preventDefault();
    		
    		$.get(url, function(json) {
    			
    			makeHistoryDialog(json.dialog);
    			
    		}, "json");
    	});
    	
    	$('body').on("click", ".his_file_cancel, .his_item_cancel", function(e) {
    		removeHistoryDialog();
    	});
    	
    	$('body').on("click", ".his_file_save", function(e) {
    		
    		e.preventDefault();
    		
    		var $form = $(this).parents("form");
    		var data = $form.serializeArray();
    		
    		var url = baseUrl+"Playouthistory/update-file-item/format/json";
    		
    		$.post(url, data, function(json) {
    			
    			//TODO put errors on form.
    			if (json.error !== undefined) {
    				//makeHistoryDialog(json.dialog);
    			}
    			else {
    				removeHistoryDialog();
    				redrawTables();
    			}
    		    	
    		}, "json");
    		
    	});
    	
    	$('body').on("click", ".his_item_save", function(e) {
    		
    		e.preventDefault();
    		
    		var $form = $(this).parents("form"),
    			data = $form.serializeArray(),
    			id = data[0].value,
    			createUrl = baseUrl+"Playouthistory/create-list-item/format/json",
    			updateUrl = baseUrl+"Playouthistory/update-list-item/format/json",
    			url;
    		
    		url = (id === "") ? createUrl : updateUrl;
    				
    		$.post(url, data, function(json) {
    			
    			if (json.form !== undefined) {
    				var $newForm = $(json.form);
    				$hisDialogEl.html($newForm.html());
    				initializeDialog();
    			}
    			else {
    				removeHistoryDialog();
    				redrawTables();
    			}
    		    	
    		}, "json");
    		
    	});
    	
    	
    	$historyContentDiv.on("click", ".his_checkbox input", function(e) {
    		var checked = e.currentTarget.checked,
    			$tr = $(e.currentTarget).parents("tr");
    		
    		if (checked) {
    			addSelectedLogItem($tr);
    		}
    		else {
    			removeSelectedLogItem($tr);
    		}
    	});
    	
    	$historyContentDiv.find("#his_submit").click(function(ev){
    		var fn,
    			oRange;
    		
    		oRange = AIRTIME.utilities.fnGetScheduleRange(dateStartId, timeStartId, dateEndId, timeEndId);
    		
    		fn = fnServerData;
    	    fn.start = oRange.start;
    	    fn.end = oRange.end;
    	    
    	    redrawTables();
    	});
    	
    	$historyContentDiv.on("click", "#his_trash", function(ev){
    		var items = getSelectedLogItems(),
    			url = baseUrl+"playouthistory/delete-list-items";
    		
    		$.post(url, {ids: items, format: "json"}, function(){
    			redrawTables();
    		});
    	});
    	
    	$historyContentDiv.find("#his-tabs").tabs({
    		show: function( event, ui ) {
				var tab = tabsInit[ui.index];
				
				if (!tab.initialized) {
					tab.initialize();
					tab.initialized = true;
				}
			}
    	});
    	
    	// begin context menu initialization.
        $.contextMenu({
            selector: '#history_content td:not(.his_checkbox)',
            trigger: "left",
            ignoreRightClick: true,
            
            build: function($el, e) {
                var items = {}, 
                	callback, 
                	$tr,
                	editUrl,
                	deleteUrl;
                
                $tr = $el.parents("tr");
                editUrl = $tr.data("url-edit");
                deleteUrl = $tr.data("url-delete");
                
                if (editUrl !== undefined) {
                	
                	callback = function() {
                    	$.post(editUrl, {format: "json"}, function(json) {
                			
                			makeHistoryDialog(json.dialog);
                			
                		}, "json");
                    };
                    
                    items["edit"] = {
                    	"name": $.i18n._("Edit"),
                    	"icon": "edit",
                    	"callback": callback
                    };
                }
                
                if (deleteUrl !== undefined) {
                	
                	callback = function() {
                    	var c = confirm("Delete this entry?");
                    	
                    	if (c) {
                    		$.post(deleteUrl, {format: "json"}, function(json) {
                    			redrawTables();
                    		});
                    	}	
                    };
                    
                    items["del"] = {
                    	"name": $.i18n._("Delete"),
                    	"icon": "delete",
                    	"callback": callback
                    };
                }
                
                return {
                    items: items
                };
            }
        });
    };
    
return AIRTIME;
    
}(AIRTIME || {}));

$(document).ready(AIRTIME.history.onReady);