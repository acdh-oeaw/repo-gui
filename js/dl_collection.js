 jQuery(function($) {
    "use strict";
    var selectedItems = [];
    var disableChkUrlArray = [];
    var checked_ids = []; 
    var unchecked_ids = []; 
    
    var disableChkArray = [];
    var disableChkIDArray = [];
    
    var resourceGroupsData = {};
        
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
    var tableCollection = $('table.collTable').DataTable({
       "lengthMenu": [[20, 35, 50, -1], [20, 35, 50, "All"]]
    });
*/

    function getActualuserRestriction() {
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
        if(drupalSettings.oeaw.users.name == "shibboleth") {
            actualUserRestriction = 'academic';
        }
        return actualUserRestriction;
    }

    function generateCollection(url, disabledUrls = [], username = "", password= "") {
                
        var actualUserRestriction = getActualuserRestriction();
        
        var loadedData = [];
        $('#collectionBrowser')
        .jstree({
            core : {
                'check_callback': false,
                data : {
                    "url" : '/browser/get_collection_data/'+url,
                    "dataType" : "json"
                },
                themes : { stripes : true },
                error : function (jqXHR, textStatus, errorThrown) { 
                    $('#collectionBrowser').html("<h3>Error: </h3><p>" + jqXHR.reason +"</p>");
                } 
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
            $.each( d.instance._model.data, function( key, value ) {
                $.each( value.original, function( k, v ) { 
                    if(k == 'userAllowedToDL') {
                        if ( v == true ) { userAllowedToDL = true; } 
                    }
                    if(k == 'accessRestriction') {
                        
                        var result = v.split('/');
                        var resRestriction = result.slice(-1)[0];
                        if(!resRestriction) { resRestriction = "public"; }
                        
                        if(  ((resRestriction != 'public') &&  resRestriction != actualUserRestriction) && actualUserRestriction != 'admin'){
                            userAllowedToDL === false;
                            disableChkArray.push(key+'_anchor');
                            disableChkUrlArray.push(value.original.uri);
                            var obj = {};
                            obj = {"id": value.id, "url": value.original.uri, "accessRestriction": resRestriction};
                            //get one url for the permission levels
                            if(!resourceGroupsData.hasOwnProperty(resRestriction)) {
                                resourceGroupsData[resRestriction] = value.original.uri;
                            }
                            
                            disableChkIDArray.push(obj);
                            $("#"+value.id).css('color','red');
                            $("#collectionBrowser").jstree("uncheck_node", value.id);
                            $("#collectionBrowser").jstree().disable_node(value.id);
                        }
                    }
                    userAllowedToDL = false;
                });
            });
        });
    }
    
    function checkResourceAccess(urls, username, password, callback) {
        var xhr = new XMLHttpRequest();
        $("#loader-div").css("display", "block");
        
        let length = Object.keys(resourceGroupsData).length;
        var counter = 0;
        var result = [];
        $.each(urls, function(i,u){
            $.when(
                $.ajax(u+'/fcr:metadata', 
                { 
                    type: 'HEAD',
                    username: username,
                    password: password,
                    error: function(error) {
                        callback(false);   
                    }
                })
            )
            .then(
                function (data, textStatus, jqXHR) {
                    if (jqXHR.status == 200) {
                        //if the user/pwd was okay then we will remove this id 
                        //rfom the result
                        result.push(i);
                        counter++;
                        // last element reached so we will pass back the urls
                        if (counter == length) {
                            callback(result);   
                        }
                    }
                }
            );
        });
    }

    $(document).ready(function() {
       
        $('#selected_files_size_div').hide();
        $('#success_login_msg').hide();
        $('#error_login_msg').hide();
        let dlTime = $('#estDLTime').val();
        let formattedDlTime = secondsTimeSpanToHMS(dlTime);
        $('#dl_time').html(formattedDlTime);
        
        //var uid = Drupal.settings.currentUser;
        var roles = drupalSettings.oeaw.users.roles;
        var actualUserRestriction = 'public';
        
        actualUserRestriction = getActualuserRestriction();
        
        var url = $('#insideUri').val();
        
        if(!getCookie(url)){
            window.setTimeout( generateCollection(url), 5000 );
        }
        
        /**  the collection download input field actions **/
        $("#search-input").keyup(function () {
            var searchString = $(this).val();
            $('#collectionBrowser').jstree('search', searchString);
        });
        
        /** the collection download jstree js  **/
        var sumSize = 0;        
                
        //handle the node clicking to download the file
        $('#collectionBrowser').on("changed.jstree", function (node, data) {
            $('#selected_files_size_div').show();
            $('#dl_link').hide();
            $('#dl_link_txt').hide();
            
            if(data.selected.length == 1) {
                //if we have a directory then do not open the fedora url
                if(data.node.original.dir === false){
                    let id = data.instance.get_node(data.selected[0]).id;
                    //check the permissions for the file download
                    var resourceRestriction = data.instance.get_node(data.selected[0]).original.accessRestriction;
                    if( ((resourceRestriction.search('public') == -1) && resourceRestriction.indexOf(actualUserRestriction) == -1 ) 
                            || actualUserRestriction == 'admin' ) {
                        $('#not_enough_permission').hide();
                        window.location.href = data.instance.get_node(data.selected[0]).original.uri;
                    } else if( resourceRestriction.search('public') != -1 ){
                        $('#not_enough_permission').hide();
                        window.location.href = data.instance.get_node(data.selected[0]).original.uri;
                    }else {
                        alert(Drupal.t('You do not have access rights to get all of the resources'));
                    }
                }
            }
        })        
        //the tree view before open functions
        .on("before_open.jstree", function (e, d) {
            $('#not_enough_permission').hide();
            if (disableChkArray.length > 0) {
                $.each( disableChkArray, function( key, value ) {
                    $("#"+value).css('color','red');
                    //$("#collectionBrowser").jstree("uncheck_node", value);
                    //$("#collectionBrowser").jstree().disable_node(value);
                });
                $('#not_enough_permission').show();
            }else {
                $.each( d.instance._model.data, function( key, value ) {
                    $("#collectionBrowser").jstree().enable_node(value.id);
                });
            }
        })
        //handle the checkboxes to download the selected files as a zip
        .on("check_node.jstree uncheck_node.jstree", function (node, data) {
            
            $('#selected_files_size_div').show();
            $('#dl_link').hide();
            $('#dl_link_txt').hide();
            $('#getCollectionData').prop('disabled', false);
            $('#not_enough_permission').hide();
            if (disableChkArray.length > 0) {
                $.each( disableChkArray, function( key, value ) {
                    //$("#collectionBrowser").jstree("uncheck_node", value.replace('_anchor', ''));
                    //$("#collectionBrowser").jstree().disable_node(value.replace('_anchor', ''));
                });
                $('#not_enough_permission').show();
            }
            sumSize = 0;
            
            if(data.instance.get_checked(true)) {
                
                selectedItems = [];
                var actualResource = data.instance.get_checked(true);
                
                
                if(actualResource.length > 4000) {
                    $.each( actualResource, function( i, res ){
                        $("#collectionBrowser").jstree("uncheck_node", res.id);
                    });
                    $("#selected_files_size").html("<p class='size_text_red'> "+Drupal.t('You can select max 4000 files!') + "("+ actualResource.length  + " " + Drupal.t('Files') + ") </p> ");
                    $("#getCollectionDiv").hide();
                    
                } else {
                    //check here also the disables array
                    $.each( actualResource, function( i, res ){
                        if( res ){

                            var id = res.id;
                            var size = res.original.binarySize;
                            var uri = res.original.uri;
                            var uri_dl = res.original.encodedUri;
                            var filename = res.original.filename;
                            var path = res.original.path;
                            var resourceRestriction = "public";
                            if(res.original.hasOwnProperty("accessRestriction")){
                                resourceRestriction = res.original.accessRestriction;
                            }
                            var enabled = false;
                            //check the rights
                            if( ((resourceRestriction != 'public') &&  resourceRestriction != actualUserRestriction) && actualUserRestriction != 'admin' ){
                                if (disableChkArray.length > 0) {
                                    $.each( disableChkArray, function( key, value ) {
                                        $("#"+value).css('color','red');
                                        $("#collectionBrowser").jstree("uncheck_node", id);
                                        $("#collectionBrowser").jstree().disable_node(id);
                                    });
                                    $('#not_enough_permission').show();
                                }else {
                                    enabled = true;
                                    $("#collectionBrowser").jstree().enable_node(id);
                                }
                            }else {
                                enabled = true;
                                $("#collectionBrowser").jstree().enable_node(id);
                                $("#"+id).css('color','black');
                            }

                            if(size && uri){
                               // if( ((resourceRestriction == 'public') &&  resourceRestriction == actualUserRestriction) || actualUserRestriction == 'admin' ){
                               if(enabled === true) {
                                    selectedItems.push({id: id, size: size, uri: uri, uri_dl: uri_dl, filename: filename, path: path});
                                    sumSize += Number(size);
                                    if(sumSize > 6299999999){
                                        $("#selected_files_size").html("<p class='size_text_red'>" + bytesToSize(sumSize) + " ("+Drupal.t('Max tar download limit is') + " 6GB) ("+ actualResource.length + " " + Drupal.t('Files') + ")</p> ");
                                        $("#getCollectionDiv").hide();                                    
                                    }else {
                                        $("#selected_files_size").html("<p class='size_text'>" + bytesToSize(sumSize)+" ("+Drupal.t('Max tar download limit is') + " 6GB) ("+ actualResource.length + " " + Drupal.t('Files') + ")</p> ");   
                                        $("#getCollectionDiv").show();
                                    }
                                }
                            }
                        } else {
                            sumSize = 0;
                        }
                    });
                }
            }
        });
        hidepopup();
        
         //prepare the zip file
        $('#getCollectionData').on('click', function(e){
            
            $("#loader-div").show();
            //disable the button after the click
            $(this).prop('disabled', true);
            var insideUri = $('#insideUri').val();
            e.preventDefault();
            var uriStr = "";
            //object for the file list
            var myObj = {};

            $.each(selectedItems, function(index, value) {
                uriStr += value.uri_dl+"__";
                
                var resArr = {};
                resArr['uri'] = value.uri;
                resArr['filename'] = value.filename;
                resArr['path'] = value.path;
                myObj[index] = resArr;
            });
            
            $.ajax({
                url: '/browser/oeaw_dlc/'+insideUri,
                type: "POST",
                async: false,
                data: {jsonData : JSON.stringify(myObj)},
                timeout: 3600,
                success: function(data, status) {
                    $('#dl_link_a').html('<a href="'+data+'" target="_blank">'+Drupal.t("Download Collection")+'</a>');
                    $('#dl_link').show();
                    $('#dl_link_txt').show();
                    $("#loader-div").delay(2000).fadeOut("fast");
                    $("#getCollectionDiv").hide();
                    return data;
                },
                error: function(xhr,status,error) {
                    $("#loader-div").delay(2000).fadeOut("fast");
                    $("#selected_files_size").html("<p class='size_text_red'>" + Drupal.t('A server error has occurred... '+status) + " </p> ");
                }
            });

        });
        
    
        $('#loginToRestrictedResources').on('click', function(e) {
            showpopup();
            $('#dologin').on('click', function(ed) {
                $("#loader-div").css("display", "block");
               
                var username = $("input#username").val();
                var password = $("input#password").val();
                
                //checkResourceAccess(disableChkIDArray, username, password, function(newData) {
                checkResourceAccess(resourceGroupsData, username, password, function(newData) {
                    
                    if(newData === false){
                        $("#loader-div").delay(2000).fadeOut("fast");
                        $('#error_login_msg').show(0).delay(4000).fadeOut(1000).hide(0);
                        return false;
                    }
                    
                    var newArray = [];
                    unchecked_ids = [];
                    var disabledArray = disableChkIDArray;
                    disableChkIDArray = [];
                    disableChkArray = [];
                    //if we have resources which are still not available for us
                    $.each(newData, function(i,u){ 
                        $.each(disabledArray, function(ind,val) { 
                            if(val.accessRestriction == u) { 
                                 //create the anchor ids 
                                var ahrefId = val.id+'_anchor';
                                //the objects
                                newArray.push(disabledArray[ind]);
                                unchecked_ids.push(val.id);                                
                                //var node = $('#collectionBrowser').jstree(true).get_node(val.id);
                                //node.accessRestriction = true;                                
                                //$("#collectionBrowser").jstree().enable_node(val.id);
                                //$('#collectionBrowser').jstree(true).refresh_node(val.id)                                
                            }else {
                                disableChkIDArray.push(disabledArray[ind]);
                                disableChkArray.push(val.id+'_anchor');
                            }
                        });
                        //create the anchor ids
                        //var ahrefId = u.id+'_anchor';
                        //newArray.push(ahrefId);
                        //unchecked_ids.push(u.id);
                    });
                   
                    checked_ids = [];
                    
                    var checkedObj = $("#collectionBrowser").jstree("get_checked",true);
                    
                    $.each(checkedObj,function(k,v){
                        if(v.state.checked === true){
                            checked_ids.push(this.id);
                            $("#"+this.id).css('color','black');
                            //remove the unchecked elements, because the jstree unchecked func not 
                            //working always as we expect.
                            
                            checked_ids = checked_ids.filter(function(val) {
                                v.original.userAllowedToDL = true;
                                return unchecked_ids.indexOf(val) == -1;
                            });
                            
                        }
                        $("#"+v.id).css('color','black');
                    });
                    
                    $.each(newArray,function(k,v){
                        $("#"+v.id+"_anchor").css('color','black');
                        $("#collectionBrowser").jstree().enable_checkbox(v.id+"_anchor");
                        $("#collectionBrowser").jstree().enable_node(v.id+"_anchor");
                    });
                    setCookie(insideUri, disableChkIDArray);
                    $("#loader-div").delay(2000).fadeOut("fast");
                    $('#success_login_msg').show(0).delay(2000).fadeOut(1000).hide(0);
                    
                });
                
                ed.preventDefault();
                $("#loader-div").delay(2000).fadeOut("fast");
                hidepopup();
            }); 
           e.preventDefault();
        });
        
        /** check the restriction for the dissemination services  START */
        
        $('#cancelLogin').on('click', function(){
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
    
    function setCookie(cname, cvalue, exdays) {
        var d = new Date();
        d.setTime(d.getTime() + (exdays*24*60*60*1000));
        var expires = "expires="+ d.toUTCString();
        document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
    }

    function getCookie(cname) {
        var name = cname + "=";
        var decodedCookie = decodeURIComponent(document.cookie);
        var ca = decodedCookie.split(';');
        for(var i = 0; i <ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) == ' ') {
                c = c.substring(1);
            }
            if (c.indexOf(name) == 0) {
                return c.substring(name.length, c.length);
            }
        }
        return "";
    }
    
});