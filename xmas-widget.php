<?php
/*
Plugin Name: Xmas Widget Little Elf
Plugin URI: http://xmaswidget.com/
Description: Add personalized Christmas greetings to your site!
Author: Urlshortener.co
Version: 1.0
Author URI: http://www.urlshortener.co/
*/


if (!function_exists('add_action')) {
  die('Please don\'t open this file directly!');
}


class xmas_widget_core {
  static $version = '1.0';
  static $options = 'xmas_options';

  // hook everything up
  static function init() {
    if (is_admin()) {
      self::check_wp_version(4.2);

      add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_enqueue_scripts'));
      add_action('customize_controls_enqueue_scripts', array(__CLASS__, 'admin_enqueue_scripts'));
      add_filter('plugin_action_links_' . basename(dirname(__FILE__)) . '/' . basename(__FILE__), array(__CLASS__, 'plugin_action_links'));
      add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'update_plugin'));
      add_filter('plugins_api', array(__CLASS__, 'plugin_api_call'), 10, 3);
    } else {
      add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
      add_action('wp_footer', array(__CLASS__, 'wp_footer'));
    }
    add_shortcode('xmas', array('xmas_widget_core', 'do_shortcode_xmas'));
    add_shortcode('var', array('xmas_widget_core', 'do_shortcode_var'));
    add_shortcode('variable', array('xmas_widget_core', 'do_shortcode_var'));

    global $wf_ftw_do_footer, $wf_ftw_active_fonts, $wf_ftw_nb, $wf_ftw_custom_css;
    $wf_ftw_do_footer = false;
    $wf_ftw_custom_css = '';
    $wf_ftw_active_fonts = array();
    $wf_ftw_nb = 0;
  } // init


  // check if user has the minimal WP version required by the plugin
  static function check_wp_version($min_version) {
    if (!version_compare(get_bloginfo('version'), $min_version,  '>=')) {
        add_action('admin_notices', array(__CLASS__, 'min_version_error'));
    }
  } // check_wp_version


  // display error message if WP version is too low
  static function min_version_error() {
    echo '<div class="error"><p>Xmas Widget <b>requires WordPress version 4.2</b> or higher to function properly. You\'re using WordPress version ' . get_bloginfo('version') . '. Please <a href="' . admin_url('update-core.php') . '">update</a> your WordPress.</p></div>';
  } // min_version_error


  // initialize widgets
  static function widgets_init() {
    register_widget('xmas_widget');

    register_sidebar(array(
      'name' => 'Xmas Widget hidden sidebar',
      'id' => 'xmas-widget-hidden',
      'description' => 'Widgets in this area will never be shown in any sidebars in the theme. Area only helps you to build Xmas Widgets that are displayed with shortcodes.',
      'before_widget' => '<div id="%1$s" class="%2$s">',
      'after_widget'  => '</div>',
      'before_title'  => '<h2 class="widgettitle">',
      'after_title'   => '</h2>',
    ));
  } // widgets init


  // shortcode support for GET variables
  static function do_shortcode_var($atts, $content = null) {
    $out = '';
    $atts = shortcode_atts(array('name' => '', 'default' => ''), $atts);
    $atts['name'] = trim($atts['name']);

    if (!empty($_GET[$atts['name']])) {
      $out = $_GET[$atts['name']];
    } elseif (empty($_GET[$atts['name']]) && !empty($atts['default'])) {
      $out = $atts['default'];
    }

    $out = urldecode($out);
    $out = strip_tags($out);

    return $out;
  } // do_shortcode_var


  // shortcode support for any Xmas instance
  static function do_shortcode_xmas($atts, $content = null) {
    global $wp_widget_factory;
    $out = '';
    $atts = shortcode_atts(array('id' => 0, 'width' => '250px'), $atts);
    $id = (int) $atts['id'];
    $widgets = get_option('widget_xmas_widget');

    if (!$id || empty($widgets[$id])) {
      $out .= '<p class="xmas-error"><b>Xmas Widget shortcode error</b>: please double-check the widget ID.</p>';
    } else {
      $widget_args = $widgets[$id];
      $widget_instance['widget_id'] = 'xmas_widget-' . $id;
      $widget_instance['widget_name'] = 'Xmas Widget Little Elf';
      $widget_instance['before_widget'] = '<div id="' . $widget_instance['widget_id'] . '" class="xmas_widget">';

      $out .= '<div class="xmas-widget-shortcode" style="width:100%; max-width: ' . $atts['width'] . '">';
      ob_start();
      the_widget('xmas_widget', $widget_args, $widget_instance);
      $out .= ob_get_contents();
      ob_end_clean();
      $out .= '</div>';
    }

    return $out;
  } // do_shortcode_xmas


  // enqueue CSS and JS scripts on widgets pages
  static function admin_enqueue_scripts() {
    global $wp_customize;
    $current_screen = get_current_screen();

    if ($current_screen->id == 'widgets' || !empty($wp_customize)) {
      $plugin_url = plugin_dir_url(__FILE__);

      wp_enqueue_script('wp-color-picker');
      wp_enqueue_script('xmas-widget', $plugin_url . 'js/xmas-admin.js', array('jquery'), self::$version, true);

      wp_enqueue_style('wp-color-picker');
      wp_enqueue_style('xmas-widget', $plugin_url . 'css/xmas-admin.css', array(), self::$version);
    } // if
  } // enqueue_scripts


  // validate license
  static function is_activated($force_check = false) {
    $options = self::get_options();

    if ($force_check || rand(0, 100) > 95) {
      $check = self::check_purchase_key($options['purchase_key']);
      if ($check) {
        self::set_options(array('activated' => true));
        return true;
      } else {
        self::set_options(array('activated' => false));
        return false;
      }
    } // force or rnd retest

    // fast check
    if ($options['activated']) {
      return true;
    } else {
      return true;
    }

    return false;
  } // is_active

  // cleanup on deactivate
  static function deactivate() {
    delete_option(self::$options);
  } // deactivate


  // get plugin's options
  static function get_options() {
    $options = get_option(xmas_widget_core::$options, array());

    if (!is_array($options)) {
      $options = array();
    }

    return $options;
  } // get_options


  // update and set one or more options
  static function set_options($new_options) {
    if (!is_array($new_options)) {
      return false;
    }

    $options = xmas_widget_core::get_options();
    $options = array_merge($options, $new_options);

    update_option(xmas_widget_core::$options, $options);

    return $options;
  } // set_options


  // add settings link to plugins page
  static function plugin_action_links($links) {
    $settings_link = '<a href="widgets.php" title="Configure your Xmas Widgets">Widgets</a>';
    array_unshift($links, $settings_link);

    return $links;
  } // plugin_action_links


  // add jQuery if necessary
  static function enqueue_scripts() {
    if (is_active_widget(false, null, 'xmas_widget', true)) {
      wp_enqueue_script('jquery');
    }
  } // enqueue_scripts


  // inject CSS in theme footer
  static function wp_footer() {
    global $wf_ftw_do_footer, $wf_ftw_active_fonts, $wf_ftw_custom_css;
    $out = $footer = '';

    if ($wf_ftw_do_footer) {
      $css_files[] = plugins_url('/css/xmas.css', __FILE__);
      $css_files = apply_filters('wf_ftw_css_files_list', $css_files);

      foreach ($css_files as $css_file) {
        if (!empty($css_file)) {
          $out .= '<style type="text/css">@import url("' . $css_file . '");</style>' . "\n";
        }
      }

      if ($wf_ftw_active_fonts) {
        foreach ($wf_ftw_active_fonts as $font => $tmp) {
          $font = 'http://fonts.googleapis.com/css?family=' . $font_files[$font];
          $out .= '<style type="text/css">@import url("' . $font . '");</style>' . "\n";
        }
      } // if fonts

      $out = apply_filters('wf_ftw_do_footer', $out);

      global $wf_ftw_flash_snow_divs;

      if (sizeof($wf_ftw_flash_snow_divs)) {
        $swfobject_js = plugins_url('/js/swfobject.js', __FILE__);
        $footer .= '<script type="text/javascript" src="' . $swfobject_js . '"></script>' . "\n";

        $footer .= '<script type="text/javascript">' . "\n";
        $footer .= 'if (typeof(jQuery) == "undefined") { alert("Xmas Widget\'s snow effect requires jQuery."); } else { ';
        $footer .= 'jQuery(window).load(function($){ var params = { menu: "false", fullscreen: "false", wmode: "transparent" };' . "\n";

        foreach ($wf_ftw_flash_snow_divs as $widget) {
          $swf = plugins_url('/swf/' . $widget['file'] . '.swf', __FILE__);
          $footer .= 'swfobject.embedSWF("' . $swf . '", "' . $widget['div'] . '", "100%",  jQuery("#' . $widget['div'] . '").parent().parent().innerHeight() + "px", "9.0.0", "", "", params);' . "\n";
        } // foreach widget

        $footer .= "}); } \n</script>\n";

        $out .= $footer;
      } // if flash_snow_divs

      if (!empty($wf_ftw_custom_css)) {
        $out .= '<style type="text/css">' . $wf_ftw_custom_css . '</style>';
      }

      echo $out;
    } // if do_footer
  } // wp_footer


  // check for updates
  static function plugin_api_call($def, $action, $args) {
    if (!self::is_activated()) {
      return false;
    }

    $options = self::get_options();
    $plugin_slug = 'xmas-widget';
    $plugin_slug_long = plugin_basename(__FILE__);
    $api_url = 'http://xmaswidget.com/update.php';

    if (!isset($args->slug) || ($args->slug != $plugin_slug)) {
      return false;
    }

    $args->version = self::$version;
    $args->site = get_home_url();

    $request_string = array(
      'body' => array(
        'action' => $action,
        'request' => serialize($args),
        'purchase_key' => $options['purchase_key']
    ));

    $request = wp_remote_post($api_url, $request_string);

    if (is_wp_error($request)) {
      $res = new WP_Error('plugins_api_failed', __('An Unexpected HTTP Error occurred during the API request.</p>'), $request->get_error_message());
    } else {
      $res = unserialize($request['body']);

      if (!is_object($res))
        $res = new WP_Error('plugins_api_failed', __('An unknown error has occurred'), $request['body']);
    }

    return $res;
  } // plugin_api_call


  // automatic updates support
  static function update_plugin($current) {
    if (!self::is_activated()) {
      return $current;
    }

    static $request = false;
    $plugin = plugin_basename(__FILE__);
    $options = self::get_options();

    if(empty($request)){
      $request = wp_remote_get(
        add_query_arg(array(
          'timestamp' => time(),
          'action' => 'update_info',
          'purchase_key' => $options['purchase_key'],
          'site' => get_home_url(),
          'version' => self::$version,
          ), 'http://xmaswidget.com/update.php'),
        array('timeout' => 8)
      );
    }

    if (!is_wp_error($request) && $request['response']['code'] == 200 && !empty($request['body'])) {
      $data = @unserialize($request['body']);
      if (empty($current)) {
        $current = new stdClass();
      }
      if (empty($current->response)) {
        $current->response = array();
      }
      if (!empty($data)) {
        $current->response[$plugin] = $data;
      }
    } // if not error

    return $current;
  } // update_plugin


  // helper function for creating dropdowns
  static function create_select_options($options, $selected = null, $output = true) {
    $out = "\n";

    if(!is_array($selected)) {
      $selected = array($selected);
    }

    foreach ($options as $tmp) {
      $data = '';
      if (isset($tmp['data-imagesrc'])) {
        $data .= ' data-imagesrc="' . $tmp['data-imagesrc'] . '" ';
      }
      if ($tmp['val'] == '-1') {
        $data .= ' class="gmw_promo" ';
      }
      if (in_array($tmp['val'], $selected)) {
        $out .= "<option selected=\"selected\" value=\"{$tmp['val']}\"{$data}>{$tmp['label']}&nbsp;</option>\n";
      } else {
        $out .= "<option value=\"{$tmp['val']}\"{$data}>{$tmp['label']}&nbsp;</option>\n";
      }
    } // foreach

    if ($output) {
      echo $out;
    } else {
      return $out;
    }
  } // create_select_options
} // class xmas_widget_core


