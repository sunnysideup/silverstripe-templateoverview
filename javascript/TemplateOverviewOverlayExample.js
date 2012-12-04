;(function($) {
	$(document).ready(
		function() {
			templateoverviewoverlay.init();
		}
	);
})(jQuery);


var templateoverviewoverlay = {

	availablePageClasses: function () {
		return new Array(
			"HomePage",
			"TypographyTestPage"
		);
	},

	linkSelector: ".overlayLink",

	init: function() {
		var classNameList = templateoverviewoverlay.availablePageClasses();
		jQuery(templateoverviewoverlay.linkSelector).hide();
		for(var i = 0; i < classNameList.length; i++) {
			jQuery("#overlayFor" + classNameList[i]).show();
		}
	},

	addExtension: function (className) {
		switch(className) {

			case "HomePage":
				jQuery("#Container").prepend('<div style="position: absolute; width: 100%; height: 3000px; "><img style="position: absolute; z-index: 999999; opacity: 0.35; left: 0; margin-left: -1px " src="http://www.mysite.com/assets/templates/HomePage.png"/></div>');
				alert("you are looking at the homepage");
				break;

			case "TypographyTestPage":
				jQuery('body').prepend('<div style="position: absolute; width: 100%; height: 3000px; "><img style="position: absolute; z-index: 999999; opacity: 0.35; left: 0pt; margin-left: -72px;" src="http://www.mysite.com/assets/Uploads/TypographyTestPage.jpg"/></div>');
				break;

			default:
				alert("there is no overlay for this page");
		}
	}

}