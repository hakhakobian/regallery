jQuery(document).ready(function () {
  jQuery("#reacg-box__create-demo").on("click", function () {
    const button = jQuery(this);
    const parent = button.parent();
    const spinner = parent.find(".spinner");
    spinner.addClass("is-active");
    jQuery.ajax({
      type: "POST",
      url: ajaxurl,
      dataType: "json",
      data: {
        "action": "reacg_import_demo",
      },
      success: function (response) {
        spinner.hide();
        button.hide();

        if ( response.data.gallery ) {
          if ( response.data.message ) {
            parent.append(`<div class="reacg-box__note updated" >${response.data.message}</div>`);
          }
          parent.append(
            `<a class="reacg-box__note" href="${response.data.gallery.url}" target="_blank">
                ${response.data.gallery.title}
              </a>`
          );
        }
        else {
          parent.append(`<div class="reacg-box__note error" >${response.data.message ? response.data.message : 'Error occurred.'}</div>`);
        }

      },
      error: function () {
        spinner.removeClass("is-active");
        parent.append(`<div class="reacg-box__note error" >Error occurred.</div>`);
      }
    });
  });
});