// main widget class
class xmas_widget extends WP_Widget {
  static $default_options = array('title' => 'Merry Christmas',
                                   'text' => 'May all your <strong>wishes come true</strong>! Have a merry, merry Christmas!',
                                   'background' => 'ftw-body-wrapping-paper-3',
                                   'background_url' => '',
                                   'icon' => 'ftw-graphics-xmas-pin-3',
                                   'icon_position' => 'ftw-graphics-right-bottom',
                                   'font' => 'ftw-font-mountains-of-christmas',
                                   'corners' => 'both',
                                   'font_color' => '#FFFFFF',
                                   'color' => '#EE0000',
                                   'snow_footer' => 'ftw-snow-footer-2',
                                   'snow' => 'original-effect',
                                   'custom_css' => '');
                             

  // constructor method
  function xmas_widget() {
    $widget_ops = array('classname' => 'xmas_widget', 'description' => 'Add personalized seasons\' greetings to your site.');
    $control_ops = array('width' => 450, 'height' => 350);
    $widget_name = 'Xmas Widget Little Elf';

    $this->__construct('xmas_widget', $widget_name, $widget_ops, $control_ops);
  } // xmas_widget


  // widget HTML generator
  function widget($args, $instance) {
    if (!xmas_widget_core::is_activated()) {
      return '';
    }

    global $wf_ftw_active_fonts, $wf_ftw_do_footer, $wf_ftw_nb, $wf_ftw_flash_snow_divs, $wf_ftw_custom_css;
    $out = '';

    extract($args);
    extract($instance);

    $wf_ftw_do_footer = true;
    $wf_ftw_active_fonts[$font] = true;
    $wf_ftw_nb++;

    $wf_ftw_custom_css .= ' ' . $custom_css . ' ';

    $title = do_shortcode($instance['title']);
    $text = wpautop(do_shortcode($instance['text']));

    if ($font_color) {
      $wf_ftw_custom_css .= ' #' . $widget_id . ' p { color:' . $font_color . ' !important; } ';
      $font_color = ' color:' . $font_color . ' !important;';
    }

    $container_class = apply_filters('wf_ftw_container_class', 'xmas_container', $args, $instance);
    if ($instance['snow_footer']) {
      $container_class .= ' ' . $instance['snow_footer'];
    }

    $out .= $before_widget;
    $out .= '<div id="ftw-' . $wf_ftw_nb . '" class="ftw-container ' . $container_class . '">';
    $out = apply_filters('wf_ftw_html_container_begin', $out, $args, $instance);

    if ($instance['snow']) {
      $out .= '<div class="ftw_snow"><div id="ftw_snow_flash-' . $wf_ftw_nb . '"></div></div>';
      $wf_ftw_flash_snow_divs[] = array('div' => 'ftw_snow_flash-' . $wf_ftw_nb, 'file' => $instance['snow']);
    }

    if ($background != 'custom') {
      $out .= '<div class="ftw-body ' . $background . ' ' . $font . '" style="background-color: ' . $instance['color'] . ' !important;' . $font_color . '"><div class="ftw-spacing">';
    } else {
      $out .= '<div class="ftw-body ' . $font . '" style="background: url(' . $background_url . ') repeat !important; background-color: ' . $instance['color'] . ' !important;' . $font_color . '"><div class="ftw-spacing">';
    }

    if (!empty($title)) {
      $out .= '<p><strong>' . $title . '</strong></p>';
    }
    $out .= $text . '</div></div>';
    $out .= '<div class="ftw-footer">';
    if ($corners == 'both') {
      $out .= '<div class="ftw-footer-left"></div>';
      $out .= '<div class="ftw-footer-right"></div>';
    } elseif ($corners == 'left') {
      $out .= '<div class="ftw-footer-left"></div>';
    } elseif ($corners == 'right') {
      $out .= '<div class="ftw-footer-right"></div>';
    }
    $out .= '</div>';
    $out .= '<div class="' . $icon . ' ' . $icon_position . '"></div>';
    $out .= '</div>';
    $out .= $after_widget;

    $out = apply_filters('wf_ftw_html_before_echo', $out, $args, $instance);
    echo $out;
  } // widget


