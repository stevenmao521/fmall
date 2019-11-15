$(document).ready(function(e) {
	//	$(".buy-btn").on("touchend", function(e) {
	//		$("#buy-fail").show();
	//	})
	//	$("#buy-fail .close").on("touchend", function(e) {
	//		$("#buy-fail").hide();
	//	})

	$(".buy-btn").on("touchend", function(e) {
		event.preventDefault();
		$("#buy-succ").show();
	})
	$("#buy-succ .close").on("touchend", function(e) {
		$("#buy-succ").hide();
	})

})