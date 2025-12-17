jQuery(document).ready(function () {
  if (!JSON.parse(localStorage.getItem("reacg-pro"))) {
    jQuery(".reacg-upgrade").removeClass("reacg-hidden");
  }
  jQuery(document).on("click", "a[id^='deactivate-regallery']", function (e){
    jQuery(".reacg-deactivate-popup-overlay").show();
    jQuery(".reacg-skip").attr("href", jQuery(this).attr("href"));

    return false;
  });
  jQuery(document).on("click", ".reacg-submit", function (e){
    if ( !jQuery(".reacg-agreement").prop("checked") ||
      !jQuery(".reacg-reasonType:checked").length ) {
      return false;
    }
    jQuery(".spinner").addClass("is-active");
    jQuery(this).addClass("button-primary");
    let reason = "";
    if ( jQuery(".reacg-reasonType:checked").val() === "other"
      && jQuery(".reacg-reason").val() !== "" ) {
      reason = jQuery(".reacg-reason").val();
    }
    else {
      reason = jQuery(".reacg-reasonType:checked").attr("alt");
    }
    jQuery.ajax({
      type: "POST",
      url: "https://regallery.team/core/wp-json/reacgcore/v2/deactivate",
      contentType: "application/json",
      data: JSON.stringify({
        "reason": reason,
        "email": jQuery("input[name='reacg-email']").val(),
        "version": jQuery(".reacg-deactivate-popup").data("version"),
      }),
      complete: function (data) {
        jQuery(".spinner").removeClass("is-active");
        jQuery(".reacg-deactivate-popup-overlay").hide();

        window.location.href = jQuery(".reacg-skip").attr("href");
      }
    });

    return false;
  });
  jQuery(document).on("change", ".reacg-reasonType, .reacg-agreement", function () {
    if ( jQuery(".reacg-agreement").prop("checked") && jQuery(".reacg-reasonType:checked").length ) {
      jQuery(".reacg-submit").addClass('button-primary');
    }
    else {
      jQuery(".reacg-submit").removeClass('button-primary');
    }
  });
  jQuery(document).on("click", ".reacg-deactivate-popup-close", function () {
    jQuery(".reacg-deactivate-popup-overlay").hide();
  });
  jQuery(document).on("click", ".reacg-reasonType", function () {
    if ( jQuery(this).val() === "other" ) {
      jQuery(".reacg-reason-wrapper").show();
    }
    else {
      jQuery(".reacg-reason-wrapper").hide();
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