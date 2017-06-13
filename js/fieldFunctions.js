(function ($, Drupal, window, document) {
    Drupal.behaviors.basic = {
        attach: function (context, settings) {

            $(document).ready(function () {
                                
                //get the actual page
                var url = window.location.href;
                //if the user is on the edit view then
                if (url.toLowerCase().indexOf("/oeaw_edit/") >= 0){
                    
                    var newArray = new Array();
                    //input contains maxcardinality
                    //and has some empty fields then we need to hide those input fields.
                    $("input[data-maxcardinality]").each(function(){
                        newArray.push($(this));
                    });
                    jQuery.each(newArray, function(index, item) {
                        var id = item.attr('id');                        
                        //we dont want to hide the first input
                        if(id.match(".*\\d.*")){
                            var itemValue = $('#'+id).val();
                            if(!itemValue){
                                $('#'+id).parent().hide();
                                $('#'+id).val("");
                            }
                        }
                    });
                }
                
                //The user is on the adding view
                if (url.toLowerCase().indexOf("oeaw_newresource_two") >= 0){
                    var newArray = new Array();
                    $("input[data-fieldhidden='yes']").each(function(){
                        newArray.push($(this));
                        });
                    jQuery.each(newArray, function(index, item) {
                        var id = item.attr('id');
                        var itemValue = $('#'+id).val();
                        if(!itemValue){
                            $('#'+id).parent().hide();
                            $('#'+id).val("");
                        }
                    });                                       
                }
                
                //edit or add view with click event
                if((url.toLowerCase().indexOf("/oeaw_edit/") >= 0 || url.toLowerCase().indexOf("oeaw_newresource_two") >= 0)){
                    //get the a click event
                    $("a").unbind('click').bind('click', function(e){
                        //get the id
                        var str = $(this).attr("id");
                        //check the clicked add/remove button
                        if (str.toLowerCase().indexOf("plus") >= 0){
                            e.preventDefault();
                            var str2 = str.replace('-plus', '');

                            //the sum of the input fields
                            var indexOfTheInput = $('input[id^="edit-'+str2+'"]').length;
                            
                            //the last div is already available
                            if($("div[class*='js-form-item-"+str2+"-"+indexOfTheInput+"']").css('display') == 'block'){
                                alert("You can't add more fields!");
                                return false;
                            }
                            //if we have existing divs then add them to an array
                            if($("div[class*='js-form-item-"+str2+"']").length > 0){

                                var plusArray = new Array();
                                $("div[class*='js-form-item-"+str2+"']").each(function(){
                                    plusArray.push($(this));
                                });
                                //show the last hidden div
                                jQuery.each(plusArray, function(index, item) {
                                    if(item.css('display') == 'none'){
                                        var elemClass = item.attr('class');
                                        $("div[class='"+elemClass+"']").css('display', 'block');
                                        return false;
                                    }
                                });
                            }
                        }
                        
                        if (str.toLowerCase().indexOf("minus") >= 0){
                            e.preventDefault();
                            var str2 = str.replace('-minus', '');
                            var succ = false;
                            //the sum of the input fields
                            var indexOfTheInput = $('input[id^="edit-'+str2+'"]').length;
                            
                            if($("div[class*='js-form-item-"+str2+"']").length > 0){
                                var minusArray = new Array();
                                //add the divs to an array
                                $("div[class*='js-form-item-"+str2+"-']").each(function(){
                                    minusArray.push($(this));
                                });
                                //loopon the array backwards because we need to remove the last displayed element
                                for (var i = indexOfTheInput; i > 0; i--) {
                                    jQuery.each(minusArray, function(index, item) {
                                        
                                        //if we showed the last element then we can exit from the loop
                                        if(succ === true){
                                            return false;
                                        }
                                        //if the checked div has the actual class
                                        if(item.hasClass('js-form-item-'+str2+'-'+i)){
                                            
                                            //and it is displayed
                                            if(item.css('display') == 'block'){
                                                //then we need to hide it and set the success to true
                                                var elemClass = item.attr('class');
                                                $("div[class='"+elemClass+"']").css('display', 'none');
                                                //remove the value of the removed input field
                                                $("input#edit-"+str2+'-'+i).val("");
                                                succ = true;
                                            }
                                        }
                                    });
                                }
                            }
                        }
                    });
                }
            });
            
            
            
            
            /*
            $("form#multistep-form-two").submit(function( event ) {
                $("form#multistep-form-two :input").each(function(){
                var input = $(this); // This is the jquery object of the input, do what you will
                
                });
                event.preventDefault();
            });*/
        }
    };
}(jQuery, Drupal, this, this.document));