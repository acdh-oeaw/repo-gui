var jq2 = jQuery;
jQuery.noConflict(true);
jq2(function( $ ) {
    
       var table = jq2('table.display').DataTable({
            //"pageLength": 25
            searching: false,
            "info": false,
            paging: false
        });
        
        
        //the inverse table setup
        jq2( "#showInverse" ).click(function() {
            //show the table
            jq2('#inverseTableDiv').show("slow");
            //hide the button
            jq2('#showInverse').hide("slow");
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