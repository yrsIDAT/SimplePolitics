// Box resize 

var boxwidth = function () {

boxwidth = $(".content").width() / 3 - 5;
box3 = $(".box-3").width(boxwidth);

}

$(document).ready(boxwidth);
$(window).resize(boxwidth);

//body padding 

var bodypad = function () {

navheight = $(".menu").height();
bodypadding = $("body").css("padding-top",navheight);

}

$(document).ready(bodypad);
$(window).resize(bodypad);

