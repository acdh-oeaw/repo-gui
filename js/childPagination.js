var jq = jQuery;
jQuery.noConflict(true);
jq(function( $ ) {
        
        /***  PAGINATION START  ****/
        jq('#ajax-pagination').hide();
        var insideUri = jq('#insideUri').val();
        var limit = jq('#limit-sel').val();
        
        if(!limit){ limit = 10; }
        
        var orderby = jq('#orderby').val();
        if(!orderby){ orderby = 'asc'; }
        insideUri = insideUri.replace('id.acdh.oeaw.ac.at/uuid/', '');
        var insideUriCache = "dV"+insideUri;
        
        let searchParams = new URLSearchParams(window.location.href);
        let param = searchParams.get('page');
        let param2 = searchParams.get('limit');
        
        console.log(searchParams);
        console.log(param);
        console.log(param2);
        
        //var oeawObj = drupalSettings.oeaw;
        var page = 1;
        var maxPage = 0;
        /*$.each( oeawObj, function( key, value ) {
            if(key == insideUriCache){
                //page = value.page;
                console.log(value);
            }
        });
        */
        if(!page){page = 1 }
        
        function getData(insideUri, limit, page, orderby) {
            
            console.log('/browser/oeaw_child_api/'+insideUri+'/'+limit+'/'+page+'/'+orderby);
            
            $.ajax({
                url: '/browser/oeaw_child_api/'+insideUri+'/'+limit+'/'+page+'/'+orderby,
                data: {'ajaxCall':true},
                async: false,
                success: function(result){
                    //empty the data div, to display the new informations
                    
                    jq('#child-div-content').html(result);
                    /*
                    console.log(result);
                    jq('#child-data-content').empty();
                    maxPage = result.maxPage;
                    jq('#maxPage').val(maxPage);
                    jq('<div class="children-overview children-overview-basic" id="children-overview-basic">').appendTo('#child-data-content');
                    
                    
                    $.each(result.childResult, function(i, item) {
                        jq('<div class="child-res" id="child-res">').html('').appendTo('#children-overview-basic');
                        jq('<div class="res-property">').html( item.title ).appendTo('#child-res');
                        jq('<div class="res-property">').html( item.types.replace('https://vocabs.acdh.oeaw.ac.at/schema#', '')  ).appendTo('#child-res');
                        //jq('</div>').appendTo('#children-overview-basic');
                        //jq('<tr>').html("<td>" + item.title + "</td><td>" + item.types.replace('https://vocabs.acdh.oeaw.ac.at/schema#', '') + "</td><td>" + item.description + "</td>").appendTo('#child-data-content');
                    });
                    
                    
                    jq('</div>').appendTo('#child-data-content');
                    */
                },
                error: function(error){
                    jq('<tr>').html("<td>There is no data</td>").appendTo('#child-data-content');
                }
            });
        }
        
        jq( "#getChildView" ).click(function(e) {
            //drupalSettings.oeaw.detailView.insideUri.page = 1;
            e.preventDefault();
            jq('#ajax-pagination').show();
            getData(insideUri, limit, page, orderby);
            //to skip the jump to top function
            return false;
        });
        
        jq('#limit-sel').on('change', function(e) {
            e.preventDefault();
            getData(insideUri, this.value, page, orderby);
            return false;
        });
        
        jq('#orderby').on('change', function(e) {
            e.preventDefault();
            return false;
        });
        
        jq( "#prev-btn" ).click(function(e) {
            e.preventDefault();
                        
            if(page == 1){
                return false;
            }
            
            page = page - 1;
            if(page < 1){ page = 1; }
            getData(insideUri, limit, page, orderby);
            //to skip the jump to top function
            return false;
        });
        
        jq( "#last-btn" ).click(function(e) {
            e.preventDefault();
            var mxp = jq('#maxPage').val();
            page = Math.ceil(mxp / limit);
            getData(insideUri, limit, page, orderby);
            //to skip the jump to top function
            return false;
        });
        
        jq( "#first-btn" ).click(function(e) {
            alert("a next button ban");
            e.preventDefault();
            page = 1;
            getData(insideUri, limit, page, orderby);
            //to skip the jump to top function
            return false;
        });
        
        jq( "#next-btn" ).click(function(e) {
            alert("a next button ban");
            
            console.log("next btn");
            
            if (history.pushState) {
                var newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?myNewUrlQuery=1';
                console.log(newurl);
                window.history.pushState({path:newurl},'',newurl);
            }
            
            e.preventDefault();
            
            if ($(this).hasClass('disabled')){
                return false;
            }
            
            var mxp = jq('#maxPage').val();
            var lastPage = Math.ceil(mxp / limit);
            
            if(lastPage == page + 1){
                page = page + 1;
                jq( "#next-btn" ).addClass('disabled');
                getData(insideUri, limit, page, orderby);
            }else if (lastPage == page) {
                jq( "#next-btn" ).addClass('disabled');
                return false;
            }else {
                jq( "#next-btn" ).removeClass('disabled');
                page = page + 1;
                getData(insideUri, limit, page, orderby);
            }
            //to skip the jump to top function
            return false;
        });
        
        /***  PAGINATION END  ****/
    
        
});