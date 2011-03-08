var datagridData = null;
var currentShowInstanceID = -1;

function getDateText(obj){
	var str = obj.aData[ obj.iDataColumn ].toString();
	datetime = str.split(" ");
    if (datetime.length == 2)
        return datetime[0];
	return str;
}

function getTimeText(obj){
	var str = obj.aData[ obj.iDataColumn ].toString();
	datetime = str.split(" ");
    if (datetime.length == 2)
        return changeTimePrecision(datetime[1]);
	return str;
}

function changeTimePrecisionInit(obj){
	var str = obj.aData[ obj.iDataColumn ].toString();
	return changeTimePrecision(str);
}

function changeTimePrecision(str){
    
    var temp = str.split(".")
    if (temp.length == 2){
        if (temp[1].length > 2)
            return temp[0]+"."+temp[1].substr(0, 2);
    }
    return str;
}

function notifySongStart(){
	for (var i=0; i<datagridData.rows.length; i++){
		if (datagridData.rows[i][0] == "c")
			datagridData.rows[i][0] = "p";
		if (datagridData.rows[i][0] == "n"){
			datagridData.rows[i][0] = "c";
			break;
		}
	}
	
	updateDataTable();
}

function notifyShowStart(show){
	currentShowInstanceID = show.instance_id;
	updateDataTable();
}

var columns = [{"sTitle": "type", "bVisible":false},
    {"sTitle":"Date"},
    {"sTitle":"Start"},
    {"sTitle":"End"},
    {"sTitle":"Duration"},
    {"sTitle":"Song"},
    {"sTitle":"Artist"},
    {"sTitle":"Album"},
    {"sTitle":"Playlist"},
    {"sTitle":"Show"},
    {"sTitle":"instance_id", "bVisible":false}];

function getDateString(){
    var date0 = $("#datepicker").datepicker("getDate");
    return (date0.getFullYear() + "-" + (parseInt(date0.getMonth())+1) + "-" + date0.getDate());
}

function getAJAXURL(){
    var url = "/Nowplaying/get-data-grid-data/format/json/view/"+viewType;
    
    if (viewType == "day"){
      url +=  "/date/" + getDateString();
    }
    
    return url;
}

function updateDataTable(){
    var table = $('#nowplayingtable').dataTable();

    //Check if datagridData has been initialized since this update
    //function can be called before ajax call has been returned.
    if (datagridData != null){
        table.fnClearTable(false);
        table.fnAddData(datagridData.rows, false);
        table.fnDraw(true);
    }
}

function getData(){
       $.ajax({ url: getAJAXURL(), dataType:"json", success:function(data){
		datagridData = data.entries;
        if (datagridData.currentShow.length > 0)
            currentShowInstanceID = datagridData.currentShow[0].instance_id;
        updateDataTable();
	  }});   
}

function init2(){	        
      getData();

      setTimeout(init2, 5000);
}

function redirect(url){
    document.location.href = url;
}

function createDataGrid(){
    	
	columns[1]["fnRender"] = getDateText;
	columns[2]["fnRender"] = getTimeText;
	columns[3]["fnRender"] = getTimeText;
	columns[4]["fnRender"] = changeTimePrecisionInit;

	$('#nowplayingtable').dataTable( {
		"bSort" : false,
		"bJQueryUI": true,
		"bFilter": false,
		"bInfo": false,
		"bLengthChange": false,
        "bPaginate": false,
		"aoColumns": columns,
		"fnRowCallback": function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
            if (aData[aData.length-2] == currentShowInstanceID)
                $(nRow).addClass("playing-list");
            if (aData[0] == "c")
				$(nRow).attr("class", "playing-song");
            else if (aData[0] == "over"){
                //$(nRow).attr("class", "over-booked-song");
            }
			return nRow;
		},
        "fnDrawCallback": function(oSettings){
            //check if there are any rows to display
            if (oSettings.aiDisplay.length == 0)
                return;

            var nTrs = $('#nowplayingtable tbody tr');
            var iColspan = nTrs[0].getElementsByTagName('td').length;

            for (var i=0; i<nTrs.length; i++){
                
                var iDisplayIndex = oSettings._iDisplayStart + i;
                var sType = oSettings.aoData[ oSettings.aiDisplay[iDisplayIndex]]._aData[0];

                if ( sType == "g" ){
                    var showName = oSettings.aoData[ oSettings.aiDisplay[iDisplayIndex]]._aData[9];
                    var startTime = oSettings.aoData[ oSettings.aiDisplay[iDisplayIndex]]._aData[2];
                    var endTime = oSettings.aoData[ oSettings.aiDisplay[iDisplayIndex]]._aData[3];

                    var nGroup = document.createElement('tr');
                    var nCell = document.createElement('td');
                    nCell.colSpan = iColspan;
                    nCell.className = "group";
                    nCell.innerHTML = showName + ": " + startTime + " - " + endTime;
                    nGroup.appendChild(nCell);
                    nTrs[i].parentNode.replaceChild(nGroup, nTrs[i]);
                } else if ( sType == "b" ){
                    var gapTime = oSettings.aoData[ oSettings.aiDisplay[iDisplayIndex]]._aData[1];
                    
                    var nGroup = document.createElement('tr');
                    var nCell = document.createElement('td');
                    nCell.colSpan = iColspan;
                    nCell.className = "gap";
                    nCell.innerHTML = "Gap until show end: " + gapTime + " seconds";
                    nGroup.appendChild(nCell);
                    nTrs[i].parentNode.replaceChild(nGroup, nTrs[i]);                   
                }
            }
            
        },
        "bAutoWidth":false
	} );
}

$(document).ready(function() {
    
    createDataGrid();
    if (viewType == "day"){
        $('#now_view').click(function(){redirect('/Nowplaying/index')});
        
        $("#datepicker").datepicker({
            onSelect: function(dateText, inst) 
                { getData();}});
        $("#datepicker").datepicker("setDate", new Date());
    } else {
        $('#day_view').click(function(){redirect('/Nowplaying/day-view')});
    }

    init2();
});
