jQuery(document).ready(function () {
  if (!JSON.parse(localStorage.getItem("reacg-pro"))) {
    jQuery(".reacg-upgrade").removeClass("reacg-hidden");
  }
  jQuery(document).on("click", "a[id^='deactivate-regallery']", function (e){
    jQuery(".reacg-deactivate-popup-overlay").show();
    jQuery(".reacg-deactivate-popup .reacg-skip").attr("href", jQuery(this).attr("href"));

    return false;
  });
  jQuery(document).on("click", ".reacg-deactivate-popup .reacg-submit", function (e){
    if ( !jQuery(".reacg-deactivate-popup .reacg-agreement").prop("checked") ||
      !jQuery(".reacg-deactivate-popup .reacg-reasonType:checked").length ||
      jQuery(".reacg-deactivate-popup input[name='reacg-email']").val() === "" ) {
      return false;
    }
    jQuery(".reacg-deactivate-popup .spinner").addClass("is-active");
    let reason = "";
    if ( jQuery(".reacg-deactivate-popup .reacg-reasonType:checked").val() === "other"
      && jQuery(".reacg-deactivate-popup .reacg-reason").val() !== "" ) {
      reason = jQuery(".reacg-deactivate-popup .reacg-reason").val();
    }
    else {
      reason = jQuery(".reacg-deactivate-popup .reacg-reasonType:checked").attr("alt");
    }
    jQuery.ajax({
      type: "POST",
      url: "https://regallery.team/core/wp-json/reacgcore/v2/deactivate",
      contentType: "application/json",
      data: JSON.stringify({
        "reason": reason,
        "email": jQuery(".reacg-deactivate-popup input[name='reacg-email']").val(),
        "version": jQuery(".reacg-deactivate-popup").data("version"),
      }),
      complete: function (data) {
        jQuery(".reacg-deactivate-popup .spinner").removeClass("is-active");
        jQuery(".reacg-deactivate-popup-overlay").hide();

        window.location.href = jQuery(".reacg-deactivate-popup .reacg-skip").attr("href");
      }
    });

    return false;
  });
  jQuery(document).on("change", ".reacg-deactivate-popup .reacg-reasonType, .reacg-deactivate-popup .reacg-agreement, .reacg-deactivate-popup input[name='reacg-email']", function () {
    if ( jQuery(".reacg-deactivate-popup .reacg-agreement").prop("checked") &&
      jQuery(".reacg-deactivate-popup .reacg-reasonType:checked").length &&
      jQuery(".reacg-deactivate-popup input[name='reacg-email']").val() !== "" ) {
      jQuery(".reacg-deactivate-popup .reacg-submit").removeAttr('disabled');
    }
    else {
      jQuery(".reacg-deactivate-popup .reacg-submit").attr('disabled', 'disabled');
    }
  });
  jQuery(document).on("click", ".reacg-deactivate-popup-close", function () {
    jQuery(".reacg-deactivate-popup-overlay").hide();
  });
  jQuery(document).on("click", ".reacg-deactivate-popup .reacg-reasonType", function () {
    if ( jQuery(this).val() === "other" ) {
      jQuery(".reacg-deactivate-popup .reacg-reason-wrapper").show();
    }
    else {
      jQuery(".reacg-deactivate-popup .reacg-reason-wrapper").hide();
    }
  });
  jQuery(".reacg-rating span").on("mouseenter", function () {
    const index = jQuery(this).index();
    jQuery(this).parent().find("span").each(function (i) {
      jQuery(this).toggleClass("dashicons-star-filled", i <= index)
                  .toggleClass("dashicons-star-empty", i > index);
    });
  });
  jQuery(".reacg-rating").on("mouseleave", function () {
    jQuery(this).find("span").removeClass("dashicons-star-empty").addClass("dashicons-star-filled");
  });
});