  // update widget settings
  function update($new_instance, $old_instance) {

    if (sizeof($new_instance) < 10) {
      return self::$default_options;
    }
    
    if (empty($new_instance['icon_position'])) {
      return $old_instance;
    }

    $instance['title'] = trim($new_instance['title']);
    $instance['text'] = trim($new_instance['text']);
    $instance['background'] = $new_instance['background'];
    $instance['background_url'] = trim($new_instance['background_url']);
    $instance['icon'] = $new_instance['icon'];
    $instance['icon_position'] = $new_instance['icon_position'];
    $instance['font'] = $new_instance['font'];
    $instance['corners'] = $new_instance['corners'];
    $instance['font_color'] = substr($new_instance['font_color'], 0, 7);
    $instance['color'] = substr($new_instance['color'], 0, 7);
    $instance['snow_footer'] = $new_instance['snow_footer'];
    $instance['snow'] = $new_instance['snow'];
    $instance['custom_css'] = trim($new_instance['custom_css']);

    return $instance;
  } // update


  // widget customization form
  function form($instance) {
    $out = '';

    $instance = wp_parse_args((array) $instance, self::$default_options);
    if (empty($instance['icon_position'])) {
      $instance = $default_options;
    }
    $title = strip_tags($instance['title']);

    $backgrounds   = array();
    $backgrounds[] = array('val' => '', 'label' => 'Solid color, no image');
    $backgrounds[] = array('val' => 'ftw-body-wrapping-paper-1', 'label' => 'Snow crystals');
    $backgrounds[] = array('val' => 'ftw-body-wrapping-paper-2', 'label' => 'Snow crystals, darker');
    $backgrounds[] = array('val' => 'ftw-body-wrapping-paper-4', 'label' => 'Snowflakes #2');
    $backgrounds[] = array('val' => 'ftw-body-wrapping-paper-3', 'label' => 'Trees');

    $icons   = array();
    $icons[] = array('val' => '', 'label' => 'None');
    $icons[] = array('val' => 'ftw-graphics-xmas-pin-16', 'label' => 'Blue ornament sticker');
    $icons[] = array('val' => 'ftw-graphics-xmas-pin-12', 'label' => 'Gingerbread cookie');
    $icons[] = array('val' => 'ftw-graphics-xmas-pin-20', 'label' => 'Gold bell sticker');
    $icons[] = array('val' => 'ftw-graphics-xmas-pin-4', 'label' => 'Green ribbon');
    $icons[] = array('val' => 'ftw-graphics-xmas-pin-6', 'label' => 'Green ornament');  
    
    $icon_positions   = array();
    $icon_positions[] = array('val' => 'ftw-graphics-left', 'label' => 'Top left');
    $icon_positions[] = array('val' => 'ftw-graphics-right-bottom', 'label' => 'Bottom right');

    $fonts   = array();
    $fonts[] = array('val' => '', 'label' => 'Default, theme defined');
     

    $out .= '<div class="xmas-widget-container">';

    if (xmas_widget_core::is_activated()) {
      $out .= '<p><label for="' . $this->get_field_id('title') . '">Title:</label>';
      $out .= '<input placeholder="Widget title" class="widefat" id="' . $this->get_field_id('title') . '" name="' . $this->get_field_name('title') . '" type="text" value="'. esc_attr($instance['title']) . '" /></p>';

      $out .= '<p><label for="' . $this->get_field_id('title') . '">Content:</label>';
      $out .= '<textarea class="widefat" placeholder="Widget content" required="required" rows="10" id="' . $this->get_field_id('text') . '" name="' . $this->get_field_name('text') . '">' . esc_textarea($instance['text']) . '</textarea><br>';
      $out .= 'To personalize the content add GET variables to your URL (eg <i>site.com/?first_name=John</i>) and then insert them in widget with shortcodes:<br><code>[var name="first_name" default="Dear visitor"]</code></p>';

      $out .= '<p><label for="' . $this->get_field_id('background') . '">Background:</label> ';
      $out .= '<select class="xmas_background_type" name="' . $this->get_field_name('background') . '" id="' . $this->get_field_id('background') . '">';
      $out .= xmas_widget_core::create_select_options($backgrounds, $instance['background'], false);
      $out .= '</select></p>';

      $out .= '<p class="xmas_custom_background_section"><label for="' . $this->get_field_id('background_url') . '">Background Image URL:</label> ';
      $out .= '<input class="standard-text" placeholder="http://" id="' . $this->get_field_id('background_url') . '" name="' . $this->get_field_name('background_url') . '" required="required" type="text" value="'. esc_attr($instance['background_url']) . '" /></p>';

      $out .= '<p class="color_fix"><label for="' . $this->get_field_id('color') . '">Background Color:</label> ';
      $out .= '<input class="medium-text xmas-colorpicker" id="' . $this->get_field_id('color') . '" name="' . $this->get_field_name('color') . '" type="text" value="'. $instance['color'] . '" /></p>';

      $out .= '<p class="color_fix"><label for="' . $this->get_field_id('font_color') . '">Text Color:</label> ';
      $out .= '<input class="medium-text xmas-colorpicker" id="' . $this->get_field_id('font_color') . '" name="' . $this->get_field_name('font_color') . '" type="text" value="'. $instance['font_color'] . '" /></p>';

      $out .= '<p><label for="' . $this->get_field_id('icon') . '">Icon:</label> ';
      $out .= '<select name="' . $this->get_field_name('icon') . '" id="' . $this->get_field_id('icon') . '">';
      $out .= xmas_widget_core::create_select_options($icons, $instance['icon'], false);
      $out .= '</select></p>';

      $out .= '<p><label for="' . $this->get_field_id('icon_position') . '">Icon Position:</label> ';
      $out .= '<select name="' . $this->get_field_name('icon_position') . '" id="' . $this->get_field_id('icon_position') . '">';
      $out .= xmas_widget_core::create_select_options($icon_positions, $instance['icon_position'], false);
      $out .= '</select></p>';

      $out .= '<p><label for="' . $this->get_field_id('shortcode') . '">Shortcode:</label> ';
      $id = str_replace('xmas_widget-', '', $this->id);
      if (!$id || !is_numeric($id)) {
        $out .= 'Save widget to generate the shortcode.';
        $id = 0;
      } else {
        $out .= '<code>[xmas id="' . $id . '" width="250px"]</code>';
      }
      $out .= '</p>';
    } else {
      $options = xmas_widget_core::get_options();

      $out .= '<h3>Thank you for using <span class="xmas_title">Xmas</span> Widget!</h3>';
    } // not active
    $out .= '</div>'; // xmas-widget-container

    echo $out;
  } // form
} // class xmas_widget


// hook everything up
add_action('init',         array('xmas_widget_core', 'init'));
add_action('widgets_init', array('xmas_widget_core', 'widgets_init'));
register_deactivation_hook(__FILE__, array('xmas_widget_core', 'deactivate'));