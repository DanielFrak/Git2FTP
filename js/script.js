$( document ).ready(function() {
    $("#listOfFiles").hide();
});

$(document).on("change","#sendFromText",function(){
    if ($(this).is(':checked')) {
        $("#listOfFiles").slideDown().fadeIn();
    } else {
        $("#listOfFiles").slideUp().fadeOut();
    }
});
