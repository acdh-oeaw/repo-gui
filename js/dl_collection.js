var jq2 = jQuery;
jQuery.noConflict(true);
jq2(function( $ ) {
    
    var selectedItems = [];
    var disableChkUrlArray = [];
    var checked_ids = []; 
    var unchecked_ids = []; 
        
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
        return ' '+h+' '+Drupal.t('hour(s)')+' '+(m < 10 ? '0'+m : m)+' '+Drupal.t('min(s)')+(s < 10 ? '0'+s : s)+' '+Drupal.t('sec(s)'); //zero padding on minutes and seconds
    }
    
        /*
    var tableCollection = jq2('table.collTable').DataTable({
       "lengthMenu": [[20, 35, 50, -1], [20, 35, 50, "All"]]
    });
*/
    jq2(document).ready(function() {
			
        jq2('#selected_files_size_div').hide();
        jq2('#success_login_msg').hide();
        jq2('#error_login_msg').hide();
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
        
        /**  the collection download input field actions **/
        jq2("#search-input").keyup(function () {
            var searchString = $(this).val();
            $('#collectionBrowser').jstree('search', searchString);
        });
        
        /** the collection download jstree js  **/
        var sumSize = 0;
        var disableChkArray = [];
        //var disableChkUrlArray = [];
        var disableChkIDArray = [];
        var loadedData = [];
        
        
        function generateCollection(url, disabledUrls = [], username = "", password= "") {
            jq2('#collectionBrowser')
            .jstree({
                core : {
                    'check_callback': true,
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
            })//treeview before load the data to the ui
            .on("loaded.jstree", function (e, d) {
                
                loadedData = d;
                var userAllowedToDL = false;
                jq2.each( d.instance._model.data, function( key, value ) {
                    jq2.each( value.original, function( k, v ) { 
                        if(k == 'userAllowedToDL') {
                            if ( v == true ) { userAllowedToDL = true; } 
                        }
                        if(k == 'accessRestriction') {
                            if(  ((v != 'public') &&  v != actualUserRestriction) && actualUserRestriction != 'admin'){
                                if( userAllowedToDL === false){
                                    disableChkArray.push(key+'_anchor');
                                    disableChkUrlArray.push(value.original.uri);
                                    var obj = {};
                                    obj = {"id": value.id, "url": value.original.uri};
                                    disableChkIDArray.push(obj);
                                    jq2("#"+value.id).css('color','red');
                                    jq2("#collectionBrowser").jstree("uncheck_node", value.id);
                                    jq2("#collectionBrowser").jstree().disable_node(value.id);
                                }
                            }
                        }
                        userAllowedToDL = false;
                    });
                });
            });
        }
        generateCollection(url);
        
        
        //handle the node clicking to download the file
        jq2('#collectionBrowser').on("changed.jstree", function (node, data) {
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
                    } else if( resourceRestriction == 'public'){
                        jq2('#not_enough_permission').hide();
                        window.location.href = data.instance.get_node(data.selected[0]).original.uri;
                    }else {
                        alert(Drupal.t('You do not have access rights to get all of the resources'));
                    }
                }
            }
        })        
        //the tree view before open functions
        .on("before_open.jstree", function (e, d) {
            
            jq2('#not_enough_permission').hide();
            if (disableChkArray.length > 0) {
                jq2.each( disableChkArray, function( key, value ) {
                    jq2("#"+value).css('color','red');
                    jq2("#collectionBrowser").jstree("uncheck_node", value);
                    jq2("#collectionBrowser").jstree().disable_node(value);
                });
                jq2('#not_enough_permission').show();
            }
        })
        //handle the checkboxes to download the selected files as a zip
        .on("check_node.jstree", function (node, data) {
            jq2('#selected_files_size_div').show();
            jq2('#dl_link').hide();
            jq2('#dl_link_txt').hide();
            jq2('#getCollectionData').prop('disabled', false);
            jq2('#not_enough_permission').hide();
            if (disableChkArray.length > 0) {
                jq2.each( disableChkArray, function( key, value ) {
                    jq2("#collectionBrowser").jstree("uncheck_node", value.replace('_anchor', ''));
                    jq2("#collectionBrowser").jstree().disable_node(value.replace('_anchor', ''));
                });
                jq2('#not_enough_permission').show();
            }
            
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
                    jq2("#collectionBrowser").jstree().enable_node(id);
                    jq2("#"+id).css('color','black');
                    //check the rights
                    if( ((resourceRestriction != 'public') &&  resourceRestriction != actualUserRestriction) && actualUserRestriction != 'admin' ){
                        //if the user doesnt have any rights on the resource then we will make it unreachable to download
                        jq2('#not_enough_permission').show();
                    }
                    
                    if(size && uri){
                        //if( ((resourceRestriction == 'public') &&  resourceRestriction == actualUserRestriction) || actualUserRestriction == 'admin' ){
                            selectedItems.push({id: id, size: size, uri: uri, uri_dl: uri_dl, filename: filename});
                            sumSize += Number(size);
                            if(sumSize > 1599999999){
                                jq2("#selected_files_size").html("<p class='size_text_red'>" + bytesToSize(sumSize) + " ("+Drupal.t('Max tar download limit is') + " 1.5GB)</p> ");
                                jq2("#getCollectionDiv").hide();
                            }else {
                                jq2("#selected_files_size").html("<p class='size_text'>" + bytesToSize(sumSize)+" ("+Drupal.t('Max tar download limit is') + " 1.5GB) </p> ");   
                                jq2("#getCollectionDiv").show();
                            }
                        //}
                    }
                });
            }
        })
        
        //the tree view checkbox uncheck events
        .on("uncheck_node.jstree", function (node, data) {
            jq2('#selected_files_size_div').show();
            jq2('#dl_link').hide();
            jq2('#dl_link_txt').hide();
            jq2('#getCollectionData').prop('disabled', false);
            jq2('#not_enough_permission').hide();
            if (disableChkArray.length > 0) {
                jq2.each( disableChkArray, function( key, value ) {
                    //jq2("#"+value.replace('_anchor', '')).css('color','red');
                    jq2("#collectionBrowser").jstree("uncheck_node", value.replace('_anchor', ''));
                    jq2("#collectionBrowser").jstree().disable_node(value.replace('_anchor', ''));
                });
                jq2('#not_enough_permission').show();
            }
            
            if(data.instance.get_checked(true)) {
                selectedItems = [];
                sumSize = 0;
                var formData = data.instance.get_checked(true);
                
                if(formData.length == 0){
                    jq2("#selected_files_size").html("<p class='size_text'>" + bytesToSize(sumSize) + " ("+Drupal.t('Max tar download limit is') + " 1.5GB)</p> ");
                    jq2("#getCollectionDiv").hide();
                }else {
                    $.each( formData, function( index, value ){
                        var id = value.id;
                        var size = value.original.binarySize;
                        var uri = value.original.uri;
                        var uri_dl = value.original.encodedUri;
                        var filename = value.original.filename;
                        var resourceRestriction = value.original.accessRestriction;
                        jq2("#collectionBrowser").jstree().enable_node(id);
                        jq2("#"+id).css('color','black');
                        if(size && uri){
                            //if( ((resourceRestriction == 'public') &&  resourceRestriction == actualUserRestriction) || actualUserRestriction == 'admin' ){
                                selectedItems.push({id: id, size: size, uri: uri, uri_dl: uri_dl, filename: filename});
                                sumSize += Number(size);
                                if(sumSize > 1599999999){
                                    jq2("#selected_files_size").html("<p class='size_text_red'>" + bytesToSize(sumSize) + " ("+Drupal.t('Max tar download limit is') + " 1.5GB)</p> ");
                                    jq2("#getCollectionDiv").hide();
                                }else {
                                    jq2("#selected_files_size").html("<p class='size_text'>" + bytesToSize(sumSize)+" ("+Drupal.t('Max tar download limit is') + " 1.5GB) </p> ");   
                                    jq2("#getCollectionDiv").show();
                                }
                            //}
                        }
                    });
                }
            }
        });
        
        hidepopup();
        
        
        function checkResourceAccess(urls, username, password, callback) {
            var xhr = new XMLHttpRequest();
            
            let length = urls.length;
            var counter = 0;
            
            $.each(urls, function(i,u){
                $.when(
                    $.ajax(u.url, 
                    { 
                        type: 'HEAD',
                        username: username,
                        password: password
                    })
                    .error(function() {
                        callback(false);   
                    })
                    .then(
                        function (data, textStatus, jqXHR) {
                            if (jqXHR.status == 200) {
                                //if the user/pwd was okay then we will remove this id 
                                //rfom the result
                                urls = $.grep(urls, function(value) {
                                    return value.id != u.id;
                                });
                                counter++;
                                // last element reached so we will pass back the urls
                                if (counter == length) {
                                    callback(urls);   
                                }
                            }
                        }
                    )
                );
            });
            
        }
        
        jq2( "#loginToRestrictedResources" ).click(function(e) {
            showpopup();
            jq2( "#dologin" ).click(function(ed) {
                var username = $("input#username").val();
                var password = $("input#password").val();
                var counter = 0;
                checkResourceAccess(disableChkIDArray, username, password, function(newData) {
                    
                    if(newData === false){
                        counter++;
                        if(counter > 1){
                            return false;
                        }else {
                            jq2('#error_login_msg').show(0).delay(4000).fadeOut(1000).hide(0);
                            return false;
                        }
                    }
                    var newArray = [];
                    unchecked_ids = [];
                    //if we have resources which are still not available for us
                    $.each(newData, function(i,u){ 
                        //create the anchor ids
                        var ahrefId = u.id+'_anchor';
                        newArray.push(ahrefId);
                        unchecked_ids.push(u.id);
                    });
                    checked_ids = [];
                    
                    var checkedObj = $("#collectionBrowser").jstree("get_checked",true);
                    $.each(checkedObj,function(k,v){
                        if(v.state.checked === true){
                            checked_ids.push(this.id);
                            jq2("#"+this.id).css('color','black');
                            //remove the unchecked elements, because the jstree unchecked func not 
                            //working always as we expect.
                            checked_ids = checked_ids.filter(function(val) {
                                v.original.userAllowedToDL = true;
                                return unchecked_ids.indexOf(val) == -1;
                            });
                        }
                        jq2("#"+v.id).css('color','black');
                    });
                    
                    $.each(unchecked_ids,function(k,v){
                        //remove the unchecked elements, because the jstree unchecked func not 
                        //working always as we expect.
                        jq2("#"+v).css('color','red');
                        jq2("#collectionBrowser").jstree("uncheck_node", v);
                        jq2("#collectionBrowser").jstree().disable_node(v);
                    });
                    disableChkArray = newArray;
                    jq2('#success_login_msg').show(0).delay(2000).fadeOut(1000).hide(0);
                });
                ed.preventDefault();
                hidepopup();
            }); 
           e.preventDefault();
        });
        
        /** check the restriction for the dissemination services  START */
        
        jq2("#cancelLogin").click(function(){
            hidepopup();
        });
    
        function showpopup()
        {
            $("#dissServLoginform").fadeIn();
            $("#dissServLoginform").css({"visibility":"visible","display":"block"});
        }

        function hidepopup()
        {
            $("#dissServLoginform").fadeOut();
            $("#dissServLoginform").css({"visibility":"hidden","display":"none"});
        }
        
    });

    jq2(window).load(function() {
        //jq2("#loader").delay(3000).fadeOut("fast");
        jq2(".loader-div").hide();
    });
    
    //prepare the zip file
    jq2( "#getCollectionData" ).click(function(e) {
        jq2(".loader-div").show();
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
                jq2('#dl_link_a').html('<a href="'+data+'" target="_blank">'+Drupal.t("Download Collection")+'</a>');
                jq2('#dl_link').show();
                jq2('#dl_link_txt').show();
                jq2(".loader-div").delay(2000).fadeOut("fast");
                jq2("#getCollectionDiv").hide();
                return data;

            },
            error: function(message) {
                return message;
            }
        });

    });
        
        
        
});