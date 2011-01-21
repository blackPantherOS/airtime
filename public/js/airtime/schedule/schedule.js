/**
*
*	Schedule Dialog creation methods.
*
*/

function makeTimeStamp(date){
	var sy, sm, sd, h, m, s, timestamp;
	sy = date.getFullYear();
	sm = date.getMonth() + 1;
	sd = date.getDate();
	h = date.getHours();
	m = date.getMinutes();
	s = date.getSeconds();

	timestamp = sy+"-"+ sm +"-"+ sd +" "+ h +":"+ m +":"+ s;
	return timestamp;
}

//dateText mm-dd-yy
function startDpSelect(dateText, inst) {
	var time, date;

	time = dateText.split("-");
	date = new Date(time[0], time[1] - 1, time[2]);

	$("#end_date").datepicker("option", "minDate", date);
}

function endDpSelect(dateText, inst) {
	var time, date;

	time = dateText.split("-");
	date = new Date(time[0], time[1] - 1, time[2]);

	$("#start_date").datepicker( "option", "maxDate", date);
}

function createDateInput(el, onSelect) {
	var date;

	el.datepicker({
			minDate: new Date(),
			onSelect: onSelect,
			dateFormat: 'yy-mm-dd' 
		});

	date = $.datepicker.formatDate("yy-mm-dd", new Date());
	el.val(date);
}

function submitShow() {

	var formData, dialog;

	formData = $("#schedule_add_event_dialog").find("form").serializeArray();
	dialog = $(this);
	
	$.post("/Schedule/add-show-dialog/format/json", 
		formData,
		function(data){
			if(data.content) {
				dialog.find("form").remove();
				dialog.find("#show_overlap_error").empty();
				dialog.append(data.content);

				makeShowDialog(dialog, json);

				if(data.overlap) {
					var div, table, tr, days;
					div = dialog.find("#show_overlap_error");
					table = $('<table/>');
					days = $.datepicker.regional[''].dayNamesShort;

					$.each(data.overlap, function(i, val){
						tr = $("<tr/>");
						tr
							.append("<td>"+val.name+"</td>")
							.append("<td>"+days[val.day]+"</td>")
							.append("<td>"+val.start_time+"</td>")
							.append("<td>"+val.end_time+"</td>");

						table.append(tr);
					});
				
					div.append("<span>Cannot add show. New show overlaps the following shows:</span>");
					div.append(table);
					dialog.append(div);
				}
	
			}
			else {
				$("#schedule_calendar").fullCalendar( 'refetchEvents' );
				dialog.remove();
			}
		});
}

function closeDialog(event, ui) {
	$("#schedule_calendar").fullCalendar( 'refetchEvents' );
	$(this).remove();
}

function autoSelect(event, ui) {

	$("#hosts-"+ui.item.value).attr("checked", "checked");
	event.preventDefault();
}

function makeShowDialog(dialog, json) {

	dialog.append(json.content);
	dialog.find("#tabs").tabs();

	var start  = dialog.find("#add_show_start_date");
	var end  = dialog.find("#add_show_end_date");

	createDateInput(start, startDpSelect);
	createDateInput(end, endDpSelect);

	var auto = json.hosts.map(function(el) {
		return {value: el.id, label: el.login};
	});

	dialog.find("#add_show_hosts_autocomplete").autocomplete({
		source: auto,
		select: autoSelect
	});

	dialog.find("#schedule-show-style input").ColorPicker({
		onSubmit: function(hsb, hex, rgb, el) {
			$(el).val(hex);
			$(el).ColorPickerHide();
		},
		onBeforeShow: function () {
			$(this).ColorPickerSetColor(this.value);
		}
	});

	return dialog;
}

function openShowDialog() {
	var url;

	url = '/Schedule/add-show-dialog/format/json';

	$.get(url, function(json){

		var dialog;

		//main jqueryUI dialog
		dialog = $('<div id="schedule_add_event_dialog" />');
		makeShowDialog(dialog, json);

		dialog.dialog({
			autoOpen: false,
			title: 'Add Show',
			width: 1100,
			height: 500,
			modal: true,
			close: closeDialog,
			buttons: { "Cancel": closeDialog, "Ok": submitShow}
		});

		dialog.dialog('open');
	});
}

