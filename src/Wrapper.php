<?php

namespace QtGye\WPTemplateWrapper;


/**
 * Theme wrapper
 *
 * @link https://roots.io/sage/docs/theme-wrapper/
 * @link http://scribu.net/wordpress/theme-wrappers.html
 */

class Wrapper {


  /**
   * Stores the theme root path
   */
  static $theme_root;

  /**
   * Stores the full path to the main template file
   */
  static $main_template;

  /**
   * Stores the base name of the template file; e.g. 'page' for 'page.php' etc.
   */
  static $wrapper;

  /**
   * Stores the slug the template root directory.
   */
  static $template_root;

  /**
   * Stores the slug the template file; e.g. 'page/about' for 'page/page.php' etc.
   */
  static $template_slug;


  /**
   * Stores recorded routes
   */
  static $routes;


  /**
   * Stores matched route
   */
  static $matched_route;


  /**
   * Stores global data
   */
  static $global_data;


  /**
   * Stores which template types to filter
   */
  static $template_types = [
    'embed',
    '404',
    'search',
    'frontpage',
    'home',
    'taxonomy',
    'attachment',
    'single',
    'page',
    'singular',
    'category',
    'tag',
    'author',
    'date',
    'archive',
    'index',
  ];


  /**
   * Stores the template data to be used within the templates
   */
  static $template_data;


  static function init()
  {
    self::cache_vars();

    if ( defined('DOING_AJAX') ) {
      self::perform_ajax();
    } else {
      self::register_template_filters();
    }
  }


  static function cache_vars()
  {
    self::$theme_root = get_template_directory();
    self::$template_root = self::$theme_root."/templates/";
    self::$routes = self::get_routes();
  }


  static function wrap( $template ) {

    self::$template_slug = preg_replace("/(".str_replace("/", "\/", self::$template_root)."|[.]php$)/", "", $template);
    self::$wrapper = locate_template( 'templates/wrapper.php' );;
    self::$main_template = $template;
    self::load_template();
  }


  /**
   * Registers template hooks
   */
  static function register_template_filters()
  {
    $class = __NAMESPACE__.'\\Wrapper';

    // template_include
    add_filter( 'template_include', array( $class, 'wrap' ), 99 );

    // transform template files location through template hierarchy
    foreach (self::$template_types as $type) {
      add_filter("{$type}_template_hierarchy", array( $class, 'template_hierarchy_transform' ), 99);
    }
  }


  static function template_hierarchy_transform ( $template_hierarchy = [] )
  {
    $types_string = implode('|', self::$template_types);
    $templates = array_map(function ( $base_name ) use ($types_string)
    {
      $base_name = preg_replace("/.+\//", "page-", $base_name); // Normalize custom page template directory prefix
      $template_file =  preg_replace("/^(".$types_string.")-(.+)$/i", "templates/$1/$2", $base_name);
      $template_file = $base_name !== $template_file ? $template_file : "templates/$template_file";
      $template_slug = preg_replace("/(templates\/|[.]php$)/i", "", $template_file);

      if ( empty(self::$matched_route) && array_key_exists( $template_slug, self::$routes ) ) {
        self::$matched_route = $template_slug;
      }

      return $template_file;
    }, $template_hierarchy);
    return $templates;
  }


  static function get_global_template_data()
  {

    if ( !empty(self::$global_data) ) {
      return self::$global_data;
    }

    // Significant Wordpress globals
    global $wp, $wpdb, $wp_query, $post, $authordata, $page;

    $global_path = self::$theme_root . "/src/global.php";
    if ( file_exists( $global_path ) ) {
      include($global_path);
    }

    // Merge user-defined globals with predefined globals
    $data = apply_filters( 'template_global_data', '' );
    $data = is_array($data) ? $data : [];
    $data = array_merge( compact('wp','wpdb','wp_query','post','authordata','page'), $data );

    self::$global_data = $data;

    return $data;
  }


  /**
   * Gets the data for the currently matched route
   */
  static function get_template_data ()
  {
    $routes = self::get_routes();
    $data = [];

    if ( !empty(self::$matched_route) ) {

      $globals = self::get_global_template_data();
      $data_function = $routes[ self::$matched_route ];

      // Callable must exist
      self::try_catch( is_callable($data_function), "Method or function for the route ". self::$matched_route ." does not exist: <br><pre><code>". json_encode( $data_function, TRUE ) . "</code></pre>" );

      // Get controller method parameters
      if ( is_string($data_function) && function_exists($data_function) ) {
        $reflection_func = new \ReflectionFunction($data_function); }
      elseif ( call_user_func_array( 'method_exists', $data_function ) ) {
        $reflection_func = new \ReflectionMethod( $data_function[0], $data_function[1] );
      }

      // Populate with matched global values
      $params = $reflection_func->getParameters();
      $param_values = array_map(function ($param) use ( $globals )
      {
        // Include globals
        if ( array_key_exists($param->name, $globals) ) {
          return $globals[$param->name];
        }
        return null;
      }, $params);

      $data = call_user_func_array($data_function, $param_values);
    }

    return $data;
  }


