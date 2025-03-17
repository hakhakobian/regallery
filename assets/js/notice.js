jQuery(document).ready(function () {
  jQuery(document).on("click", ".reacg-notice a", function (e){
    const action_url = jQuery(this).data("action-url");
    const redirect_url = jQuery(this).data("redirect-url");
    const container = jQuery(this).closest(".reacg-notice")
    if ( !redirect_url ) {
      container.remove();
    }
    else {
      jQuery(".spinner").addClass("is-active").show();
    }

    jQuery.ajax({
      type: "GET",
      url: action_url,
      complete: function (data) {
        if ( redirect_url ) {
          container.remove();
          window.open(redirect_url, '_blank').focus();
        }
      }
    });

    return false;
  });
});