function setScheduleDialogHtml(json) {

	$("#schedule_playlist_choice")
		.empty()
		.append(json.choice)
		.find('li')
			.draggable({ 
				helper: 'clone' 
			});

	$("#schedule_playlist_chosen")
		.empty()
		.append(json.chosen);

	$("#show_time_filled").empty().append(json.timeFilled);
	$("#show_progressbar").progressbar( "value" , json.percentFilled );
}

function setScheduleDialogEvents(dialog) {

	dialog.find(".ui-icon-triangle-1-e").parent().click(function(){
		var span = $(this).find("span");

		if(span.hasClass("ui-icon-triangle-1-s")) {
			span
				.removeClass("ui-icon-triangle-1-s")
				.addClass("ui-icon ui-icon-triangle-1-e");

			$(this).parent().removeClass("ui-state-active ui-corner-top");
			$(this).parent().addClass("ui-corner-all");
			$(this).parent().parent().find(".group_list").hide();
		}
		else if(span.hasClass("ui-icon-triangle-1-e")) {
			span
				.removeClass("ui-icon-triangle-1-e")
				.addClass("ui-icon ui-icon-triangle-1-s");

			$(this).parent().addClass("ui-state-active ui-corner-top");
			$(this).parent().removeClass("ui-corner-all");
			$(this).parent().parent().find(".group_list").show();
		}
	});

	dialog.find(".ui-icon-close").parent().click(function(){
		var groupId, url;
		
		groupId = $(this).parent().parent().attr("id").split("_").pop();
		url = '/Schedule/remove-group/format/json';
	
		$.post(url, 
			{groupId: groupId},
			function(json){
				var dialog = $("#schedule_playlist_dialog");

				setScheduleDialogHtml(json);
				setScheduleDialogEvents(dialog);
			});	
	});
}

function makeScheduleDialog(dialog, json) {
	
	dialog.find("#schedule_playlist_search").keyup(function(){
		var url, string;
		
		url = "/Schedule/find-playlists/format/html";
		string = $(this).val();
		
		$.post(url, {search: string}, function(html){
			
			$("#schedule_playlist_choice")
				.empty()
				.append(html)
				.find('li')
					.draggable({ 
						helper: 'clone' 
					});
			
		});
	});

	dialog.find('#schedule_playlist_choice')
		.append(json.choice)
		.find('li')
			.draggable({ 
				helper: 'clone' 
			});

	dialog.find("#schedule_playlist_chosen")
		.append(json.chosen)
		.droppable({
      		drop: function(event, ui) {
				var pl_id, url, search;

				search = $("#schedule_playlist_search").val();
				pl_id = $(ui.helper).attr("id").split("_").pop();
				
				url = '/Schedule/schedule-show/format/json';

				$.post(url, 
					{plId: pl_id, search: search},
					function(json){
						var dialog = $("#schedule_playlist_dialog");

						setScheduleDialogHtml(json);
						setScheduleDialogEvents(dialog);
					});	
			}
    	});

	dialog.find("#show_progressbar").progressbar({
		value: json.percentFilled
	});

	setScheduleDialogEvents(dialog);
}

function openScheduleDialog(show) {
	var url, start_date, end_date;

	url = '/Schedule/schedule-show-dialog/format/json';
	
	start_date = makeTimeStamp(show.start);
	end_date = makeTimeStamp(show.end);

	$.post(url, 
		{start: start_date, end: end_date, showId: show.id},
		function(json){
			var dialog = $(json.dialog);

			makeScheduleDialog(dialog, json, show);

			dialog.dialog({
				autoOpen: false,
				title: 'Schedule Playlist',
				width: 1100,
				height: 500,
				modal: true,
				close: closeDialog,
				buttons: {"Ok": function() {
					dialog.remove();
					$("#schedule_calendar").fullCalendar( 'refetchEvents' );
				}}
			});

			dialog.dialog('open');
		});
}

