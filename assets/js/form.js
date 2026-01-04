jQuery(document).ready(function () {
  jQuery(document).on("click", "a[href='reacg-book-a-call']", function (e){
    jQuery(".reacg-form-popup-overlay").show();

    return false;
  });
  jQuery(document).on("click", ".reacg-form-popup .reacg-submit", function (e){
    if ( !jQuery(".reacg-form-popup .reacg-agreement").prop("checked") ||
      !jQuery(".reacg-form-popup .reacg-reasonType:checked").length ||
      jQuery(".reacg-form-popup input[name='reacg-email']").val() === "" ) {
      return false;
    }
    jQuery(".reacg-form-popup .spinner").addClass("is-active");
    let reason = "";
    if ( jQuery(".reacg-form-popup .reacg-reasonType:checked").val() === "other"
      && jQuery(".reacg-form-popup .reacg-reason").val() !== "" ) {
      reason = jQuery(".reacg-form-popup .reacg-reason").val();
    }
    else {
      reason = jQuery(".reacg-form-popup .reacg-reasonType:checked").attr("alt");
    }
    jQuery.ajax({
      type: "POST",
      //url: "http://localhost/wordpress/wp-json/reacgcore/v2/form",
      url: "https://regallery.team/core/wp-json/reacgcore/v2/form",
      contentType: "application/json",
      data: JSON.stringify({
        "action": "bookacall",
        "reason": reason,
        "email": jQuery(".reacg-form-popup input[name='reacg-email']").val(),
        "version": jQuery(".reacg-form-popup").data("version"),
      }),
      complete: function (data) {
        jQuery(".reacg-form-popup .spinner").removeClass("is-active");
        jQuery(".reacg-form-popup-overlay").hide();
      }
    });

    return false;
  });
  jQuery(document).on("change", ".reacg-form-popup .reacg-reasonType, .reacg-form-popup .reacg-agreement, .reacg-form-popup input[name='reacg-email']", function () {
    if ( jQuery(".reacg-form-popup .reacg-agreement").prop("checked") &&
      jQuery(".reacg-form-popup .reacg-reasonType:checked").length &&
      jQuery(".reacg-form-popup input[name='reacg-email']").val() !== "" ) {
      jQuery(".reacg-form-popup .reacg-submit").removeAttr('disabled');
    }
    else {
      jQuery(".reacg-form-popup .reacg-submit").attr('disabled', 'disabled');
    }
  });
  jQuery(document).on("click", ".reacg-form-popup-close, .reacg-form-popup .reacg-skip", function () {
    jQuery(".reacg-form-popup-overlay").hide();
  });
  jQuery(document).on("click", ".reacg-form-popup .reacg-reasonType", function () {
    if ( jQuery(this).val() === "other" ) {
      jQuery(".reacg-form-popup .reacg-reason-wrapper").show();
    }
    else {
      jQuery(".reacg-form-popup .reacg-reason-wrapper").hide();
    }
  });
});