// Box resize 

var boxwidth = function () {

boxwidth = $(".content").width() / 3 - 5;
box3 = $(".box-3").width(boxwidth);

}

$(document).ready(boxwidth);
$(window).resize(boxwidth);