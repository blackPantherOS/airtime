/*
function tpStartOnHourShowCallback(hour) {
    var tpEndHour = $('#show_builder_timepicker_end').timepicker('getHour');
    
    // Check if proposed hour is prior or equal to selected end time hour
    if (hour <= tpEndHour) { return true; }
    // if hour did not match, it can not be selected
    return false;
}

function tpStartOnMinuteShowCallback(hour, minute) {
    var tpEndHour = $('#show_builder_timepicker_end').timepicker('getHour'),
    	tpEndMinute = $('#show_builder_timepicker_end').timepicker('getMinute');
    
    // Check if proposed hour is prior to selected end time hour
    if (hour < tpEndHour) { return true; }
    // Check if proposed hour is equal to selected end time hour and minutes is prior
    if ( (hour == tpEndHour) && (minute < tpEndMinute) ) { return true; }
    // if minute did not match, it can not be selected
    return false;
}

function tpEndOnHourShowCallback(hour) {
    var tpStartHour = $('#show_builder_timepicker_start').timepicker('getHour');
    
    // Check if proposed hour is after or equal to selected start time hour
    if (hour >= tpStartHour) { return true; }
    // if hour did not match, it can not be selected
    return false;
}

function tpEndOnMinuteShowCallback(hour, minute) {
	var tpStartHour = $('#show_builder_timepicker_start').timepicker('getHour'),
    	tpStartMinute = $('#show_builder_timepicker_start').timepicker('getMinute');
	
    // Check if proposed hour is after selected start time hour
    if (hour > tpStartHour) { return true; }
    // Check if proposed hour is equal to selected start time hour and minutes is after
    if ( (hour == tpStartHour) && (minute > tpStartMinute) ) { return true; }
    // if minute did not match, it can not be selected
    return false;
}
*/

/*
 * Get the schedule range start in unix timestamp form (in seconds).
 * defaults to NOW if nothing is selected.
 * 
 * @param String sDatePickerId
 * 
 * @param String sTimePickerId
 * 
 * @return Number iTime
 */
function fnGetUIPickerUnixTimestamp(sDatePickerId, sTimePickerId) {
	var oDate, 
		oTimePicker = $( sTimePickerId ),
		iTime,
		iHour,
		iMin,
		iServerOffset,
		iClientOffset;
	
	oDate = $( sDatePickerId ).datepicker( "getDate" );
	
	//nothing has been selected from this datepicker.
	if (oDate === null) {
		oDate = new Date();
	}
	else {
		iHour = oTimePicker.timepicker('getHour');
		iMin = oTimePicker.timepicker('getMinute');
		
		oDate.setHours(iHour, iMin);
	}
	
	iTime = oDate.getTime(); //value is in millisec.
	iTime = Math.round(iTime / 1000);
	iServerOffset = serverTimezoneOffset;
	iClientOffset = oDate.getTimezoneOffset() * 60;//function returns minutes
	
	//adjust for the fact the the Date object is
	iTime = iTime + iServerOffset + iClientOffset;
	
	return iTime;
}
/*
 * Returns an object containing a unix timestamp in seconds for the start/end range
 * 
 * @return Object {"start", "end", "range"}
 */
function fnGetScheduleRange() {
	var iStart, 
		iEnd, 
		iRange,
		MIN_RANGE = 60*60*24;
	
	iStart = fnGetUIPickerUnixTimestamp("#show_builder_datepicker_start", "#show_builder_timepicker_start");
	iEnd = fnGetUIPickerUnixTimestamp("#show_builder_datepicker_end", "#show_builder_timepicker_end");
	
	iRange = iEnd - iStart;
	
	//return min range
	if (iRange < MIN_RANGE){
		iEnd = iStart + MIN_RANGE;
		iRange = MIN_RANGE;
	}
		
	return {
		start: iStart,
		end: iEnd,
		range: iRange
	};
}

var fnServerData = function fnServerData( sSource, aoData, fnCallback ) {
	aoData.push( { name: "format", value: "json"} );
	
	if (fnServerData.hasOwnProperty("start")) {
		aoData.push( { name: "start", value: fnServerData.start} );
	}
	if (fnServerData.hasOwnProperty("end")) {
		aoData.push( { name: "end", value: fnServerData.end} );
	}
	
	$.ajax( {
		"dataType": "json",
		"type": "GET",
		"url": sSource,
		"data": aoData,
		"success": fnCallback
	} );
};

