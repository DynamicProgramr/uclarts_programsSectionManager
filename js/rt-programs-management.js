/* JavaScript for rt-programs-managment plugin */

jQuery(function()
{
	jQuery("#show_roster").click(function(e)
	{
		e.preventDefault();
		var nonce = jQuery(this).attr("data-nonce");
		var program_id = jQuery("#program_select").val();
		
		// for testing -> 
		alert("Program ID: " + program_id);
		
		jQuery.ajax(
		{
			type:	"post",
			dataType:	"JSON",
			url:		programsManagementAjax.ajaxurl,
			data:	{
						"action": "generate_roster_list",
						"post_id": program_id,
						"nonce": nonce
					},
			success: function(response)
			{
				if(response.type == "success")
				{
					// can use response.product_id and responsse.product_title if needed
					// but this info is include in response.html, so only use if need outside of display
					// for testing ->
					alert("Success");
                    jQuery("#data_list").html("Program ID: " + program_id + "<br />" +response.html);
                    // also want stuff for 'export to csv' enabled
                    jQuery("#csv_post_id").val(program_id);
                    jQuery("#csv_product_id").val(response.product_id);
                    console.log("program product ID for csv field: " + response.product_id);
                    jQuery("#submit_csv").attr("disabled", false);
                    jQuery("#submit_xls").attr("disabled", false);
                    var submitBtn_status = jQuery("#submit_csv").attr("disabled");
                    console.log("csv button now " + submitBtn_status);
				}
				else if(response.type == "fail")
				{
					jQuery("#data_list").html("An error occured. Error: " + response.html + " for product id: " + response.product_id + ".");
					alert("An error occured.  No roster info returned.");
				}
				else if(response.type == "idBad")
				{
					jQuery("#data_list").html("Program ID error: program ID does not seem to exist.");
					alert("Program ID not in database. Contact your developer.");
				}
				else
				{
					jQuery("#data_list").html(nonce); //("Unknown error occured.");
					alert("Unknown error occured. Please clear browser cache and try again.");
				}
			},
			error: function(errorThrown)
			{
				jQuery("#data_list").html("nonce: " + nonce + "<br />Ajax error: " + errorThrown);
				alert("Ajax error.");
				console.log(errorThrown);
			}
		});
	});
	
	jQuery("#narrow_programs_list").click(function(e)
	{
		e.preventDefault();
		var nonce = jQuery(this).attr("data-nonce");
		var dateArray = {
			"start_month": jQuery("#startMonth").val(),
			"start_day": jQuery("#startDay").val(),
			"start_year": jQuery("#startYear").val(),
			"end_month": jQuery("#endMonth").val(),
			"end_day": jQuery("#endDay").val(),
			"end_year": jQuery("#endYear").val()
		};
		
        // check to see if any of the date values is "choose." if so, alert and return with no action
        /*
		for (var i in dateArray)
		{
			if (dateArray[i] == "choose")
			{
				alert("All dates must have a value.");
				return;
			}
        }
        */
        // this is now just start dates that can not have 'choose'
        if(dateArray['start_month'] == "choose" || dateArray['start_day'] == "choose" || dateArray['start_year'] == "choose")
        {
            alert("All 'Start Date' fields must have values.");
            return;
        }
		
		// for testing -> alert("start month: " + dateArray['start_month'] + " start day: " + dateArray['start_day'] + " start year: " + dateArray['start_year']);
		
		jQuery.ajax(
		{
			type:	"post",
			dataType:	"JSON",
			url:		programsManagementAjax.ajaxurl,
			data:	{
						"action": "narrow_program_list",
						"start_month": dateArray['start_month'],
						"start_day": dateArray['start_day'],
						"start_year": dateArray['start_year'],
						"end_month": dateArray['end_month'],
						"end_day": dateArray['end_day'],
						"end_year": dateArray['end_year'],
						"nonce": nonce
					},
			success: function(response)
			{
				if(response.type == "success")
				{
					// can use response.product_id and responsse.product_title if needed
					// but this info is include in response.html, so only use if need outside of display
					// for testing ->
					alert("Success");
					
					// remove the <li> children from the #program_select element
					jQuery("#program_select").children().remove();
					jQuery("#program_list_message").html("List filtered by date (" + dateArray['start_month'] + "/" + dateArray['start_day'] + "/" + dateArray['start_year'] + " - " + dateArray['end_month'] + "/" + dateArray['end_day'] + "/" + dateArray['end_year'] + ")<br />");
					jQuery("#program_select").append(response.html);
				}
				else if(response.type == "fail")
				{
					jQuery("#program_list_message").html("<span style=\"font-weight: bold; color: #ff0000;\">List not filtered.</span><br />");
					// for testing ->
					jQuery("#program_select").children().remove();
					jQuery("#program_select").append(response.html);
					// end test code
					alert("An error occured.  No programs returned for list.");
				}
				else if(response.type == "other")
				{
					alert("type: other");
					jQuery("#data_list").html(response.html);
				}
				else
				{
					jQuery("#data_list").html(nonce + "<br />" + response.html); //("Unknown error occured. Please clear browser cache and try again.");
					alert("Unknown error occured.");
				}
			},
			error: function(errorThrown)
			{
				jQuery("#data_list").html("nonce: " + nonce + "<br />Ajax error: " + errorThrown);
				alert("Ajax error.");
				console.log(errorThrown);
			}
		});
    });
    
    // 02/23/2019 - export data for csv
    jQuery("#submit_csv").click(function(e)
	{
		// this sends the program and product IDs to the function to create the csv file for Roster
		
		e.preventDefault();
        var nonce = jQuery(this).attr("data-nonce");

        // verify the program and product IDs are passed
        var programId = jQuery("#csv_post_id").val();
        var productId = jQuery("#csv_product_id").val();
        console.log("Program ID for csv export: " + programId + "\nProduct ID for csv export: " + productId);

        var dataArray = {
            "action": "generate_csv",
            "postId": programId,  // program id is the wp post id
            "productId": productId,
            "nonce": nonce
        };

        jQuery.ajax(
        {
            type:	"post",
            dataType:	"JSON",
            url:		programsManagementAjax.ajaxurl,
            data:	dataArray,
            success: function(response)
            {
                if(response.type == "success")
                {
                    var responseMessage = response.html;
                    console.log("PHP Returned (Program) Product ID: " + response.product_id);
                    console.log(response.roster);
                    alert("success: CSV file has been generated.");
                    
                    // send to new php page for generation and download
                    /* this works, but client finds it confusing cuz not getting feedback they want
                    var sendData = function()
                    {
                        jQuery.post("../data/index.php",
                                    {data: response.roster},
                                    function(response2)
                                    {
                                        console.log(response2);
                                    });
                    }
                    sendData();
                    */
                        // method opens new tab or window
                    var obj2string = JSON.stringify(response.roster);
                    var tabOpen = window.open("../data/index.php?data=" + obj2string, "_blank");
                    if (tabOpen)
                    {
                        tabOpen.focus();
                    }
                }
                else if(response.type == "fail")
                {
                    var responseMessage = "CSV generation has failed: " + response.html;
                    alert(responseMessage)
                }
                else if(response.type == "other")
                {
                    var responseMessage = "other result: " + response.html;
                    alert(responseMessage);
                }
                else
                {
                    var responseMessage = "unknown message - type: " + response.type + " message: " + response.html;
                    alert("unknown response returned");
                }

                jQuery("#csv_result_message").html(responseMessage);
                setTimeout(doTimeout, 30000, "csv_result_message", "id");
            },
            error: function(errorThrown)
            {
                jQuery("#csv_result_message").html("nonce: " + nonce + "<br />Ajax error: " + errorThrown);
                alert("Ajax error.");
                console.log(errorThrown);
                setTimeout(doTimeout, 30000, "csv_result_message", "id");
            }
        });

    });  // end click event for 'submit_csv'

    // 04/25/2019 - export data for xls
    jQuery("#submit_xls").click(function(e)
	{
		// this sends the program and product IDs to the function to create the xls file for Roster
		
		e.preventDefault();
        var nonce = jQuery(this).attr("data-nonce");

        // verify the program and product IDs are passed
        var programId = jQuery("#csv_post_id").val();
        var productId = jQuery("#csv_product_id").val();
        console.log("Program ID for xls export: " + programId + "\nProduct ID for xls export: " + productId);

        var dataArray = {
            "action": "generate_csv",
            "postId": programId,  // program id is the wp post id
            "productId": productId,
            "nonce": nonce
        };

        jQuery.ajax(
        {
            type:	"post",
            dataType:	"JSON",
            url:		programsManagementAjax.ajaxurl,
            data:	dataArray,
            success: function(response)
            {
                if(response.type == "success")
                {
                    var responseMessage = response.html;
                    console.log("PHP Returned (Program) Product ID: " + response.product_id);
                    console.log(response.roster);
                    alert("success: XML file has been generated.");
                    
                    // send to new php page for generation and download
                    /* this works, but client finds it confusing cuz not getting feedback they want
                    var sendData = function()
                    {
                        jQuery.post("../data/index.php",
                                    {data: response.roster},
                                    function(response2)
                                    {
                                        console.log(response2);
                                    });
                    }
                    sendData();
                    */
                        // method opens new tab or window
                    var obj2string = JSON.stringify(response.roster);
                    var tabOpen = window.open("../data/db2excel.php?data=" + obj2string, "_blank");
                    if (tabOpen)
                    {
                        tabOpen.focus();
                    }
                }
                else if(response.type == "fail")
                {
                    var responseMessage = "XLS generation has failed: " + response.html;
                    alert(responseMessage)
                }
                else if(response.type == "other")
                {
                    var responseMessage = "other result: " + response.html;
                    alert(responseMessage);
                }
                else
                {
                    var responseMessage = "unknown message - type: " + response.type + " message: " + response.html;
                    alert("unknown response returned");
                }

                jQuery("#csv_result_message").html(responseMessage);
                setTimeout(doTimeout, 30000, "csv_result_message", "id");
            },
            error: function(errorThrown)
            {
                jQuery("#csv_result_message").html("nonce: " + nonce + "<br />Ajax error: " + errorThrown);
                alert("Ajax error.");
                console.log(errorThrown);
                setTimeout(doTimeout, 30000, "csv_result_message", "id");
            }
        });

    });  // end click event for 'submit_xls'

	jQuery("#modify_carat_btn").click(function(e)
	{
		// this does a save of the new or modified carat info
		
		e.preventDefault();
        var nonce = jQuery(this).attr("data-nonce");

		if (jQuery("#modify_carat_id").val() == "add")
		{
            // this is a new carat, no id # yet in the db
            // generate the name of the textarea the carat data uses on the front-end
            var theDate = new Date();
            var generatedCaratName = "carat_custom_" + theDate.getTime();
            var caratOrder = jQuery("#modify_carat_position").val(); // this needs to be outside the dataArray because it is used for placing the new custom item in the list

            var dataArray = {
                "action": "save_new_carat",
                "caratTitle": jQuery("#modify_carat_title").val(),
                "caratRole": jQuery("#modify_carat_role").val(),  // at time of writing, this should always be 'custom'
                "caratOrder": caratOrder, // assigned above
                "caratTextareaName": generatedCaratName, // generated above
                "nonce": nonce
            };

            jQuery.ajax(
            {
                type:	"post",
                dataType:	"JSON",
                url:		programsManagementAjax.ajaxurl,
                data:	dataArray,
                success: function(response)
                {
                    if(response.type == "success")
                    {
                        var responseMessage = "success: the carat was saved and inserted above.";
                        // i want this new carat item to be inserted in the position of 'caratOrder' : note that there might already be something there
                        var thePosition = caratOrder - 1;
                        var totalChildren = jQuery(".customCarat_sortable").children().length;

                        if(caratOrder == 1)
                        {
                            jQuery(".customCarat_sortable li:eq(1)").before(response.html);
                        }
                        else if (caratOrder > totalChildren)
                        {
                            jQuery(".customCarat_sortable").append(response.html);
                        }
                        else
                        {
                            jQuery(".customCarat_sortable li:eq(" + thePosition + ")").before(response.html);
                        }
                        
                        alert(responseMessage);
                        jQuery("#modify_carat_title").val("");
                    }
                    else if(response.type == "fail")
                    {
                        var responseMessage = "save failed: " + response.html;
                        alert(responseMessage)
                    }
                    else if(response.type == "other")
                    {
                        var responseMessage = "other result: " + response.html;
                        alert(responseMessage);
                    }
                    else
                    {
                        var responseMessage = "unknown message - type: " + response.type + " message: " + response.html;
                        alert("unknown response returned");
                    }

                    jQuery("#data_list").html(responseMessage);
                    setTimeout(doTimeout, 30000, "data_list", "id");
                },
                error: function(errorThrown)
                {
                    jQuery("#data_list").html("nonce: " + nonce + "<br />Ajax error: " + errorThrown);
                    alert("Ajax error.");
                    console.log(errorThrown);
                    setTimeout(doTimeout, 30000, "data_list", "id");
                }
            });
		}
		else
		{
			// this is existing data being modified
		}
    });  // end click event for 'modify_carat_btn'
    
    jQuery("#save_carat_settings").click(function(e)
	{
		// this does a save of the default carats' order
		
		e.preventDefault();
        var nonce = jQuery(this).attr("data-nonce");

        // get the order of the default carat list
        var caratNewOrder = jQuery(".defaultCarat_sortable").sortable("toArray", {attribute: "data-default_id"});
        console.log(caratNewOrder);

        var dataArray = {
            "action": "save_carat_order",
            "role": "default",
            "caratOrder": caratNewOrder,  // this is an array with the values being the IDs of the carats in the database
            "nonce": nonce
        };

        jQuery.ajax(
        {
            type:	"post",
            dataType:	"JSON",
            url:		programsManagementAjax.ajaxurl,
            data:	dataArray,
            success: function(response)
            {
                if(response.type == "success")
                {
                    var responseMessage = "success: the carat order has been saved";
                    alert(responseMessage);
                }
                else if(response.type == "fail")
                {
                    var responseMessage = "save failed: " + response.html;
                    alert(responseMessage)
                }
                else if(response.type == "other")
                {
                    var responseMessage = "other result: " + response.html;
                    alert(responseMessage);
                }
                else
                {
                    var responseMessage = "unknown message - type: " + response.type + " message: " + response.html;
                    alert("unknown response returned");
                }

                jQuery("#default_carat_message").html(responseMessage);
                setTimeout(doTimeout, 30000, "default_carat_message", "id");
            },
            error: function(errorThrown)
            {
                jQuery("#default_carat_message").html("nonce: " + nonce + "<br />Ajax error: " + errorThrown);
                alert("Ajax error.");
                console.log(errorThrown);
                setTimeout(doTimeout, 30000, "default_carat_message", "id");
            }
        });

    });  // end click event for 'save_carat_settings'

    jQuery("#save_custom_carat_order").click(function(e)
	{
		// this does a save of the custom carats' order
		
		e.preventDefault();
        var nonce = jQuery(this).attr("data-nonce");

        // get the order of the default carat list
        var caratNewOrder = jQuery(".customCarat_sortable").sortable("toArray", {attribute: "data-custom_id"});
        console.log(caratNewOrder);

        var dataArray = {
            "action": "save_carat_order",
            "role": "custom",
            "caratOrder": caratNewOrder,  // this is an array with the values being the IDs of the carats in the database
            "nonce": nonce
        };

        jQuery.ajax(
        {
            type:	"post",
            dataType:	"JSON",
            url:		programsManagementAjax.ajaxurl,
            data:	dataArray,
            success: function(response)
            {
                if(response.type == "success")
                {
                    var responseMessage = "success: the carat order has been saved";
                    alert(responseMessage);
                }
                else if(response.type == "fail")
                {
                    var responseMessage = "save failed: " + response.html;
                    alert(responseMessage)
                }
                else if(response.type == "other")
                {
                    var responseMessage = "other result: " + response.html;
                    alert(responseMessage);
                }
                else
                {
                    var responseMessage = "unknown message - type: " + response.type + " message: " + response.html;
                    alert("unknown response returned");
                }

                jQuery("#custom_carat_message").html(responseMessage);
                setTimeout(doTimeout, 30000, "custom_carat_message", "id");
            },
            error: function(errorThrown)
            {
                jQuery("#custom_carat_message").html("nonce: " + nonce + "<br />Ajax error: " + errorThrown);
                alert("Ajax error.");
                console.log(errorThrown);
                setTimeout(doTimeout, 30000, "custom_carat_message", "id");
            }
        });

    });  // end click event for 'save_carat_settings'
    
    // jQuery(".customCaratDelete_btn").click(function(e) <- becauses some carat items may be appended after document load, this will not work. those carats do not get the click event
    jQuery(".customCarat_sortable").on("click", ".customCaratDelete_btn", function(e)
	{
        e.preventDefault();
        
        // get the db id for this carat row (in the data-custom_id attr)
        var dbId = jQuery(this).parent().data("custom_id");

        var dataArray = {
            "action": "delete_custom_carat",
            "dbId": dbId
        };

        // for testing ->
        console.log("Carat delete clicked: " + dbId);

        jQuery.ajax(
        {
            type:	"post",
            dataType:	"JSON",
            url:		programsManagementAjax.ajaxurl,
            data:	dataArray,
            success: function(response)
            {
                if(response.type == "success")
                {
                    var responseMessage = "success: the carat has been deleted";
                    // delete the child from the unordered list
                    jQuery("li[data-custom_id='" + dbId + "']").remove();
                    alert(responseMessage);
                }
                else if(response.type == "fail")
                {
                    var responseMessage = response.html;
                    alert(responseMessage)
                }
                else if(response.type == "other")
                {
                    var responseMessage = response.html;
                    alert(responseMessage);
                }
                else
                {
                    var responseMessage = "unknown message - type: " + response.type + " message: " + response.html;
                    alert("unknown response returned");
                }

                jQuery("#custom_carat_message").html(responseMessage);
                setTimeout(doTimeout, 30000, "custom_carat_message", "id");
            },
            error: function(errorThrown)
            {
                jQuery("#custom_carat_message").html("nonce: " + nonce + "<br />Ajax error: " + errorThrown);
                alert("Ajax error.");
                console.log(errorThrown);
                setTimeout(doTimeout, 30000, "custom_carat_message", "id");
            }
        });
    }); // end click event for 'customCaratDelete_btn'

    

    jQuery(".customCarat_sortable").on("click", ".customCaratEdit_btn", function(e)
	{
        e.preventDefault();

        // get the db id for this carat row (in the data-custom_id attr)
        var dbId = jQuery(this).parent().data("custom_id");
        var currentPosition = jQuery(this).parent().data("custom_position"); // for use below, on success
        var theParent = jQuery(this).parent(); // for use below, on success

        // get the new title for the carat (use javascript prompt)
        var newTitle = prompt("Enter the new carat name:");
        
        if (newTitle != null)
        {
            var dataArray = {
                "action": "edit_custom_carat",
                "dbId": dbId,
                "newTitle": newTitle
            };

            console.log("The new title: " + newTitle);
        }
        else
        {
            var dataArray = [];
            alert("You MUST enter a new title!  The process has terminated.  Try again.");
            return false;
        }

        

        // for testing ->
        console.log("Edit carat clicked: " + dbId);

        jQuery.ajax(
        {
            type:	"post",
            dataType:	"JSON",
            url:		programsManagementAjax.ajaxurl,
            data:	dataArray,
            success: function(response)
            {
                if(response.type == "success")
                {
                    var responseMessage = response.html;
                    theParent.html("<span class=\"ui-icon ui-icon-arrowthick-2-n-s\"></span>" + newTitle + "<span class=\"ui-icon ui-icon-trash carat-delete customCaratDelete_btn\" style=\"cursor: pointer;\"></span> <span class=\"ui-icon ui-icon-pencil carat-edit customCaratEdit_btn\" style=\"cursor: pointer;\"></span>");
                    alert("success: the new name has been saved");
                }
                else if(response.type == "fail")
                {
                    var responseMessage = response.html;
                    alert("failure: change not saved")
                }
                else if(response.type == "other")
                {
                    var responseMessage = response.html;
                    alert("unknown result - see message on page");
                }
                else
                {
                    var responseMessage = "unknown message - type: " + response.type + " message: " + response.html;
                    alert("unknown response returned");
                }

                jQuery("#custom_carat_message").html(responseMessage);
                setTimeout(doTimeout, 30000, "custom_carat_message", "id");
            },
            error: function(errorThrown)
            {
                jQuery("#custom_carat_message").html("nonce: " + nonce + "<br />Ajax error: " + errorThrown);
                alert("Ajax error.");
                console.log(errorThrown);
                setTimeout(doTimeout, 30000, "custom_carat_message", "id");
            }
        });
    }); // end click event for 'customCaratEdit_btn'

    function doTimeout(theElement, elementAttr = "id")
    {
        // this is used to remove the message from any message area in this plugin
        // it uses the jQuery .html() method and the name of the element is passed in
        if(elementAttr == "id")
        {
            jQuery("#" + theElement).html("");
        }
        else
        {
            jQuery("." + theElement).html("");
        }
        
    } // end doTimeout function
}); // end of jQuery(function())
