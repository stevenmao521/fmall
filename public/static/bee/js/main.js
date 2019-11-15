(function(window, document) {
	var docEl = document.documentElement
	var dpr = window.devicePixelRatio || 1
	var option = docEl.getAttribute('data-use-rem')
	// adjust body font size
	function setBodyFontSize() {
		if(document.body) {
			document.body.style.fontSize = (12 * dpr) + 'px'
		} else {
			document.addEventListener('DOMContentLoaded', setBodyFontSize)
		}
	}
	setBodyFontSize();
	// set 1rem = viewWidth / 10
	function setRemUnit() {
		var rem = docEl.clientWidth / 10
		if(option) {
			// 保证rem与设计稿之间的比例恒为100
			var grids = option / 100
			rem = docEl.clientWidth / grids
		}
		docEl.style.fontSize = rem + 'px'
	}
	setRemUnit()
	// reset rem unit on page resize
	window.addEventListener('resize', setRemUnit)
	window.addEventListener('pageshow', function(e) {
		if(e.persisted) {
			setRemUnit()
		}
	})
}(window, document))

//创建缺省页面
function createrDefault(content) {
	var content = (typeof content == "string") ? content : "暂无记录";
	var bodys = "<div class='default-warp'><img class='default-img' src='img/default.png' /><div class='default-txt'>" + content + "</div></div>"
	$("body").html(bodys);
}
