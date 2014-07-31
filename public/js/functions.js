//body padding 

var bodypad = function () {


if (windowWidth > 700) {
    navheight = $(".menu").height();
} else {
    navheight = $(".mobile-bar").height();
};

bodypadding = $("body").css("padding-top",navheight);

};



//Responsive Menu

var nav = function () {

windowWidth = $(window).width();

if (windowWidth > 700) {
    $(".mobile-menu").css("display", "none");
    $(".menu").css("display", "block");
} else {
    $(".mobile-menu").css("display", "block");
    $(".menu").css("display", "none");
}
$(document).ready(bodypad);
$(window).resize(bodypad);
};
$(document).ready(nav);
$(window).resize(nav);


function toggleMenu() {
    if ($(".drawer").hasClass('hidden')) {
        $(".drawer").removeClass('hidden');
    } else {
        $(".drawer").addClass('hidden');
    }
}
