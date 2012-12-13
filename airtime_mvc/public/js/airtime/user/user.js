function populateForm(entries){
    //$('#user_details').show();
        
    $('.errors').remove();
    $('.success').remove();
    
    $('#user_id').val(entries.id);
    $('#login').val(entries.login);
    $('#first_name').val(entries.first_name);
    $('#last_name').val(entries.last_name);
    $('#type').val(entries.type);
    $('#email').val(entries.email);
    $('#cell_phone').val(entries.cell_phone);
    $('#skype').val(entries.skype_contact);
    $('#jabber').val(entries.jabber_contact);
    
    if (entries.id.length != 0){
        $('#login').attr('readonly', 'readonly');
        $('#password').val("xxxxxx");
    } else {
        $('#login').removeAttr('readonly');
        $('#password').val("");
    }
}

function rowClickCallback(row_id){
      $.ajax({ url: baseUrl+'/User/get-user-data/id/'+ row_id +'/format/json', dataType:"json", success:function(data){
        populateForm(data.entries);
	  }});    
}

function removeUserCallback(row_id, nRow){
      $.ajax({ url: baseUrl+'/User/remove-user/id/'+ row_id +'/format/json', dataType:"text", success:function(data){
        var o = $('#users_datatable').dataTable().fnDeleteRow(nRow);
	  }});
}

function rowCallback( nRow, aData, iDisplayIndex ){
    $(nRow).click(function(){rowClickCallback(aData['id'])});
    if( aData['delete'] != "self"){
    	$('td:eq(4)', nRow).append( '<span class="ui-icon ui-icon-closethick"></span>').children('span').click(function(e){e.stopPropagation(); removeUserCallback(aData['id'], nRow)});
    }else{
    	$('td:eq(4)', nRow).empty().append( '<span class="ui-icon ui-icon-closethick"></span>').children('span').click(function(e){e.stopPropagation(); alert("Can't delete yourself!")});
    }

    if ( aData['type'] == "A" )
    {
	    $('td:eq(3)', nRow).html( $.i18n._('Admin') );
    } else if ( aData['type'] == "H" )
    {
	    $('td:eq(3)', nRow).html( $.i18n._('DJ') );
    } else if ( aData['type'] == "G" )
    {
	    $('td:eq(3)', nRow).html( $.i18n._('Guest') );
    } else if ( aData['type'] == "P" )
    {
    	$('td:eq(3)', nRow).html( $.i18n._('Program Manager') );
    }
    
    return nRow;
}

function populateUserTable() {
    $('#users_datatable').dataTable( {
        "bProcessing": true,
        "bServerSide": true,
        "sAjaxSource": baseUrl+"/User/get-user-data-table-info/format/json",
        "fnServerData": function ( sSource, aoData, fnCallback ) {
            $.ajax( {
                "dataType": 'json', 
                "type": "POST", 
                "url": sSource, 
                "data": aoData, 
                "success": fnCallback
            } );
        },
        "fnRowCallback": rowCallback,
        "aoColumns": [
            /* Id */         { "sName": "id", "bSearchable": false, "bVisible": false, "mDataProp": "id" },
            /* user name */  { "sName": "login", "mDataProp": "login" },
            /* first name */ { "sName": "first_name", "mDataProp": "first_name" },
            /* last name */  { "sName": "last_name", "mDataProp": "last_name" },
            /* user type */  { "sName": "type", "bSearchable": false, "mDataProp": "type" },
            /* del button */ { "sName": "null as delete", "bSearchable": false, "bSortable": false, "mDataProp": "delete"}
        ],
        "bJQueryUI": true,
        "bAutoWidth": false,
        "bLengthChange": false,
        "oLanguage": datatables_dict,
        
        "sDom": '<"H"lf<"dt-process-rel"r>>t<"F"ip>',
    });
}

$(document).ready(function() {
    populateUserTable();

    //assign user-rights and id to each user type option so we can
    //display user rights for each with tipsy tooltip
    $.each($('#type').children(), function(i, opt) {
        switch ($(this).val()) {
            case 'G':
                $(this).attr('id', 'user-type-G');
                $(this).attr('user-rights',
                    $.i18n._('Guests can do the following:')+'<br><br>'+
                    $.i18n._('View schedule')+'<br>'+
                    $.i18n._('View current playing tracks')
                );
                break;
            case 'H':
                $(this).attr('id', 'user-type-H');
                $(this).attr('user-rights',
                    $.i18n._('DJs can do the following:')+'<br><br>'+
                    $.i18n._('View schedule')+'<br>'+
                    $.i18n._('View current playing tracks')+'<br>'+
                    $.i18n._('Manage assigned show content')+'<br>'+
                    $.i18n._('Import media files')+'<br>'+
                    $.i18n._('Manage their own library content')+'<br>'+
                    $.i18n._('Create playlists, smart blocks, and webstreams')
                );
                break;
            case 'P':
                $(this).attr('id', 'user-type-P');
                $(this).attr('user-rights',
                    $.i18n._('Progam Managers can do the following:')+'<br><br>'+
                    $.i18n._('View schedule')+'<br>'+
                    $.i18n._('Schedule shows')+'<br>'+
                    $.i18n._('Import media files')+'<br>'+
                    $.i18n._('Manage all library content')+'<br>'+
                    $.i18n._('Create playlists, smart blocks, and webstreams')+'<br>'+
                    $.i18n._('Manage show content')
                );
                break;
            case 'A':
                $(this).attr('id', 'user-type-A');
                $(this).attr('user-rights',
                    $.i18n._('Admins can do the following:')+'<br><br>'+
                    $.i18n._('Manage preferences')+'<br>'+
                    $.i18n._('Manage users')+'<br>'+
                    $.i18n._('Manage watched folders')+'<br>'+
                    $.i18n._('Send support feedback')+'<br>'+
                    $.i18n._('View system status')+'<br>'+
                    $.i18n._('Access playout history')+'<br>'+
                    $.i18n._('View listener stats')+'<br>'+
                    $.i18n._('View schedule')+'<br>'+
                    $.i18n._('Schedule shows')+'<br>'+
                    $.i18n._('Import media files')+'<br>'+
                    $.i18n._('Manage all library content')+'<br>'+
                    $.i18n._('Create playlists, smart blocks, and webstreams')+'<br>'+
                    $.i18n._('Manage show content')
                );
                break;
        }
    });

    $('#type').live("change", function(){
        //when the title changes on live tipsy tooltips the changes take
        //affect the next time tipsy is shown so we need to hide and re-show it
        $(this).tipsy('hide').tipsy('show');
    });
    
    $('#type').tipsy({
        gravity: 'w',
        html: true,
        opacity: 0.9,
        trigger: 'manual',
        live: true,
        title: function() {
            return $('#user-type-'+$(this).val()).attr('user-rights');
        }
    });
    
    $('#type').tipsy('show');
    
    var newUser = {login:"", first_name:"", last_name:"", type:"G", id:""};
    
    $('#add_user_button').live('click', function(){populateForm(newUser)});
    
    $('#save_user').live('click', function(){
        var data = $('#user_form').serialize();
        var url = baseUrl+'/User/add-user';
        
        $.post(url, {format: "json", data: data}, function(data){
            var json = $.parseJSON(data);
            if (json.valid === "true") {
                $('#content').empty().append(json.html);
                populateUserTable();
            } else {
                //if form is invalid we only need to redraw the form
                $('#user_form').empty().append($(json.html).find('#user_form').children());
            }
            setTimeout(removeSuccessMsg, 5000);
        });
    });
    
});
