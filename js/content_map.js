/**
 * @file
 * Timeline panel app.
 */
(function ($, Drupal, drupalSettings) {

    'use strict';

    Drupal.behaviors.webprofiler_timeline = {
        attach: function (context) {
            $("#content_map").html(Viz($("#content_map").attr("data-dot")));
        }
    };

})(jQuery, Drupal, drupalSettings);