var fnShowBuilderRowCallback = function ( nRow, aData, iDisplayIndex, iDisplayIndexFull ){
	var i,
		sSeparatorHTML,
		fnPrepareSeparatorRow;
	
	fnPrepareSeparatorRow = function(sRowContent, sClass) {
		var node;
		
		node = nRow.children[0];
		node.innerHTML = sRowContent;
		node.setAttribute('colspan',100);
		for (i = 1; i < nRow.children.length; i = i+1) {
			node = nRow.children[i];
			node.innerHTML = "";
			node.setAttribute("style", "display : none");
		}
		
		nRow.className = sClass;
	};
	
	if (aData.header === true) {
		sSeparatorHTML = '<span>'+aData.title+'</span><span>'+aData.starts+'</span><span>'+aData.ends+'</span>';
		fnPrepareSeparatorRow(sSeparatorHTML, "show-builder-header");
	}
	else if (aData.footer === true) {
		sSeparatorHTML = '<span>Show Footer</span>';
		fnPrepareSeparatorRow(sSeparatorHTML, "show-builder-footer");
	}
	
	return nRow;
};

$(document).ready(function() {
	var dTable,
		oBaseDatePickerSettings,
		oBaseTimePickerSettings;
	
	oBaseDatePickerSettings = {
		dateFormat: 'yy-mm-dd',
		onSelect: function(sDate, oDatePicker) {
			var oDate,
				dInput;
			
			dInput = $(this);			
			oDate = dInput.datepicker( "setDate", sDate );
		}
	};
	
	oBaseTimePickerSettings = {
		showPeriodLabels: false,
		showCloseButton: true,
		showLeadingZero: false,
		defaultTime: '0:00'
	};
	
	dTable = $('#show_builder_table').dataTable( {
		"aoColumns": [
		     /* hidden */ {"mDataProp": "instance", "bVisible": false, "sTitle": "hidden"},
		    /* instance */{"mDataProp": "instance", "sTitle": "si_id"},
            /* starts */{"mDataProp": "starts", "sTitle": "starts"},
            /* ends */{"mDataProp": "ends", "sTitle": "ends"},
            /* title */{"mDataProp": "title", "sTitle": "track_title"}
        ],
        
        "asStripClasses": [ 'odd' ],
        
        "bJQueryUI": true,
        "bSort": false,
        "bFilter": false,
        "bProcessing": true,
		"bServerSide": true,
		"bInfo": false,
        
		"fnServerData": fnServerData,
		"fnRowCallback": fnShowBuilderRowCallback,
		
		"oColVis": {
			"aiExclude": [ 0 ]
		},
		
        // R = ColReorder, C = ColVis, see datatables doc for others
        "sDom": 'Rr<"H"C>t<"F">',
        
        //options for infinite scrolling
        //"bScrollInfinite": true,
        //"bScrollCollapse": true,
        "sScrollY": "400px",
        
        "sAjaxDataProp": "schedule",
		"sAjaxSource": "/showbuilder/builder-feed"
		
	});
	
	$( "#show_builder_datepicker_start" ).datepicker(oBaseDatePickerSettings);
	
	$( "#show_builder_timepicker_start" ).timepicker(oBaseTimePickerSettings);
	
	$( "#show_builder_datepicker_end" ).datepicker(oBaseDatePickerSettings);
	
	$( "#show_builder_timepicker_end" ).timepicker(oBaseTimePickerSettings);
	
	$( "#show_builder_timerange_button" ).click(function(ev){
		var oTable, 
			oSettings,
			oRange;
		
		oRange = fnGetScheduleRange();
		
		oTable = $('#show_builder_table').dataTable({"bRetrieve": true});
	    oSettings = oTable.fnSettings();
	    oSettings.fnServerData.start = oRange.start;
	    oSettings.fnServerData.end = oRange.end;
		
		oTable.fnDraw();
	});
	
	$( "#show_builder_table" ).sortable({
		placeholder: "placeholder show-builder-placeholder",
		items: 'tr',
		cancel: ".show-builder-header .show-builder-footer",
		receive: function(event, ui) {
			var x;
		}
	});
	
});
