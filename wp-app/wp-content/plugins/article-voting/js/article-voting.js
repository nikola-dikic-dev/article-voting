jQuery( document ).ready(
	function ($) {
		$( ".vote-button" ).click(
			function () {
				var vote    = $( this ).data( "vote" );
				var post_id = $( this ).closest( ".article-voting" ).data( "post-id" );
				var nonce   = $( this ).data( "nonce" );
				$.ajax(
					{
						url: article_voting_ajax.ajax_url,
						type: "post",
						data: {
							action: "submit_vote",
							vote: vote,
							post_id: post_id,
							nonce: nonce,
						},
						success: function (response) {
							if (response.success) {
								$( ".article-voting" ).html( response.data );
							} else {
								alert( response.data );
							}
						},
					}
				);
			}
		);
	}
);
