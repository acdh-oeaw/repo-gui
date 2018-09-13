var jq2 = jQuery;
jQuery.noConflict(true);
jq2(function( $ ) {
        
        var table = jq2('table.display').DataTable({
           "lengthMenu": [[20, 35, 50, -1], [20, 35, 50, "All"]]
        });
        
        jq2("#loader-div").hide();
        
        //the JS for the inverse table
        jq2( "#showInverse" ).click(function() {
            //show the table
            jq2('#inverseTableDiv').show("slow");
            //hide the button
            jq2('#showInverse').parent().hide("slow");
            //get the uri
            var uri = jq2('#showInverse').data('tableuri');
            //genereate the data
            jq2('table.inverseTable').DataTable({
                "ajax": {
                    "url": "/browser/oeaw_inverse_result/"+uri,
                    "data": function ( d ) {
                        d.limit = d.draw;
                    }
                }
            });
        });
        
        //the JS for the isMember table
        jq2( "#showIsMember" ).click(function() {
            //show the table
            jq2('#isMemberTableDiv').show("slow");
            //hide the button
            jq2('#showIsMember').parent().hide("slow");
            //get the uri            
            var url = jq2('#showIsMember').data('tableurl');    
            //genereate the data
            jq2('table.isMemberTable').DataTable({
                "ajax": {
                    "url": "/browser/oeaw_ismember_result/"+url,
                    "data": function ( d ) {
                        d.limit = d.draw;
                    }
                }
            });
        });
        
        
        /***  PAGINATION START  ****/
        
        var insideUri = jq2('#insideUri').val();
        if(insideUri){
            insideUri = insideUri.replace('id.acdh.oeaw.ac.at/uuid/', '');
        }
        
        
        //check that we have already submitted pagination info
        let searchParams = new URLSearchParams(window.location.href);
        let urlPage = searchParams.get('page');
        let urlLimit = searchParams.get('limit');
        let urlOrder = searchParams.get('order');
               
        
        //check the checkbox values
        var limitSel = jq2('#limit-sel').val();
        if(!limitSel){ limitSel = 10; }
        var orderbySel = jq2('#orderby').val();
        if(!orderbySel){ orderbySel = 'asc'; }
        
        var maxPage = 0;
        maxPage = jq2('#maxPage').val();
        var maxPageLimit = 0;
        
        var limit = 0;
        var page = 0;
        var orderBy = "asc";
        
        // if we have already submitted page and/or limit infos then
        if(urlPage && urlLimit){
            //display the child view
            jq2('#ajax-pagination').show();
            jq2( "#getChildView" ).hide();
            if(!urlOrder){ urlOrder = orderbySel; }
            
            limit = urlLimit;
            page = urlPage;
            orderBy = urlOrder;
            // we check the maxPage and if the actual page is bigger
            // then we change the page
            if( maxPage > 0) {
                maxPageLimit = Math.ceil(maxPage / limit);
                if( page > maxPageLimit) {
                    page = maxPageLimit;
                }
            }
            
            //query the data
            getData(insideUri, limit, page, orderBy);
            
            //change the select values
            jq2('#limit-sel').val(limit);
            jq2('#orderby').val(orderBy);
            
            
        }else{
            page = 1;
            limit = limitSel;
            orderBy = orderbySel;
            jq2('#ajax-pagination').hide();
            //we dont have page and limit passed in the url
         //   getData(insideUri, limitSel, 1, orderbySel);
            jq2('#limit-sel').val(limit);
            jq2('#orderby').val(orderBy);
        }
        
        
        
       /**
         * Do the API request to get the actual child data
         * 
         * @param {type} insideUri
         * @param {type} limit
         * @param {type} page
         * @param {type} orderby
         * @returns {undefined}
         */
        function getData(insideUri, limit, page, orderby) {
            $.ajax({
                url: '/browser/oeaw_child_api/'+insideUri+'/'+limit+'/'+page+'/'+orderby,
                data: {'ajaxCall':true},
                async: true,
                success: function(result){
                    //empty the data div, to display the new informations
                    jq2('#child-div-content').html(result);
                    return false;
                },
                error: function(error){
                    jq2('#child-div-content').html('<div>There is no data</div>');
                    return false;
                }
            });
        }
        
        jq2( "#getChildView" ).click(function(e) {
            jq2("#loader-div").show();
            //drupalSettings.oeaw.detailView.insideUri.page = 1;
            e.preventDefault();            
            jq2('#ajax-pagination').show();
            //if we have a url and page in the URL
            if(urlPage) { page = urlPage; }
            if(urlLimit) { limit = urlLimit; }
            getData(insideUri, limit, page, orderBy);
            //to skip the jump to top function
            jq2( "#getChildView" ).hide();
            createNewUrl();
            jq2("#loader-div").hide();
            return false;
        });
        
        /**
         * create and change the new URL after click events
         * 
         * @type Arguments
         */
        function createNewUrl(){
            if (history.pushState) {
                var path = window.location.pathname;
                var newUrlLimit = "&limit="+limit;
                var newUrlPage = "&page="+page;
                var newUrlOrder = "&order="+orderBy;
                var cleanPath = "";
                if(path.indexOf('&') != -1){
                    cleanPath = path.substring(0, path.indexOf('&'));
                }else {
                    cleanPath = path;
                }
                var newurl = window.location.protocol + "//" + window.location.host + cleanPath + newUrlPage + newUrlLimit + newUrlOrder;
                window.history.pushState({path:newurl},'',newurl);
            }
        }
        
        /***  PAGINATION END  ****/
        
        
        
        jq2('a#delete').click(function(e){ //on add input button click
            
            e.preventDefault();
            var deleteVal = jq2(this).data('resourceid');
            var trParent = jq2(this).parent().parent();
            
            var confirmation = confirm("Are you sure?");
            
            if (confirmation == true) {
                jq2.ajax({
                    url: '/oeaw_delete/'+deleteVal,
                    data: deleteVal,
                    success: function(data, status) {
                        if(data.result == true){
                            trParent.hide();
                        }                    
                        if(data.result == false){
                            alert(data.error_msg);
                        }
                    }
                });
            }else {
                alert("Delete cancelled");
            }
        });
        
        
        jq2('a#delete-root').click(function(e){ //on add input button click
            
            e.preventDefault();
            var deleteVal = jq2(this).data('resourceid');
            
            var confirmation = confirm("Are you sure?");
            
            if (confirmation == true) {
                jq2.ajax({
                    url: '/oeaw_delete/'+deleteVal,
                    data: deleteVal,
                    success: function(data) {
                        if(data.result == true){
                            alert("Delete done!")
                            window.location = "/";
                        }                    
                        if(data.result == false){
                            alert(data.error_msg);
                        }
                    }
                });
            }else {
                alert("Delete cancelled");
            }
        });
        
        jq2('a#delete-rule').click(function(e){ //on add input button click
            
            e.preventDefault();
            var confirmation = confirm("Are you sure you want to Revoke the user rights?");
            
            var deleteVal = jq2(this).data('resourceid');
            var userVal = jq2(this).data('user');
            
            if (confirmation == true) {                
                jq2.ajax({                    
                    url: '/oeaw_revoke/'+deleteVal+'/'+userVal,
                    data: deleteVal,
                    success: function(data) {
                        
                        if(data.result == true){
                            alert("Revoke done!")
                            window.location = "/";
                        }                    
                        if(data.result == false){
                            alert(data.error_msg);
                        }
                    }
                });
            }else {
                alert("Revoke cancelled");
            }
        });
        
});