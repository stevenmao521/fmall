$(document).ready(function(e) {

	$(".account-form-eye").click(function(e) {
		if($(this).hasClass("showpass")) {
			$("#password").attr("type", "text");
			$(this).removeClass("showpass");
		} else {
			$("#password").attr("type", "password");
			$(this).addClass("showpass");
		}
	})

})

function resultFactory() {
	var obj = new Object();
	obj.validate = false;
	obj.msg = '';
	return obj;
}

function isMobile(value) {
	var result = resultFactory();
	var len = value ? value.length : 0;
	var mobile = /^(13[0-9]{9})|(18[0-9]{9})|(14[0-9]{9})|(17[0-9]{9})|(15[0-9]{9})$/;
	if(len == 11 && mobile.test(value))(
		result.validate = true
	)
	else {
		result.msg = "手机号格式有误"
	}
	return result;
}

function isName(value) {
	var result = resultFactory();
	var len = value ? value.length : 0;
	if(len >= 6)(
		result.validate = true
	)
	return result;
}

function isPass(value) {
	var result = resultFactory();
	var len = value ? value.length : 0;
	if(len >= 6)(
		result.validate = true
	)
	return result;
}

function isShort(value) {
	var result = resultFactory();
	var len = value.length;
	if(len >= 4)(
		result.validate = true
	)
	return result;
}

function isMinLength(value, len) {
	if(value && value.toString().length >= parseInt(len)) {
		return true
	}
	return false
}