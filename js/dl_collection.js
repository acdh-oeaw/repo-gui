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
    
    function secondsTimeSpanToHMS(s) {
        var h = Math.floor(s/3600); //Get whole hours
        s -= h*3600;
        var m = Math.floor(s/60); //Get remaining minutes
        s -= m*60;
        return h+" hour(s) "+(m < 10 ? '0'+m : m)+" min(s)"+(s < 10 ? '0'+s : s)+" second(s)"; //zero padding on minutes and seconds
    }
    
        /*
    var tableCollection = jq2('table.collTable').DataTable({
       "lengthMenu": [[20, 35, 50, -1], [20, 35, 50, "All"]]
    });
*/
    jq2(document).ready(function() {
			
        jq2('#selected_files_size_div').hide();
        jq2('#collection_dl_info').hide();
        
        let dlTime = jq2('#estDLTime').val();
        let formattedDlTime = secondsTimeSpanToHMS(dlTime);
        jq2('#dl_time').html(formattedDlTime);
        
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
        
        jq2('#collection_dl_info_button').click(function(e) {
            if (jq2('#collection_dl_info').css('display') == 'none') {
                jq2('#collection_dl_info_button').html('Hide usage information');
                jq2('#collection_dl_info').show(1000);
            }else{
                jq2('#collection_dl_info_button').html('Show usage information');
                jq2('#collection_dl_info').hide(1000);
            }
            e.preventDefault();
            
        
        });
        
        /**  the collection download input field actions **/
        jq2("#search-input").keyup(function () {
            var searchString = $(this).val();
            $('#collectionBrowser').jstree('search', searchString);
        });
        
        /** the collection download jstree js  **/
        var sumSize = 0;
        var disableChkArray = [];
        jq2('#collectionBrowser')
            .jstree({
                core : {
                    data : {
                        "url" : '/browser/get_collection_data/'+url,
                        "dataType" : "json"
                    },
                    themes : { stripes : true }
		},
                checkbox : {
                    //keep_selected_style : true,
                    tie_selection : false,
                    whole_node : false
                },
                search: {
                    case_sensitive: false,
                    show_only_matches: true
                },
                plugins : [ 'checkbox', 'search' ]
	})
        //handle the node clicking to download the file
        .on("changed.jstree", function (node, data) {
            jq2('#selected_files_size_div').show();
            jq2('#dl_link').hide();
            jq2('#dl_link_txt').hide();
            if(data.selected.length == 1) {
                //if we have a directory then do not open the fedora url
                if(data.node.original.dir === false){
                    let id = data.instance.get_node(data.selected[0]).id;
                    //check the permissions for the file download
                    var resourceRestriction = data.instance.get_node(data.selected[0]).original.accessRestriction;
                    if( ((resourceRestriction != 'public') &&  resourceRestriction == actualUserRestriction) || actualUserRestriction == 'admin' ){
                        jq2('#not_enough_permission').hide();
                        window.location.href = data.instance.get_node(data.selected[0]).original.uri;
                    /*} else if( ((resourceRestriction != 'public') &&  resourceRestriction != actualUserRestriction) && actualUserRestriction != 'admin' ){
                        //if the user doesnt have any rights on the resource then we will make it unreachable to download
                        //jq2("#"+id+" > a > i.jstree-checkbox").attr("disabled", true);*/
                    } else if( resourceRestriction == 'public'){
                        jq2('#not_enough_permission').hide();
                        window.location.href = data.instance.get_node(data.selected[0]).original.uri;
                    }else {
                        alert("Sorry! But you dont have enough rights to download it!");
                    }
                }
            }
        })
        .on("loaded.jstree", function (e, d) {
            jq2.each( d.instance._model.data, function( key, value ) {
                jq2.each( value.original, function( k, v ) {
                    if(k == 'accessRestriction') {
                        if(  ((v != 'public') &&  v != actualUserRestriction) && actualUserRestriction != 'admin'  ){
                            disableChkArray.push(key+'_anchor');
                        }
                    }
                });
            });
        })
        .on("before_open.jstree", function (e, d) {
            jq2.each( disableChkArray, function( key, value ) {
                jq2("#"+value+" > i").removeClass('jstree-icon jstree-checkbox');
                let text = jq2("#"+value).text();
                jq2("#"+value).html(text + '');
                jq2("#"+value).css('color','red');
            });
        })
        //handle the checkboxes to download the selected files as a zip
        .on("check_node.jstree", function (node, data) {
            jq2('#selected_files_size_div').show();
            jq2('#dl_link').hide();
            jq2('#dl_link_txt').hide();
            jq2('#getCollectionData').prop('disabled', false);
            jq2('#not_enough_permission').hide();
            
            jq2.each( disableChkArray, function( key, value ) {
                jq2("#"+value+" > i").removeClass('jstree-icon jstree-checkbox');
                let text = jq2("#"+value).text();
                jq2("#"+value).html(text + '');
                jq2("#"+value).css('color','red');
            });
            
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
                    var resourceRestriction = "public";
                    if(value.original.hasOwnProperty("accessRestriction")){
                        resourceRestriction = value.original.accessRestriction;
                    }
                    
                    //check the rights
                    if( ((resourceRestriction != 'public') &&  resourceRestriction != actualUserRestriction) && actualUserRestriction != 'admin' ){
                        //if the user doesnt have any rights on the resource then we will make it unreachable to download
                        jq2('#not_enough_permission').show();
                    }
                    
                    if(size && uri){
                        if( ((resourceRestriction == 'public') &&  resourceRestriction == actualUserRestriction) || actualUserRestriction == 'admin' ){
                            selectedItems.push({id: id, size: size, uri: uri, uri_dl: uri_dl, filename: filename});
                            sumSize += Number(size);
                            if(sumSize > 1599999999){
                                jq2("#selected_files_size").html("<p class='size_text_red'>" + bytesToSize(sumSize) + " (Max tar download limit is 1.5GB)</p> ");
                                jq2("#getCollectionDiv").hide();
                            }else {
                                jq2("#selected_files_size").html("<p class='size_text'>" + bytesToSize(sumSize)+" (Max tar download limit is 1.5GB) </p> ");   
                                jq2("#getCollectionDiv").show();
                            }
                        }
                    }
                });
            }
        })
        .on("uncheck_node.jstree", function (node, data) {
            jq2('#selected_files_size_div').show();
            jq2('#dl_link').hide();
            jq2('#dl_link_txt').hide();
            jq2('#getCollectionData').prop('disabled', false);
            
            
            jq2.each( disableChkArray, function( key, value ) {
                jq2("#"+value+" > i").removeClass('jstree-icon jstree-checkbox');
                let text = jq2("#"+value).text();
                jq2("#"+value).html(text + '');
                jq2("#"+value).css('color','red');
            });
            
            if(data.instance.get_checked(true)) {
                selectedItems = [];
                sumSize = 0;
                var formData = data.instance.get_checked(true);
                
                if(formData.length == 0){
                    jq2("#selected_files_size").html("<p class='size_text'>" + bytesToSize(sumSize) + " (Max tar download limit is 1.5GB)</p> ");
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
                                    jq2("#selected_files_size").html("<p class='size_text_red'>" + bytesToSize(sumSize) + " (Max tar download limit is 1.5GB)</p> ");
                                    jq2("#getCollectionDiv").hide();
                                }else {
                                    jq2("#selected_files_size").html("<p class='size_text'>" + bytesToSize(sumSize)+" (Max tar download limit is 1.5GB) </p> ");   
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
            resArr['uri'] = value.uri;
            resArr['filename'] = value.filename;
            myObj[index] = resArr;
	});
        
        jq2.ajax({
            url: '/browser/oeaw_dlc/'+insideUri,
            type: "POST",
            data: {jsonData : JSON.stringify(myObj)},
            tiemout: 1800,
            success: function(data, status) {
                jq2('#dl_link_a').html('<a href="'+data+'" target="_blank">Download the Collection TAR</a>');
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