function eventMenu(action, el, pos) {
	var method, event, date;

	method = action.split('/').pop();
	event = $(el).data('event');
	date = makeTimeStamp(event.start);

	if (method === 'delete-show') {
		$.post(action, 
			{format: "json", showId: event.id, date: date},
			function(json){
				$("#schedule_calendar").fullCalendar( 'refetchEvents' );
			});
	}
	else if (method === 'schedule-show') {
		
		openScheduleDialog(event);
	}
	else if (method === 'clear-show') {
		start_date = makeTimeStamp(event.start);

		url = '/Schedule/clear-show/format/json';

		$.post(url, 
			{start: start_date, showId: event.id},
			function(json){
				$("#schedule_calendar").fullCalendar( 'refetchEvents' );
			});	
	}
}

/**
*
*	Full Calendar callback methods.
*
*/

function dayClick(date, allDay, jsEvent, view) {
	var x;
}

function eventRender(event, element, view) { 
	//element.qtip({
     //       content: event.description
     //   });

	if(view.name === 'agendaDay' || view.name === 'agendaWeek') {
		var div = $('<div/>');
		div
			.height('5px')
			.width('100px')
			.css('margin-top', '5px')
			.progressbar({
				value: event.percent
			});

		if(event.percent === 0) {
			// even at 0, the bar still seems to display a little bit of progress...
			div.find("div").hide();
		}

		$(element).find(".fc-event-title").after(div);

	}	

	if(event.backgroundColor !== "") {
		$(element)
			.css({'border-color': '#'+event.backgroundColor})
			.find(".fc-event-time, a")
				.css({'background-color': '#'+event.backgroundColor, 'border-color': '#'+event.backgroundColor});
	}
	if(event.color !== "") {
		$(element)
			.find(".fc-event-time, a")
				.css({'color': '#'+event.color});
	}
}

function eventAfterRender( event, element, view ) {
	var today = new Date();	

	if(event.isHost === true && event.start > today) {
		$(element).contextMenu(
			{menu: 'schedule_event_host_menu'}, eventMenu
		);
	}
	else{
		$(element).contextMenu(
			{menu: 'schedule_event_default_menu'}, eventMenu
		);
	}

	$(element).data({'event': event});
}

function eventClick(event, jsEvent, view) { 
	var x;
}

function eventMouseover(event, jsEvent, view) { 
}

function eventMouseout(event, jsEvent, view) { 
}

function eventDrop(event, dayDelta, minuteDelta, allDay, revertFunc, jsEvent, ui, view) {
	var url;

	if (event.repeats && dayDelta !== 0) {
		revertFunc();
		return;
	}

	url = '/Schedule/move-show/format/json';

	$.post(url, 
		{day: dayDelta, min: minuteDelta, showId: event.id},
		function(json){
			if(json.overlap) {
				revertFunc();
			}
		});
}

function eventResize( event, dayDelta, minuteDelta, revertFunc, jsEvent, ui, view ) { 
	var url;

	url = '/Schedule/resize-show/format/json';

	$.post(url, 
		{day: dayDelta, min: minuteDelta, showId: event.id},
		function(json){
			if(json.overlap) {
				revertFunc();
			}
		});
}

$(document).ready(function() {

    $('#schedule_calendar').fullCalendar({
        header: {
			left: 'prev, next, today',
			center: 'title',
			right: 'agendaDay, agendaWeek, month'
		}, 
		defaultView: 'agendaDay',
		editable: false,
		allDaySlot: false,
		lazyFetching: false,

		events: function(start, end, callback) {
			var url, start_date, end_date;
	
			var sy, sm, sd, ey, em, ed;
			sy = start.getFullYear();
			sm = start.getMonth() + 1;
			sd = start.getDate();

			start_date = sy +"-"+ sm +"-"+ sd;

			ey = end.getFullYear();
			em = end.getMonth() + 1;
			ed = end.getDate();
			end_date = ey +"-"+ em +"-"+ ed;

			url = '/Schedule/event-feed';
			
			if ((ed - sd) === 1) {
				url = url + '/weekday/' + start.getDay();
			}

			var d = new Date();

			$.post(url, {format: "json", start: start_date, end: end_date, cachep: d.getTime()}, function(json){
				callback(json.events);
			});
		},

		//callbacks
		dayClick: dayClick,
		eventRender: eventRender,
		eventAfterRender: eventAfterRender,
		eventClick: eventClick,
		eventMouseover: eventMouseover,
		eventMouseout: eventMouseout,
		eventDrop: eventDrop,
		eventResize: eventResize 

    })

	$('#schedule_add_show').click(openShowDialog);

});

