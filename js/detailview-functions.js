var jq2 = jQuery;
jq2(function( $ ) {
    jq2(".loader-div").hide();

    /***  PAGINATION START  ****/
    //get the identifier from the url
    var insideUri = jq2('#insideUri').val();
    insideUri = insideUri.replace('id.acdh.oeaw.ac.at/uuid/', '');

    //check that we have already submitted pagination info
    let searchParams = new URLSearchParams(window.location.href);
    var urlPage = searchParams.get('page');
    var urlLimit = searchParams.get('limit');
    var urlOrder = searchParams.get('order');
    //remove the # sign from the url
    urlPage = removeSpecialChar(urlPage);
    urlLimit = removeSpecialChar(urlLimit);
    urlOrder = removeSpecialChar(urlOrder);

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
        //change the select values
        jq2('#limit-sel').val(limit);
        jq2('#orderby').val(orderBy);


    }else{
        page = 1;
        limit = limitSel;
        orderBy = orderbySel;
        //we dont have page and limit passed in the url
       // getData(insideUri, limitSel, 1, orderbySel);
        jq2('#limit-sel').val(limit);
        jq2('#orderby').val(orderBy);
    }

            if(page >= maxPageLimit){
                jq2( "#next-btn" ).attr('disabled', true);
                jq2( "#next-btn" ).removeAttr("href");
                jq2( "#last-btn" ).attr('disabled', true);
                jq2( "#last-btn" ).removeAttr("href");
                
            }else{
                jq2( "#next-btn" ).attr('disabled', false);
                jq2( "#next-btn" ).attr("href");
                jq2( "#last-btn" ).attr('disabled', false);
                jq2( "#last-btn" ).attr("href");
            }
            
            if(page === 1){
                jq2( "#prev-btn" ).attr('disabled', true);
                jq2( "#prev-btn" ).removeAttr("href");
                jq2( "#first-btn" ).attr('disabled', true);
                jq2( "#first-btn" ).removeAttr("href");
            }else{
                jq2( "#prev-btn" ).attr('disabled', false);
                jq2( "#prev-btn" ).attr("href");
                jq2( "#first-btn" ).attr('disabled', false);
                jq2( "#first-btn" ).attr("href");
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
                        jq2(".loader-div").hide();
                        return false;
                    },
                    error: function(error){
                        jq2('#child-div-content').html('<div>There is no data</div>');
                        jq2(".loader-div").hide();
                        return false;
                    }
                });
            }
            
            /**
             * Remove the # sign from the url
             * 
             * @param {type} str
             * @returns string
             */
            function removeSpecialChar(str){
                if (str.indexOf('#') > -1) {
                    str = str.replace('#', '');
                }
                return str;
            }

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

            jq2(document ).delegate( ".getChildView", "click", function(e) {
            //jq2( "#getChildView" ).click(function(e) {
                //drupalSettings.oeaw.detailView.insideUri.page = 1;
                e.preventDefault();            
                if(urlPage) { page = urlPage; }
                if(urlLimit) { limit = urlLimit; }
                if(page > maxPageLimit) {
                    page = maxPageLimit;
                }
                jq2('#ajax-pagination').show();
                getData(insideUri, limit, page, orderBy);
                createNewUrl();
                jq2(".loader-div").hide();
                //to skip the jump to top function
                return false;
            });

            jq2(document ).delegate( "#limit-sel", "change", function(e) {            
                e.preventDefault();
                limit = this.value;
                jq2(".loader-div").show();
                if( maxPage > 0) {
                    maxPageLimit = Math.ceil(maxPage / limit);
                    if( page > maxPageLimit) {
                        page = maxPageLimit;
                    }
                }
                getData(insideUri, limit, page, orderBy);
                createNewUrl();
                jq2(".loader-div").hide();
                return false;
            });

            jq2(document ).delegate( "#orderby", "change", function(e) {            
                e.preventDefault();
                jq2(".loader-div").show();
                orderBy = this.value;
                if(page > maxPageLimit) {
                    page = maxPageLimit;
                }
                getData(insideUri, limit, page, orderBy);
                createNewUrl();
                jq2(".loader-div").hide();
                return false;
            });

            jq2(document ).delegate( "#prev-btn", "click", function(e) {
                e.preventDefault();
                jq2(".loader-div").show();
                if(page == 1){
                    jq2(".loader-div").hide();
                    return false;
                }

                page = page - 1;
                if(page < 1){ page = 1; }
                getData(insideUri, limit, page, orderBy);
                createNewUrl();
                jq2(".loader-div").hide();
                //to skip the jump to top function
                return false;
            });

            jq2(document ).delegate( "#last-btn", "click", function(e) {            
                e.preventDefault();
                jq2(".loader-div").show();
                page = maxPageLimit;
                getData(insideUri, limit, page, orderBy);
                createNewUrl();
                jq2(".loader-div").hide();
                //to skip the jump to top function
                return false;
            });

            jq2(document ).delegate( "#first-btn", "click", function(e) {            
                e.preventDefault();
                jq2(".loader-div").show();
                page = 1;
                getData(insideUri, limit, page, orderBy);
                createNewUrl();
                jq2(".loader-div").hide();
                //to skip the jump to top function
                return false;
            });

            jq2(document ).delegate( "#next-btn", "click", function(e) {            
                jq2(".loader-div").show();
                e.preventDefault();

                if ($(this).hasClass('disabled')){
                    jq2(".loader-div").hide();
                    return false;
                }

                if(maxPageLimit == parseInt(page) + 1){
                    page = parseInt(page) + 1;
                    jq2( "#next-btn" ).addClass('disabled');
                    getData(insideUri, limit, page, orderBy);
                }else if (maxPageLimit == page) {
                    jq2( "#next-btn" ).addClass('disabled');
                    jq2(".loader-div").hide();
                    return false;
                }else {
                    jq2( "#next-btn" ).removeClass('disabled');                
                    page = parseInt(page) + 1;
                    getData(insideUri, limit, page, orderBy);
                }
                createNewUrl();
                jq2(".loader-div").delay(2000).fadeOut("fast");
                jq2(".loader-div").hide();
                //to skip the jump to top function
                return false;
            });

            /*  PAGINATION END  */

            /* CHILD VIEW SHOW SUMMARY START */
            
            jq2(document ).delegate( ".res-act-button-summary", "click", function(e) {
                if (jq2(this).hasClass('closed')) {
                    jq2(this).parent().siblings('.res-property-desc').fadeIn(200);
                    jq2(this).removeClass('closed');
                    jq2(this).addClass('open');
                    jq2(this).children('i').text('remove');
                    jq2(this).children('span').text('Hide Summary');
                } else {
                    jq2(this).parent().siblings('.res-property-desc').fadeOut(200);
                    jq2(this).removeClass('open');
                    jq2(this).addClass('closed');
                    jq2(this).children('i').text('add');
                    jq2(this).children('span').text('Show Summary');		
                }
            });
            /* CHILD VIEW SHOW SUMMARY END */


            /* SWITCH LIST OR TREE START */

            jq2(document ).delegate( ".res-act-button-treeview", "click", function(e) {

                if ($(this).hasClass('basic')) {
                    jq2('.children-overview-basic').hide();
                    jq2('.children-overview-tree').fadeIn(200);
                    jq2(this).removeClass('basic');
                    jq2(this).addClass('tree');
                    jq2(this).children('span').text('Switch to List-View');
                    //get the data
                    var url = jq2('#insideUri').val();
                    if(url){
                        jq2('#collectionBrowser')
                        .jstree({
                            core : {
                                data : {
                                    "url" : '/browser/get_collection_data/'+url,
                                    "dataType" : "json" 
                                }
                            }
                        })
                        //handle the node clicking to download the file
                        .on("changed.jstree", function (node, data) {
                            if(data.instance.get_node(data.selected[0]).original.encodedUri) {
                                window.location.href = "/browser/oeaw_detail/"+data.instance.get_node(data.selected[0]).original.encodedUri;
                            }
                        });
                    }
                } else {
                    jq2('.children-overview-tree').hide();
                    jq2('.children-overview-basic').fadeIn(200);
                    jq2(this).removeClass('tree');
                    jq2(this).addClass('basic');
                    jq2(this).children('span').text('Switch to Tree-View');		
                }
            });
            /* SWITCH LIST OR TREE END */
            jq2(".loader-div").hide();
        });