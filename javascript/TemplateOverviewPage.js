jQuery(document).ready(
	function () {
		if( jQuery("#classList").length > 0) {
			jQuery(".typo-less").hide();
			jQuery("#classList .typo-seemore").click(
				function() {
					var url = jQuery(this).attr("href");
					var id = jQuery(this).attr("rel");
					jQuery("#" + id).show();
					jQuery("#" + id).html("<li>loading pages ....</li>");
					jQuery("#" + id).load(
						url,
						function() {
							//PrettyPhotoLoader.load("#" + id);
						}
					);
					jQuery(this).parent(".typo-more").hide().next(".typo-less").show().css("display", "block");
					return false;
				}
			);
			jQuery("#classList .typo-seeless").click(
				function() {
					var id = jQuery(this).attr("rel");
					jQuery("#" + id).hide();
					jQuery(this).parent(".typo-less").hide().prev(".typo-more").show().css("display", "block");
					return false;
				}
			);

		}
	}
);