  static function include_template ( $slug = '', $data = [] )
  {
    unset($path);
    extract( array_merge( self::get_global_template_data(), $data ) );

    include locate_template( "templates/$slug.php" );
  }


  static function get_routes ()
  {
    if ( !empty(self::$routes) ) {
      return self::$routes;
    }

    $routes_file = self::$theme_root."/src/routes.php";
    if ( file_exists( $routes_file ) ) {
      include $routes_file;
    }

    if ( defined('DOING_AJAX') ) {
      $routes = apply_filters('ajax_routes', '');
    } else {
      $routes = apply_filters('template_routes', '');
    }

    return is_array($routes) ? $routes : [];
  }


  static function load_template ()
  {
    // Extract data for template consumption
    extract( array_merge(self::get_global_template_data(), self::get_template_data() ) );
    include(self::$wrapper);

    // Let's not use default wordpress template inclusion
    exit();
  }


  static function perform_ajax ()
  {
    $action = $_GET['action'];

    if ( empty($action) || !array_key_exists($action, self::$routes) ) return;

    self::$matched_route = $action;
    $data_function = self::$routes[ self::$matched_route ];
    $globals = self::get_global_template_data();

    // Get ajax callback arguments
    if ( function_exists($data_function) ) {
      $reflection_func = new \ReflectionFunction($data_function); }
    elseif ( call_user_func_array( 'method_exists', $data_function ) ) {
      $reflection_func = new \ReflectionMethod( $data_function[0], $data_function[1] );
    }

    // Populate parameter values
    $params = $reflection_func->getParameters();
    $param_values = array_map(function ($param) use ( $globals )
    {
      if ( array_key_exists($param->name, $_GET) ) {
        return $_GET[$param->name];
      }
      // Include globals
      elseif ( array_key_exists($param->name, $globals) ) {
        return $globals[$param->name];
      }
      return null;
    }, $params);

    // Generate ajax callback
    $ajax_callback = self::generate_ajax_callback( $data_function, $param_values );

    // Register ajax hook
    add_action( "wp_ajax_$action", $ajax_callback );
    add_action( "wp_ajax_nopriv_$action", $ajax_callback );
  }


  static function generate_ajax_callback ( $callback, $param_values )
  {
    return function () use ( $callback, $param_values )
    {
      $result = call_user_func_array($callback, $param_values);
      die(json_encode($result));
    };
  }


  static function try_catch ( $expression  = true, $message = '' )
  {
    try {
      if ( !$expression ) throw new \Exception($message);
    } catch ( \Exception $e ) {
      $output = "<style>
          body {
            font-family: sans-serif;
          }

          .trace-title {
            display: block;
            text-transform: uppercase;
            opacity: 0.6;
            padding: 10px 5px;
            background-color: #777;
            color: #000;
            text-shadow: 1px 1px 0px #aaa;
            margin-top: 30px;
            margin-bottom: 20px;
          }

          h4 {
            margin-top: 30px;
          }

          table {
            font-size: 0.9rem;
          }

          tr td:not(:first-child) {
            font-size: 0.8em;
          }

          tr.spacer td {
              padding: 10px 0px;
          }

          td {
            vertical-align: top;
            line-height: 1.4;
          }

          td:first-child {
            font-size: 0.75em;
            opacity: 0.35;
            text-align: right;
            text-transform: uppercase;
          }

          .class-name {
            font-weight: lighter;
            color: #99e;
          }

          .method-type {
            font-weight: lighter;
            color: #ccc;
          }

          .method {

          }

          .arguments td:last-child {
            background-color: #eee;
            padding: 5px;
          }

          code {
            color: #888;
            line-height: 1.5;
          }

      </style>";

      $output .= "<h4>".$e->getMessage()."</h4>";
      $output .= "<small class='trace-title'>Stack Trace:</small>";
      $output .= "<table><tbody>";
      foreach ( array_slice($e->getTrace(), 1) as $trace) {
        $output .= "<tr><td><strong>Function / method : </strong> </td><td><strong>";
            $output .= $trace['class'] ? "<span class='class-name'>".$trace['class']."</span>" . '<span class="method-type"> '.$trace['type'].' </span>' . '<span class="method">'.$trace['function'].'</span>' : $trace['function'];
            $output .= "</strong></td></tr>";
        // $output .= $trace['class'] ? "<tr><td><strong>Class : </strong></td><td>".$trace['class']."</td></tr>" : '';
        $output .= $trace['file'] ? "<tr><td><strong>File : </strong></td><td>".$trace['file']." <em>(line ".$trace['line'].")</em></td></tr>" : '';
        if ( !empty($trace['args']) ) {
          // json_encode($output);
          $args = implode(',<br>', array_map(function ( $arg )
          {
            return stripslashes(json_encode($arg, JSON_PRETTY_PRINT));
          }, $trace['args']));
          $output .= "<tr class='arguments'><td><strong>Arguments : </strong> </td><td><code>".$args."</code></td></tr>";;
        }
        $output .= "<tr class='spacer'><td></td><td></td></tr>";
      }
      $output .= "</tbody></table>";
      echo $output;
      die();
    }
  }

}

