var jq2 = jQuery;
jQuery.noConflict(true);
jq2(function( $ ) {
    
       var table = jq2('table.display').DataTable({
            //"pageLength": 25
            searching: false,
            "info": false,
            paging: false
        });
        
        
        jq2('a#delete').click(function(e){ //on add input button click
            e.preventDefault();
            var deleteVal = jq2(this).data('resourceid');
            var trParent = jq2(this).parent().parent();
            
            var confirmation = confirm("Are you sure?");
            
            if (confirmation == true) {
                jq2.ajax({
                    url: '/oeaw_delete/'+deleteVal,
                    data: deleteVal,
                    success: function(data) {
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
        
});