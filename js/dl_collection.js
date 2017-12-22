var jq2 = jQuery;
jQuery.noConflict(true);
jq2(function( $ ) {
    

    function bytesToSize(bytes) {
        var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        if (bytes == 0) return '0 Byte';
        var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
        return Math.round(bytes / Math.pow(1024, i), 2) + ' ' + sizes[i];
    };
        /*
    var tableCollection = jq2('table.collTable').DataTable({
       "lengthMenu": [[20, 35, 50, -1], [20, 35, 50, "All"]]
    });
*/
    jq2(document).ready(function() {
			
        var url = jq2('#insideUri').val();
        
        jq2('#collectionBrowser').on("changed.jstree", function (e, data) {
                if(data.selected.length) {
                    window.location.href = data.instance.get_node(data.selected[0]).original.uri;
                  //  console.log(data.instance.get_node(data.selected[0]).original);
                }
            })
            .jstree({
               /* 'checkbox' : {
                    'keep_selected_style' : false
                },
                'plugins' : [ 'checkbox' ],*/
		'core' : {
                    'data' : {
                        "url" : '/browser/get_collection_data/'+url,
                        "dataType" : "json" 
                    }
		}
	});
    });

    jq2(window).load(function() {
       
    });
/*
    jq2( ".collCheckBox" ).click(function(e) {
        
        var chkArray = [];
        jq2('.collCheckBox:checked').each(function() {
            var res = jq2(this).val().split("/binary=");
            var resArr = [];
            resArr["uri"] = res[0];
            resArr["size"] = res[1];
            chkArray.push(resArr);
	});
        
        var sum = 0;
        jq2.each( chkArray, function( index, value ){
            sum += Number(value['size']);
            jq2("#getCollectionDiv").show();
            jq2("#sumBinaryDiv").show();
            
            if(sum > 1499999999){
                jq2("#sumBinaryDiv").removeClass().addClass('collection_info coll_info_red');
                jq2("#sumBinary").html("<p class='size_text_red'>" + bytesToSize(sum) + "</p> (Max zip download limit is 1.5GB)");
                jq2("#getCollectionDiv").hide();
            }else {
                jq2("#sumBinaryDiv").removeClass().addClass('collection_info coll_info_green');
                jq2("#sumBinary").html("<p class='size_text'>" + bytesToSize(sum)+" </p> (Max zip download limit is 1.5GB)");   
            }
        });

    });

    jq2( "#getCollectionData" ).click(function(e) {
        console.log(jq2('.collCheckBox:checked').serialize());


        jq2('.preloader-wrapper').show();
        //get the uri        
        var url = jq2('.collCheckBox:checked').serialize();
        //prevent Default functionality
        e.preventDefault();
        var Body = jq2('body');
        Body.addClass('preloader-site');

        jq2.ajax({
            url: '/browser/oeaw_dlc/'+url,
            type: "POST",
            data: url,
            tiemout: 1800,
            success: function(data, status) {
                console.log(data);
                console.log(status);
                jq2('.preloader-wrapper').hide();
                jq2('.preloader').hide();
                jq2('#dl_link').html('<a href="'+data+'">DL the ZIP</a>');
                jq2('#dl_link').show();
                return data;

            },
            error: function(message) {
                return message;
            }
        });

    });
        
       */
        
});