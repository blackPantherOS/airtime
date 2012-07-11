$(document).ready(function() {
    setSmartPlaylistEvents();
});

function setSmartPlaylistEvents() {
	var form = $('#smart-playlist-form');
	
	form.find('a[id="criteria_add"]').click(function(){
        var div = $('dd[id="sp_criteria-element"]').children('div:visible:last').next();

        div.show();
        div.children().removeAttr('disabled');
        div = div.next();
        if(div.length === 0) {
            $(this).hide();
        }
	});
	
	form.find('a[id^="criteria_remove"]').click(function(){
        var curr = $(this).parent();
        var curr_pos = curr.index();
        var list = curr.parent();
        var list_length = list.find("div:visible").length;
        var count = list_length - curr_pos;
        var next = curr.next();
		
       for(var i=0; i<=count; i++) {
            var criteria = next.find('[name^="sp_criteria"]').val();
            curr.find('[name^="sp_criteria"]').val(criteria);
            var modifier = next.find('[name^="sp_criteria_modifier"]').val();
            curr.find('[name^="sp_criteria_modifier"]').val(modifier);
            var criteria_value = next.find('[name^="sp_criteria_value"]').val();
            curr.find('[name^="sp_criteria_value"]').val(criteria_value);

            curr = next;
            next = curr.next();
        }
		
        list.find('div:visible:last').children().attr('disabled', 'disabled');
        list.find("div:visible:last")
            .find('[name^="sp_criteria"]').val(0).end()
            .find('[name^="sp_criteria_modifier"]').val(0).end()
            .find('[name^="sp_criteria_value"]').val('')
            .end().hide();

        list.next().show();
    });
	
    form.find('button[id="save_button"]').click(function(event){
        var playlist_type = form.find('input:radio[name=sp_type]:checked').val(),
            data = $('form').serializeArray(),
            static_action = 'Playlist/smart-playlist-generate',
            dynamic_action ='Playlist/smart-playlist-criteria-save',
            action,
            callback;
		
        if (playlist_type == "0") {
            action = static_action;
            callback = staticCallback;
        } else {
            action = dynamic_action;
            callback = dynamicCallback;
        }
        $.post(action, {format: "json", data: data}, callback);
    });
	
    form.find('dd[id="sp_type-element"]').change(function(){
        var playlist_type = $('input:radio[name=sp_type]:checked').val(),
            button_text;
        if (playlist_type == "0") {
            button_text = 'Generate';
        } else {
            button_text = 'Save';
        }
        $('button[id="save_button"]').text(button_text);    	
    });
    
    form.find('select[id^="sp_criteria"]').change(function(){
        var criteria = $(this).val(),
            criteria_type = criteriaTypes[criteria],
            div = $(this);
        $(this).next().children().remove();
        
        if (criteria_type == 's') {
            $.each(stringCriteriaOptions, function(key, value){
                div.next().append($('<option></option>')
                          .attr('value', key)
                          .text(value));
            });
        } else {
            $.each(numericCriteriaOptions, function(key, value){
                div.next().append($('<option></option>')
                          .attr('value', key)
                          .text(value));
            })
        }
    });
    
    form.find('select[id^="sp_criteria_modifier"]').change(function(){
        if ($(this).val() == 'is in the range') {
            var criteria_value = $(this).next(),
                index_name = criteria_value.attr('id'),
                index_num = index_name.charAt(index_name.length-1);
            
            criteria_value.after($('<input type="text" class="input_text">')
                          .attr('id', 'sp_criteria_extra_'+index_num)
                          .attr('name', 'sp_criteria_extra_'+index_num)).after(' to ');
        
        }	
    });
	
}

function staticCallback() {
	
}

function dynamicCallback() {
	
}

var criteriaTypes = {
    0 : "",
    "album_title" : "s",
    "artist_name" : "s",
    "bit_rate" : "n",
    "bmp" : "n",
    "comments" : "s",
    "composer" : "s",
    "conductor" : "s",
    "utime" : "n",
    "mtime" : "n",
    "disc_number" : "n",
    "genre" : "s",
    "isrc_number" : "s",
    "label" : "s",
    "language" : "s",
    "length" : "n",
    "lyricist" : "s",
    "mood" : "s",
    "name" : "s",
    "orchestra" : "s",
    "radio_station_name" : "s",
    "rating" : "n",
    "sample_rate" : "n",
    "soundcloud_id" : "n",
    "track_title" : "s",
    "track_num" : "n",
    "year" : "n"               
};

var stringCriteriaOptions = {
    "0" : "Select modifier",
    "contains" : "contains",
    "does not contain" : "does not contain",
    "is" : "is",
    "is not" : "is not",
    "starts with" : "starts with",
    "ends with" : "ends with"
};
    
var numericCriteriaOptions = {
    "0" : "Select modifier",
    "is" : "is",
    "is not" : "is not",
    "is greater than" : "is greater than",
    "is less than" : "is less than",
    "is in the range" : "is in the range"
};
