var AIRTIME = (function(AIRTIME) {
    var mod;
    var $templateDiv;
    var $templateList;
    var $fileMDList;
    
    if (AIRTIME.itemTemplate === undefined) {
        AIRTIME.itemTemplate = {};
    }
    mod = AIRTIME.itemTemplate;
    
    function createTemplateLi(name, type, filemd, required) {
    	
    	var templateRequired = 
    		"<li id='<%= id %>' data-name='<%= name %>' data-type='<%= type %>' data-filemd='<%= filemd %>'>" +
    			"<span><%= name %></span>" +
    			"<span><%= type %></span>" +
    		"</li>";
    	
    	var templateOptional = 
    		"<li id='<%= id %>' data-name='<%= name %>' data-type='<%= type %>' data-filemd='<%= filemd %>'>" +
    			"<span><%= name %></span>" +
    			"<span><%= type %></span>" +
    			"<span class='template_item_remove'>Remove</span>" +
    		"</li>";
    	
    	var template = (required) === true ? templateRequired : templateOptional;
    	
    	var template = _.template(template);
    	var count = $templateList.find("li").length;
    	var id = "field_"+count;
    	var $li = $(template({id: id, name: name, type: type, filemd: filemd}));
    	
    	return $li;
    }
    
    function addField(name, type, filemd, required) {
    	
    	$templateList.append(createTemplateLi(name, type, filemd, required));
    }
    
    function getFieldData($el) {
    	
    	return {
    		name: $el.data("name"),
    		type: $el.data("type"),
    		isFileMd: $el.data("filemd"),
    		id: $el.data("id")
    	};
    	
    }
    
    mod.onReady = function() {
    	
    	$templateDiv = $("#configure_item_template");
    	$templateList = $(".template_item_list");
    	$fileMDList = $(".template_file_md");
    	
    	$fileMDList.on("dblclick", "li", function(){
    		
    		var $li = $(this);
			var name = $li.data("name");
			var type = $li.data("type");
			
			$templateList.append(createTemplateLi(name, type, true, false));
    	});
    	
    	$templateList.sortable();
    	
    	$templateDiv.on("click", ".template_item_remove", function() {
    		$(this).parents("li").remove();
    	});
    	
    	$templateDiv.on("click", ".template_item_add button", function() {
    		var $div = $(this).parents("div.template_item_add");
    		
    		var name = $div.find("input").val();
    		var type = $div.find("select").val();
    		
    		addField(name, type, false, false);
    	});
    	
    	function updateTemplate(template_id, isDefault) {
			var url = baseUrl+"Playouthistory/update-template/format/json";
			var data = {};
			var $lis, $li;
			var i, len;
			var templateName;
			
			templateName = $("#template_name").val();
			$lis = $templateList.children();
			
			for (i = 0, len = $lis.length; i < len; i++) {
				$li = $($lis[i]);
				
				data[i] = getFieldData($li);
			}
			
			$.post(url, {'id': template_id, 'name': templateName, 'fields': data, 'setDefault': isDefault}, function(json) {
				var x;
			});
    	}
    	
    	$templateDiv.on("click", "#template_item_save", function(){
    		var template_id = $(this).data("template");
    		
    		updateTemplate(template_id, false);
    	});
    	
    	$templateDiv.on("click", "#template_set_default", function(){
    		var template_id = $(this).data("template");	
			var url = baseUrl+"Playouthistory/set-template-default/format/json";
				
			$.post(url, {id: template_id}, function(json) {
				var x;
			});
    	});
    	
    };
    
return AIRTIME;
    
}(AIRTIME || {}));

$(document).ready(AIRTIME.itemTemplate.onReady);