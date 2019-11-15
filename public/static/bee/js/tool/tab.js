$(document).ready(function(e) {

	$("#tabs-nav li").on("click", function(e) {
		$(this).addClass("active").siblings().removeClass("active");
		$("#tabs-content").children(".panel").eq($(this).index()).addClass("active").siblings().removeClass("active");
	})
})