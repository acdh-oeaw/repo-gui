var jq2 = jQuery;
jQuery.noConflict(true);
jq2(function( $ ) {
    
    var selectedItems = [];
    
    function bytesToSize(bytes, decimals = 2) {
        if(bytes == 0) return '0 Bytes';
        var k = 1024,
            dm = decimals || 2,
            sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'],
        i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    };
        /*
    var tableCollection = jq2('table.collTable').DataTable({
       "lengthMenu": [[20, 35, 50, -1], [20, 35, 50, "All"]]
    });
*/
    jq2(document).ready(function() {
			
        //var uid = Drupal.settings.currentUser;
        var roles = drupalSettings.oeaw.users.roles;
        var actualUserRestriction = 'public';
        
        if (roles !== ''){
            if (roles.includes('administrator')) {
                actualUserRestriction = 'admin';
            }
            if (roles.includes('academic')) {
                actualUserRestriction = 'academic';
            }
            if (roles.includes('restricted')) {
                actualUserRestriction = 'restricted';
            }
            if (roles.includes('anonymus')) {
                actualUserRestriction = 'public';
            }
        }
        
        var url = jq2('#insideUri').val();
        
        var sumSize = 0;
        jq2('#collectionBrowser')
            .jstree({
                core : {
                    data : {
                        "url" : '/browser/get_collection_data/'+url,
                        "dataType" : "json" 
                    }
		},
                checkbox : {
                    //keep_selected_style : true,
                    tie_selection : false,
                    whole_node : false
                },
                plugins : [ 'checkbox' ],
		
	})
        //handle the node clicking to download the file
        .on("changed.jstree", function (node, data) {
            jq2('#dl_link').hide();
            jq2('#dl_link_txt').hide();
            if(data.selected.length == 1) {
                //if we have a directory then do not open the fedora url
                if(data.node.original.dir === false){
                    //check the permissions for the file download
                    var resourceRestriction = data.instance.get_node(data.selected[0]).original.accessRestriction;
                    if( ((resourceRestriction != 'public') &&  resourceRestriction == actualUserRestriction) || actualUserRestriction == 'admin' ){
                        window.location.href = data.instance.get_node(data.selected[0]).original.uri;
                    } else if( resourceRestriction == 'public'){
                        window.location.href = data.instance.get_node(data.selected[0]).original.uri;
                    }else {
                        alert("Sorry! But you dont have enough rights to download it!");
                    }
                }
            }
        })
        //handle the checkboxes to download the selected files as a zip
        .on("check_node.jstree", function (node, data) {
            jq2('#dl_link').hide();
            jq2('#dl_link_txt').hide();
            jq2('#getCollectionData').prop('disabled', false);

            if(data.instance.get_checked(true)) {
                
                var formData = data.instance.get_checked(true);
                sumSize = 0;
                selectedItems = [];
                
                $.each( formData, function( index, value ){
                    var id = value.id;
                    var size = value.original.binarySize;
                    var uri = value.original.uri;
                    var uri_dl = value.original.encodedUri;
                    var filename = value.original.filename;
                    var resourceRestriction = value.original.accessRestriction;
                    
                    //check the rights
                    if( ((resourceRestriction != 'public') &&  resourceRestriction != actualUserRestriction) && actualUserRestriction != 'admin' ){
                        console.log("itt");
                        console.log(actualUserRestriction);
                        //if the user doesnt have any rights on the resource then we will make it unreachable to download
                        $('#'+id+" > a").removeClass();
                        $('#'+id+" > a").addClass("jstree-anchor jstree-disabled");
                    }
                    
                    if(size && uri){
                        if( ((resourceRestriction == 'public') &&  resourceRestriction == actualUserRestriction) || actualUserRestriction == 'admin' ){
                            selectedItems.push({id: id, size: size, uri: uri, uri_dl: uri_dl, filename: filename});
                            sumSize += Number(size);
                            if(sumSize > 1599999999){
                                jq2("#selected_files_size").html("<p class='size_text_red'>" + bytesToSize(sumSize) + " (Max zip download limit is 1.5GB)</p> ");
                                jq2("#getCollectionDiv").hide();
                            }else {
                                jq2("#selected_files_size").html("<p class='size_text'>" + bytesToSize(sumSize)+" (Max zip download limit is 1.5GB) </p> ");   
                                jq2("#getCollectionDiv").show();
                            }
                        }
                    }
                });
            }
        })
        .on("uncheck_node.jstree", function (node, data) {
            jq2('#dl_link').hide();
            jq2('#dl_link_txt').hide();
            jq2('#getCollectionData').prop('disabled', false);
            
            if(data.instance.get_checked(true)) {
                selectedItems = [];
                sumSize = 0;
                var formData = data.instance.get_checked(true);
                
                if(formData.length == 0){
                    jq2("#selected_files_size").html("<p class='size_text'>" + bytesToSize(sumSize) + " (Max zip download limit is 1.5GB)</p> ");
                    jq2("#getCollectionDiv").hide();
                }else {
                    $.each( formData, function( index, value ){
                        var id = value.id;
                        var size = value.original.binarySize;
                        var uri = value.original.uri;
                        var uri_dl = value.original.encodedUri;
                        var filename = value.original.filename;
                        var resourceRestriction = value.original.accessRestriction;
                        
                        if(size && uri){
                            if( ((resourceRestriction == 'public') &&  resourceRestriction == actualUserRestriction) || actualUserRestriction == 'admin' ){
                                selectedItems.push({id: id, size: size, uri: uri, uri_dl: uri_dl, filename: filename});
                                sumSize += Number(size);
                                if(sumSize > 1599999999){
                                    jq2("#selected_files_size").html("<p class='size_text_red'>" + bytesToSize(sumSize) + " (Max zip download limit is 1.5GB)</p> ");
                                    jq2("#getCollectionDiv").hide();
                                }else {
                                    jq2("#selected_files_size").html("<p class='size_text'>" + bytesToSize(sumSize)+" (Max zip download limit is 1.5GB) </p> ");   
                                    jq2("#getCollectionDiv").show();
                                }
                            }
                        }
                    });
                }
            }
        });
        
    });

    jq2(window).load(function() {
        //jq2("#loader").delay(3000).fadeOut("fast");
        jq2("#loader-div").hide();
    });


    //prepare the zip file
    jq2( "#getCollectionData" ).click(function(e) {
        jq2("#loader-div").show();
        //disable the button after the click
        $(this).prop('disabled', true);
        var insideUri = jq2('#insideUri').val();
        e.preventDefault();
        var uriStr = "";
        //object for the file list
        var myObj = {};
        
        jq2.each(selectedItems, function(index, value) {
            uriStr += value.uri_dl+"__";
            var resArr = {};
            resArr['uri'] = value.uri_dl;
            resArr['filename'] = value.filename;
            myObj[index] = resArr;
	});
        
        jq2.ajax({
            url: '/browser/oeaw_dlc/'+insideUri,
            type: "POST",
            data: {jsonData : JSON.stringify(myObj)},
            tiemout: 1800,
            success: function(data, status) {
                jq2('#dl_link_a').html('<a href="'+data+'" target="_blank">Download the Collection ZIP</a>');
                jq2('#dl_link').show();
                jq2('#dl_link_txt').show();
                jq2("#loader-div").delay(2000).fadeOut("fast");
                jq2("#getCollectionDiv").hide();
                return data;

            },
            error: function(message) {
                return message;
            }
        });

    });
        
       
        
});