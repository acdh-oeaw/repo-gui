var jq2 = jQuery;
jQuery.noConflict(true);
jq2(function( $ ) {
    
    
    jq2(document).ready(function() {
			
        //var uid = Drupal.settings.currentUser;
        //var roles = drupalSettings.oeaw.users.roles;
        
        
        //mylink
        
        jq2('#mylink').click(function(){
            alert($(this).attr('href'));
        });
    });
       
        
});