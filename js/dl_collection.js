//var $ = jQuery;
//jQuery.noConflict(true);
//$(function( $ ) {

/*
(function ($, Drupal) {
    Drupal.behaviors.myModuleBehavior = {
        attach: function (context, settings) {    
  */
 jQuery(function($) {
    "use strict";
    var selectedItems = [];
    var disableChkUrlArray = [];
    var checked_ids = []; 
    var unchecked_ids = []; 
    
    var disableChkArray = [];
    var disableChkIDArray = [];
        
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
                        if(  ((v != 'public') &&  v != actualUserRestriction) && actualUserRestriction != 'admin'){
                            if( userAllowedToDL === false){
                                disableChkArray.push(key+'_anchor');
                                disableChkUrlArray.push(value.original.uri);
                                var obj = {};
                                obj = {"id": value.id, "url": value.original.uri};
                                disableChkIDArray.push(obj);
                                $("#"+value.id).css('color','red');
                                $("#collectionBrowser").jstree("uncheck_node", value.id);
                                $("#collectionBrowser").jstree().disable_node(value.id);
                            }
                        }
                    }
                    userAllowedToDL = false;
                });
            });
        });
    }
    
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
        
        var url = $('#insideUri').val();
        window.setTimeout( generateCollection(url), 5000 );
        
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
                    if( ((resourceRestriction != 'public') &&  resourceRestriction == actualUserRestriction) || actualUserRestriction == 'admin' ){
                        $('#not_enough_permission').hide();
                        window.location.href = data.instance.get_node(data.selected[0]).original.uri;
                    } else if( resourceRestriction == 'public'){
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
                    $("#collectionBrowser").jstree("uncheck_node", value);
                    $("#collectionBrowser").jstree().disable_node(value);
                });
                $('#not_enough_permission').show();
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
                    $("#collectionBrowser").jstree("uncheck_node", value.replace('_anchor', ''));
                    $("#collectionBrowser").jstree().disable_node(value.replace('_anchor', ''));
                });
                $('#not_enough_permission').show();
            }
            
            
            sumSize = 0;
            
            if(data.instance.get_checked(true)) {
                
                selectedItems = [];
                var actualResource = data.instance.get_checked(true);
                var actualResourcefalse = data.instance.get_checked(false);
                
                $.each( actualResource, function( i, res ){
                    if( res ){
                        var id = res.id;
                        var size = res.original.binarySize;
                        var uri = res.original.uri;
                        var uri_dl = res.original.encodedUri;
                        var filename = res.original.filename;
                        var resourceRestriction = "public";
                        if(res.original.hasOwnProperty("accessRestriction")){
                            resourceRestriction = res.original.accessRestriction;
                        }
                        $("#collectionBrowser").jstree().enable_node(id);
                        $("#"+id).css('color','black');
                        //check the rights
                        if( ((resourceRestriction != 'public') &&  resourceRestriction != actualUserRestriction) && actualUserRestriction != 'admin' ){
                            //if the user doesnt have any rights on the resource then we will make it unreachable to download
                            $('#not_enough_permission').show();
                        }
                        if(size && uri){
                        //if( ((resourceRestriction == 'public') &&  resourceRestriction == actualUserRestriction) || actualUserRestriction == 'admin' ){
                            selectedItems.push({id: id, size: size, uri: uri, uri_dl: uri_dl, filename: filename});
                            sumSize += Number(size);
                            if(sumSize > 1599999999){
                                $("#selected_files_size").html("<p class='size_text_red'>" + bytesToSize(sumSize) + " ("+Drupal.t('Max tar download limit is') + " 1.5GB)</p> ");
                                $("#getCollectionDiv").hide();
                            }else {
                                $("#selected_files_size").html("<p class='size_text'>" + bytesToSize(sumSize)+" ("+Drupal.t('Max tar download limit is') + " 1.5GB) </p> ");   
                                $("#getCollectionDiv").show();
                            }
                        //}
                        }
                    }else {
                            sumSize = 0;
                        }
                });    
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
                myObj[index] = resArr;
            });
            
            $.ajax({
                url: '/browser/oeaw_dlc/'+insideUri,
                type: "POST",
                data: {jsonData : JSON.stringify(myObj)},
                tiemout: 1800,
                success: function(data, status) {
                    $('#dl_link_a').html('<a href="'+data+'" target="_blank">'+Drupal.t("Download Collection")+'</a>');
                    $('#dl_link').show();
                    $('#dl_link_txt').show();
                    $("#loader-div").delay(2000).fadeOut("fast");
                    $("#getCollectionDiv").hide();
                    return data;

                },
                error: function(message) {
                    $("#loader-div").delay(2000).fadeOut("fast");
                    console.log(message);
                    $("#selected_files_size").html("<p class='size_text_red'>" + Drupal.t('A server error has occurred. ') + " </p> ");
                    return message;
                }
            });

        });
        
    
        $('#loginToRestrictedResources').on('click', function(e){    
            showpopup();
            $('#dologin').on('click', function(ed){
                var username = $("input#username").val();
                var password = $("input#password").val();
                var counter = 0;
                checkResourceAccess(disableChkIDArray, username, password, function(newData) {
                    
                    if(newData === false){
                        counter++;
                        if(counter > 1){
                            return false;
                        }else {
                            $('#error_login_msg').show(0).delay(4000).fadeOut(1000).hide(0);
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
                    
                    $.each(unchecked_ids,function(k,v){
                        //remove the unchecked elements, because the jstree unchecked func not 
                        //working always as we expect.
                        $("#"+v).css('color','red');
                        $("#collectionBrowser").jstree("uncheck_node", v);
                        $("#collectionBrowser").jstree().disable_node(v);
                    });
                    disableChkArray = newArray;
                    $('#success_login_msg').show(0).delay(2000).fadeOut(1000).hide(0);
                });
                ed.preventDefault();
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
        
      /*  
        }
    };
})(jQuery, Drupal);
*/
});