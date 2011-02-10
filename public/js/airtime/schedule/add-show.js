/**
*
*	Schedule Dialog creation methods.
*
*/

//dateText mm-dd-yy
function startDpSelect(dateText, inst) {
	var time, date;

	time = dateText.split("-");
	date = new Date(time[0], time[1] - 1, time[2]);

	$("#add_show_end_date").datepicker("option", "minDate", date);
}

function endDpSelect(dateText, inst) {
	var time, date;

	time = dateText.split("-");
	date = new Date(time[0], time[1] - 1, time[2]);

	$("#add_show_start_date").datepicker( "option", "maxDate", date);
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

function autoSelect(event, ui) {

	$("#add_show_hosts-"+ui.item.value).attr("checked", "checked");
	event.preventDefault();
}

function findHosts(request, callback) {
	var search, url;

	url = "/User/get-hosts";
	search = request.term;

	$.post(url, 
		{format: "json", term: search}, 
		
		function(json) {
			callback(json.hosts);
		});
	
}

function setAddShowEvents() {
	
	$("h3").click(function(){
        $(this).next().toggle();
    });

    if(!$("#add_show_repeats").attr('checked')) {
        $("#schedule-show-when > fieldset:last").hide();
    }

    $("#add_show_repeats").click(function(){
        $("#schedule-show-when > fieldset:last").toggle();
    });

    $("#add_show_repeat_type").change(function(){
        if($(this).val() == 2) {
            $("#add_show_day_check-label, #add_show_day_check-element").hide();
        }
        else {
            $("#add_show_day_check-label, #add_show_day_check-element").show();
        }
    });

    $("#add_show_no_end").click(function(){
        $("#add_show_end_date").toggle();
    });

	createDateInput($("#add_show_start_date"), startDpSelect);
	createDateInput($("#add_show_end_date"), endDpSelect);

    $("#add_show_start_time").timepicker();
    $("#add_show_duration").timepicker({
        amPmText: ['', ''] 
    });

	$("#add_show_hosts_autocomplete").autocomplete({
		source: findHosts,
		select: autoSelect,
        delay: 200 
	});

	$("#schedule-show-style input").ColorPicker({
       onChange: function (hsb, hex, rgb, el) {
		    $(el).val(hex);
	    },
		onSubmit: function(hsb, hex, rgb, el) {
			$(el).val(hex);
			$(el).ColorPickerHide();
		},
		onBeforeShow: function () {
			$(this).ColorPickerSetColor(this.value);
		}
	});


    $("#add-show-close")
        .button()
		.click(function(event){
            event.stopPropagation();
            event.preventDefault();
			$("#add-show-form").hide();
		});

	$("#add-show-submit")
		.button()
		.click(function(){
			$("form").submit();
		});
}

$(document).ready(function() {

	setAddShowEvents();

	$("#fullcalendar_show_display").fullCalendar({
		header: {
			left: 'prev, next, today',
			center: 'title',
			right: 'agendaDay, agendaWeek, month'
		}, 
		defaultView: 'month',
		editable: false,
		allDaySlot: false,
        axisFormat: 'H:mm',
        timeFormat: {
            agenda: 'H:mm{ - H:mm}',
            month: 'H:mm{ - H:mm}'
        },

		events: getFullCalendarEvents,

		//callbacks
		eventRender: eventRender
	});

});

$(window).load(function() {
     var mainHeight = document.documentElement.clientHeight - 200 - 50;
    
    $('#fullcalendar_show_display').fullCalendar('option', 'contentHeight', mainHeight);
    $('#fullcalendar_show_display').fullCalendar('render');
});
