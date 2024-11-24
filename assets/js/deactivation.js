jQuery(document).ready(function () {
  jQuery(document).on("click", "a[id^='deactivate-regallery']", function (e){
    jQuery(".reacg-deactivate-popup-overlay, .reacg-deactivate-popup").show();

    jQuery(".reacg-skip").attr("href", jQuery(this).attr("href"));

    return false;
  });
  jQuery(document).on("click", ".reacg-submit", function (e){
    jQuery(".spinner").addClass("is-active");
    jQuery(this).addClass("disabled");
    jQuery.ajax({
      type: "POST",
      url: "https://regallery.team/core/wp-json/reacgcore/v2/deactivate",
      contentType: "application/json",
      data: JSON.stringify({
        "reason": jQuery(".reacg-reason").val(),
        "version": jQuery(".reacg-reason").data("version"),
      }),
      complete: function (data) {
        jQuery(".spinner").removeClass("is-active");
        jQuery(this).removeClass("disabled");
        jQuery(".reacg-deactivate-popup-overlay, .reacg-deactivate-popup").hide();

        window.location.href = jQuery(".reacg-skip").attr("href");
      }
    });

    return false;
  });
  jQuery(document).on("change", ".reacg-agreement", function () {
    jQuery(".reacg-submit").toggleClass('button-primary-disabled', !jQuery(this).prop("checked") || !jQuery(".reacg-reason").val().trim());
  });
  jQuery(document).on("keyup", ".reacg-reason", function () {
    jQuery(".reacg-submit").toggleClass('button-primary-disabled', !jQuery(".reacg-agreement").prop("checked") || !jQuery(this).val().trim());
  });
  jQuery(document).on("click", ".reacg-deactivate-popup-close, .reacg-deactivate-popup-overlay", function () {
    jQuery(".reacg-deactivate-popup-overlay, .reacg-deactivate-popup").hide();
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