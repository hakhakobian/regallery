jQuery(document).ready(function () {
  reacg_bind_book_a_call_modal_events();

  reacg_bind_optin_modal_events();
});

function reacgIsValidEmail(email) {
  // Simple email regex
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function reacg_bind_form_events(modal, submitAction, reasonExist = true, agreementExist = true, closeOnModalOverlayClick = true) {
  modal.find(".reacg-submit").on("click", function (e){
    const email = modal.find("input[name='reacg-email']").val();

    const agreementChecked = agreementExist ? modal.find(".reacg-agreement").prop("checked") : true;
    const reasonChecked = reasonExist ? modal.find(".reacg-reasonType:checked").length : true;

    if (!agreementChecked || !reasonChecked || email === "" || !reacgIsValidEmail(email)) {
      return false;
    }
    modal.find(".spinner").addClass("is-active");
    let reason = "";
    let reasonKey = 0;
    if ( reasonExist ) {
      if (modal.find(".reacg-reasonType:checked").val() == 4
        && modal.find(".reacg-reason").val() !== "") {
        reason = modal.find(".reacg-reason").val();
      }
      else {
        reason = modal.find(".reacg-reasonType:checked").attr("alt");
        reasonKey = modal.find(".reacg-reasonType:checked").val();
      }
    }

    submitAction(email, reason, reasonKey);

    return false;
  });
  modal.find(".reacg-reasonType, .reacg-agreement, input[name='reacg-email']").on("change", function () {
    const email = modal.find("input[name='reacg-email']").val();
    const agreementChecked = agreementExist ? modal.find(".reacg-agreement").prop("checked") : true;
    const reasonChecked = reasonExist ? modal.find(".reacg-reasonType:checked").length : true;

    if (agreementChecked && reasonChecked && email !== "" && reacgIsValidEmail(email)) {
      modal.find(".reacg-submit").removeAttr('disabled');
    }
    else {
      modal.find(".reacg-submit").attr('disabled', 'disabled');
    }
  });
  modal.find(".reacg-popup-close, .reacg-skip").on("click", function () {
    modal.hide();
  });
  if ( closeOnModalOverlayClick ) {
    modal.on("click", function () {
      modal.hide();
    });
    modal.find(".reacg-popup").on("click", function (e) {
      e.stopPropagation();
    });
  }

  modal.find(".reacg-reasonType").on("click", function () {
    if ( jQuery(this).val() == 4 ) {
      modal.find(".reacg-reason-wrapper").show();
    }
    else {
      modal.find(".reacg-reason-wrapper").hide();
    }
  });
}
function reacg_bind_book_a_call_modal_events() {
  const modal = jQuery("#reacg-form-popup-overlay");
  if (!modal.length) {
    return;
  }

  jQuery(document).on("click", "a[href='reacg-book-a-call']", function (e){
    modal.show();

    return false;
  });

  const submitAction = function (email, reason) {
    jQuery.ajax({
      type: "POST",
      url: "https://regallery.team/core/wp-json/reacgcore/v2/form",
      contentType: "application/json",
      data: JSON.stringify({
        action: "bookacall",
        reason: reason,
        email: email,
        version: modal.find(".reacg-popup").data("version"),
      }),
      complete: function (data) {
        modal.find(".spinner").removeClass("is-active");
        modal.hide();
      }
    });
  }

  reacg_bind_form_events(modal, submitAction);
}

function reacg_bind_optin_modal_events() {
  const modal = jQuery("#reacg-optin-popup-overlay");
  if (!modal.length) {
    return;
  }

  modal.show();

  const submitAction = function (email, reason) {
    jQuery.ajax({
      type: "POST",
      url: "https://regallery.team/core/wp-json/reacgcore/v2/optin",
      contentType: "application/json",
      data: JSON.stringify({
        email: email,
      }),
    }).complete(function () {
      modal.find(".spinner").removeClass("is-active");
      modal.hide();
    });
  }

  reacg_bind_form_events(modal, submitAction, false, false, false);
}