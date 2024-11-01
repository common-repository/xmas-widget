/*
 * Xmas Widget
 * (c) Web factory Ltd, 2015
 */


jQuery(function($) {
  $("[id*='xmas_widget-'].widget").each(function (i, widget) {
	title = $('.widget-title h3', widget).html();
	if (!title) {
      return true;
    }
    title = title.replace('Xmas', ' <span class="xmas_title">Xmas</span>');
    $('.widget-title h3', widget).html(title);
  }); // foreach Xmas widget

  // init JS for each active widget
  $(".widget-liquid-right [id*='xmas_widget-'].widget, .inactive-sidebar [id*='xmas_widget-'].widget, #accordion-panel-widgets  [id*='xmas_widget-'].widget").each(function (i, widget) {
    xmas_init_widget_ui(widget);
  }); // foreach GMW active widget

  // re-init JS on widget update and add
  $(document).on('widget-updated', function(event, widget) {
    id = $(widget).attr('id');
    if (id.indexOf('xmas_widget') != -1) {
      xmas_init_widget_ui(widget);
    }
  });
  $(document).on('widget-added', function(event, widget) {
    id = $(widget).attr('id');
    if (id.indexOf('xmas_widget') != -1) {
      xmas_init_widget_ui(widget);
    }
  });

  // init JS UI for an individual widget
  function xmas_init_widget_ui(widget) {
    $('.xmas-colorpicker', widget).wpColorPicker();

    // handle dropdown fields that have dependant fields
    $('.xmas_background_type', widget).on('change', function(e) {
      gmw_change_background_type(widget);
    }).trigger('change');

    // auto-expand textarea
    $('textarea.auto-expand', widget).on('focus', function(e) {
      e.preventDefault();
      $(this).attr('rows', '5');

      return false;
    });
    $('textarea.auto-expand', widget).on('focusout', function(e) {
      e.preventDefault();
      $(this).attr('rows', '1');

      return false;
    });
  } // xmas_init_widget_ui

  // show/hide custom background url link field
  function gmw_change_background_type(widget) {
    if ($('.xmas_background_type', widget).val() == 'custom') {
      $('.xmas_custom_background_section', widget).show();
    } else {
      $('.xmas_custom_background_section', widget).hide();
    }
  } // custom background
}); // onload