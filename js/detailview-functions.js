(function ($, Drupal) {
    Drupal.behaviors.myModuleBehavior = {
        attach: function (context, settings) {
    
            function generateUrlParams(){
                var result = [];
                //check that we have already submitted pagination info
                let searchParams = new URLSearchParams(window.location.href);
                var urlPage = searchParams.get('page');
                var urlLimit = searchParams.get('limit');
                var urlOrder = searchParams.get('order');
                //remove the # sign from the url
                result["urlPage"] = removeSpecialChar(urlPage);
                result["urlLimit"] = removeSpecialChar(urlLimit);
                result["urlOrder"] = removeSpecialChar(urlOrder);


                var insideUri = $('#insideUri').val();
                if(insideUri){
                    insideUri = insideUri.replace('id.acdh.oeaw.ac.at/uuid/', '');
                    result["insideUri"] = insideUri;
                }

                //check the checkbox values
                var limitSel = $('#limit-sel').val();
                if(!limitSel){ limitSel = 10; }
                var orderbySel = $('#orderby').val();
                if(!orderbySel){ orderbySel = 'asc'; }

                result["limitSel"] = limitSel;
                result["orderbySel"] = orderbySel;

                var maxPage = 0;
                maxPage = $('#maxPage').val();
                var maxPageLimit = 0;

                result["maxPage"] = maxPage;  

                var limit = 0;
                var page = 0;
                var orderBy = "asc";

                // if we have already submitted page and/or limit infos then
                if(urlPage && urlLimit){
                    //display the child view
                    $('#ajax-pagination').show();
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
                }else{
                    page = 1;
                    limit = limitSel;
                    orderBy = orderbySel;
                }

                if(page >= maxPageLimit){
                    $( "#next-btn" ).attr('disabled', true);
                    $( "#next-btn" ).removeAttr("href");
                    $( "#last-btn" ).attr('disabled', true);
                    $( "#last-btn" ).removeAttr("href");

                }else{
                    $( "#next-btn" ).attr('disabled', false);
                    $( "#next-btn" ).attr("href");
                    $( "#last-btn" ).attr('disabled', false);
                    $( "#last-btn" ).attr("href");
                }

                if(page === 1){
                    $( "#prev-btn" ).attr('disabled', true);
                    $( "#prev-btn" ).removeAttr("href");
                    $( "#first-btn" ).attr('disabled', true);
                    $( "#first-btn" ).removeAttr("href");
                }else{
                    $( "#prev-btn" ).attr('disabled', false);
                    $( "#prev-btn" ).attr("href");
                    $( "#first-btn" ).attr('disabled', false);
                    $( "#first-btn" ).attr("href");
                }

                 result["maxPageLimit"] = $('#maxPageLimit').val();
                return result;

            }

            if(window.location.href.indexOf("/oeaw_detail/") > -1) {
                $(".loader-div").hide();
                if(window.location.href.indexOf("&page=") > -1) {
                    var urlParams = generateUrlParams();
                    var urlPage = urlParams['urlPage'];
                    var urlLimit = urlParams['urlLimit'];
                    var urlOrder = urlParams['urlOrder'];
                    var maxPageLimit = urlParams['maxPageLimit'];
                    var insideUri = urlParams['insideUri'];

                    getData(insideUri, urlLimit, urlPage, urlOrder);
                }
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
                        $('#child-div-content').html(result);
                        $(".loader-div").hide();
                        $('#limit-sel').val(limit);
                        $('#actualPageSpan').val(page);
                        $('#orderby').val(orderby);
                        return false;
                    },
                    error: function(error){
                        $('#child-div-content').html('<div>There is no data</div>');
                        $(".loader-div").hide();
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
                if ( str && str.indexOf('#') > -1) {
                    str = str.replace('#', '');
                }
                return str;
            }

            /**
             * create and change the new URL after click events
             * 
             * @type Arguments
             */
            function createNewUrl(page, limit, orderBy){
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

            $(document ).delegate( ".getChildView", "click", function(e) {
                //drupalSettings.oeaw.detailView.insideUri.page = 1;
                e.preventDefault();     
                var urlParams = generateUrlParams();
                var urlPage = urlParams['urlPage'];
                var urlLimit = urlParams['urlLimit'];
                var urlOrder = urlParams['urlOrder'];
                var maxPageLimit = urlParams['maxPageLimit'];
                var maxPageLimit = urlParams['maxPageLimit'];
                var insideUri = urlParams['insideUri'];
                var page = 1;
                var limit = 10;
                
                if(urlPage) { page = urlPage; }
                if(!urlPage) { urlPage = 1; }
                if(urlLimit) { limit = urlLimit; }
                
                if(!urlOrder) { urlOrder = "asc"; }
                if(page > maxPageLimit) {
                    page = maxPageLimit;
                }

                $('#ajax-pagination').show();                
                getData(insideUri, limit, page, urlOrder);
                createNewUrl(page, limit, urlOrder);
                $(".loader-div").hide();
                $('#actualPageSpan').val(page);
                //to skip the jump to top function
                return false;
            });

            $(document ).delegate( "#limit-sel", "change", function(e) {            
                e.preventDefault();
                var limit = this.value;
                var urlParams = generateUrlParams();
                var urlPage = urlParams['urlPage'];
                var urlLimit = urlParams['urlLimit'];
                var urlOrder = urlParams['urlOrder'];
                var maxPageLimit = urlParams['maxPageLimit'];
                var maxPage = urlParams['maxPage'];
                var insideUri = urlParams['insideUri'];

                $(".loader-div").show();
                if( maxPage > 0) {
                    maxPageLimit = Math.ceil(maxPage / limit);
                    if( urlPage > maxPageLimit) {
                        urlPage = maxPageLimit;
                    }
                }
                
                getData(insideUri, limit, urlPage, urlOrder);
                createNewUrl(urlPage, limit,urlOrder);
                $(".loader-div").hide();
                $('#actualPageSpan').val(urlPage);
                return false;
            });

            $(document ).delegate( "#orderby", "change", function(e) {            
                e.preventDefault();
                $(".loader-div").show();
                var urlParams = generateUrlParams();
                var urlPage = urlParams['urlPage'];
                var urlLimit = urlParams['urlLimit'];
                var urlOrder = urlParams['urlOrder'];
                var maxPageLimit = urlParams['maxPageLimit'];
                var insideUri = urlParams['insideUri'];
                var orderBy = this.value;

                if(urlPage > maxPageLimit) {
                    urlPage = maxPageLimit;
                }
                getData(insideUri, urlLimit, urlPage, orderBy);
                createNewUrl(urlPage, urlLimit, orderBy );
                $(".loader-div").hide();

                return false;
            });

            $(document ).delegate( "#prev-btn", "click", function(e) {
                e.preventDefault();
                $(".loader-div").show();

                var urlParams = generateUrlParams();
                var urlPage = urlParams['urlPage'];
                var urlLimit = urlParams['urlLimit'];
                var urlOrder = urlParams['urlOrder'];
                var maxPageLimit = urlParams['maxPageLimit'];
                var insideUri = urlParams['insideUri'];

                if(urlPage == 1){
                    $(".loader-div").hide();
                    return false;
                }

                urlPage = urlPage - 1;
                if(urlPage < 1){ urlPage = 1; }
                getData(insideUri, urlLimit, urlPage, urlOrder);
                $('#actualPageSpan').html(urlPage);                
                createNewUrl(urlPage, urlLimit, urlOrder );
                $(".loader-div").hide();
                //to skip the jump to top function
                return false;
            });

            $(document ).delegate( "#last-btn", "click", function(e) {            
                e.preventDefault();
                $(".loader-div").show();
                var urlParams = generateUrlParams();
                var urlPage = urlParams['urlPage'];
                var urlLimit = urlParams['urlLimit'];
                var urlOrder = urlParams['urlOrder'];
                var maxPageLimit = urlParams['maxPageLimit'];
                var maxPage = urlParams['maxPage'];
                var insideUri = urlParams['insideUri'];
                urlPage = maxPageLimit;
                getData(insideUri, urlLimit, urlPage, urlOrder);
                $('#actualPageSpan').html(urlPage);
                createNewUrl(urlPage, urlLimit, urlOrder);
                $(".loader-div").hide();
                //to skip the jump to top function
                return false;
            });

            $(document ).delegate( "#first-btn", "click", function(e) {            
                e.preventDefault();
                $(".loader-div").show();
                var urlParams = generateUrlParams();
                var urlPage = urlParams['urlPage'];
                var urlLimit = urlParams['urlLimit'];
                var urlOrder = urlParams['urlOrder'];
                var maxPageLimit = urlParams['maxPageLimit'];
                var insideUri = urlParams['insideUri'];

                urlPage = 1;
                getData(insideUri, urlLimit, urlPage, urlOrder);
                $('#actualPageSpan').html(urlPage);
                createNewUrl(urlPage, urlLimit, urlOrder);
                $(".loader-div").hide();
                //to skip the jump to top function
                return false;
            });

            $(document ).delegate( "#next-btn", "click", function(e) {            
                $(".loader-div").show();
                e.preventDefault();

                var urlParams = generateUrlParams();
                var urlPage = urlParams['urlPage'];
                var urlLimit = urlParams['urlLimit'];
                var urlOrder = urlParams['urlOrder'];
                var maxPageLimit = urlParams['maxPageLimit'];
                var insideUri = urlParams['insideUri'];

                if ($(this).hasClass('disabled')){
                    $(".loader-div").hide();
                    return false;
                }

                if(maxPageLimit == parseInt(urlPage) + 1){
                    urlPage = parseInt(urlPage) + 1;
                    $( "#next-btn" ).addClass('disabled');
                    getData(insideUri, urlLimit, urlPage, urlOrder);
                }else if (maxPageLimit == urlPage) {
                    $( "#next-btn" ).addClass('disabled');
                    $(".loader-div").hide();
                    return false;
                }else {
                    $( "#next-btn" ).removeClass('disabled');                
                    urlPage = parseInt(urlPage) + 1;
                    getData(insideUri, urlLimit, urlPage, urlOrder);
                }
                $('#actualPageSpan').html(urlPage);
                createNewUrl(urlPage, urlLimit, urlOrder);
                $(".loader-div").delay(2000).fadeOut("fast");
                $(".loader-div").hide();
                //to skip the jump to top function
                return false;
            });

            /*  PAGINATION END  */

            /* CHILD VIEW SHOW SUMMARY START */

            $(document ).delegate( ".res-act-button-summary", "click", function(e) {
                if ($(this).hasClass('closed')) {
                    $(this).parent().siblings('.res-property-desc').fadeIn(200);
                    $(this).removeClass('closed');
                    $(this).addClass('open');
                    $(this).children('i').text('remove');
                    $(this).children('span').text('Hide Summary');
                } else {
                    $(this).parent().siblings('.res-property-desc').fadeOut(200);
                    $(this).removeClass('open');
                    $(this).addClass('closed');
                    $(this).children('i').text('add');
                    $(this).children('span').text('Show Summary');		
                }
            });
            /* CHILD VIEW SHOW SUMMARY END */


            /* SWITCH LIST OR TREE START */

            $(document ).delegate( ".res-act-button-treeview", "click", function(e) {

                if ($(this).hasClass('basic')) {
                    console.log("tree view begin");
                    $('.children-overview-basic').hide();
                    $('.child-ajax-pagination').hide();
                    $('.children-overview-tree').fadeIn(200);
                    $(this).removeClass('basic');
                    $(this).addClass('tree');
                    $(this).children('span').text('Switch to List-View');
                    //get the data
                    var url = $('#insideUri').val();
                    if(url){
                        
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
                            search: {
                                case_sensitive: false,
                                show_only_matches: true
                            },
                            plugins : [ 'search' ]
                        });
                        
                        $('#collectionBrowser')
                        //handle the node clicking to download the file
                        .bind("click.jstree", function (node, data) {
                            if(node.originalEvent.target.id) {
                                var node = $('#collectionBrowser').jstree(true).get_node(node.originalEvent.target.id);
                                if(node.original.encodedUri){
                                    window.location.href = "/browser/oeaw_detail/"+node.original.encodedUri;
                                }
                            }
                        });
                    }
                } else {
                    $('.children-overview-tree').hide();
                    $('.child-ajax-pagination').fadeIn(200);
                    $('.children-overview-basic').fadeIn(200);
                    $(this).removeClass('tree');
                    $(this).addClass('basic');
                    $(this).children('span').text('Switch to Tree-View');		
                }
            });
            /* SWITCH LIST OR TREE END */
            $(".loader-div").hide();
    
        }
    };
})(jQuery, Drupal);