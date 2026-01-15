jQuery(document).ready(function () {
  if (!JSON.parse(localStorage.getItem("reacg-pro"))) {
    jQuery(".reacg-upgrade").removeClass("reacg-hidden");
  }

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

  reacg_bind_deactivation_modal_events();
});

function reacg_bind_deactivation_modal_events() {
  const modal = jQuery("#reacg-deactivate-popup-overlay");
  if (!modal.length) {
    return;
  }

  jQuery(document).on("click", "a[id^='deactivate-regallery']", function (e){
    modal.show();
    modal.find(".reacg-skip").attr("href", jQuery(this).attr("href"));

    return false;
  });
  const submitAction = function (email, reason) {
    jQuery.ajax({
      type: "POST",
      url: "https://regallery.team/core/wp-json/reacgcore/v2/deactivate",
      contentType: "application/json",
      data: JSON.stringify({
        reason: reason,
        email: email,
        version: modal.find(".reacg-popup").data("version"),
      }),
      complete: function (data) {
        modal.find(".spinner").removeClass("is-active");
        modal.hide();

        window.location.href = modal.find(".reacg-skip").attr("href");
      }
    });
  }

  reacg_bind_form_events(modal, submitAction);
}