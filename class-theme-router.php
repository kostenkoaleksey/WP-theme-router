<?php

/**
 * Class Theme_Router
 */
class Theme_Router extends Router {
	/**
	 * @var string
	 */
	protected $base_template_path;
	
	/**
	 * Theme_Router constructor.
	 *
	 * @param $namespace
	 * @param string $base_template_path
	 */
	public function __construct( $namespace, $base_template_path = 'pages' ) {
		parent::__construct( $namespace );
		
		$this->base_template_path = $base_template_path;
		$this->routes             = config( 'theme.routes', [] );
	}
	
	/**
	 * Initializes routing system
	 */
	public function run() {
		add_action( 'init', [ $this, 'registerRoutes' ] );
		add_action( 'template_redirect', [ $this, 'resolveRoutes' ] );
		
		add_filter( 'query_vars', [ $this, 'addQueryVars' ] );
		add_filter( 'template_include', [ $this, 'templateResolver' ] );
	}
	
	/**
	 * Register all routes.
	 */
	public function registerRoutes() {
		add_rewrite_tag( "%{$this->getCustomRouteTag()}%", '([^&]+)' );
		
		$routes = array_keys( $this->getRoutes() );
		array_walk( $routes, function ( $route ) {
			$route = $this->prepareRoute( $route );
			
			add_rewrite_rule(
				"^({$route})/?$",
				"index.php?{$this->getCustomRouteTag()}={$route}",
				'top'
			);
		} );
	}
	
	/**
	 * Executes current route callback
	 */
	public function resolveRoutes() {
		$routes = $this->getRoutes();
		array_walk( $routes, function ( $callback, $route ) {
			$route = $this->prepareRoute( $route );
			if ( $route == $this->getCurrentRoute() ) {
				if ( is_array( $callback ) ) {
					$callback = $callback['callback'];
				}
				
				list( $controller, $action ) = explode( '@', $callback );
				
				call_user_func( [ new $controller(), "{$action}Action" ] );
			}
		} );
	}
	
	/**
	 * Prepares the route name
	 *
	 * @param $route
	 *
	 * @return string
	 */
	protected function prepareRoute( $route ) {
		return stripslashes( trim( $route ) );
	}
	
	/**
	 * Retrieves the custom route tag name
	 *
	 * @return string
	 */
	public function getCustomRouteTag() {
		return sprintf( 'custom_%s_route', $this->namespace );
	}
	
	/**
	 * Add custom route tag variable to the query vars
	 *
	 * @param array $query_vars
	 *
	 * @return array
	 */
	public function addQueryVars( $query_vars ) {
		array_push( $query_vars, $this->getCustomRouteTag() );
		
		return $query_vars;
	}
	
	/**
	 * Includes the appropriate template depending on current custom route
	 *
	 * @param $template
	 *
	 * @return string
	 */
	public function templateResolver( $template ) {
		if ( $this->isCustomRoute() ) {
			if ( $custom_template = $this->getRouteTemplate( $this->getCurrentRoute() ) ) {
				$template = $custom_template;
			}
		}
		
		return $template;
	}
	
	/**
	 * Checks whether the current route is custom
	 *
	 * @return bool
	 */
	public function isCustomRoute() {
		return ! empty( $this->getCurrentRoute() );
	}
	
	/**
	 * Retrieves current custom route
	 *
	 * @return string
	 */
	public function getCurrentRoute() {
		return get_query_var( $this->getCustomRouteTag() );
	}
	
	/**
	 * Retrieve the name of the highest priority template file that exists.
	 *
	 * @param string    $route
	 * @param bool      $load
	 * @param bool      $require_once
	 *
	 * @return string
	 */
	public function getRouteTemplate( $route, $load = false, $require_once = true ) {
		$template = locate_template(
			sprintf( '%1$s/%2$s.php', $this->base_template_path, $route ),
			$load,
			$require_once
		);
		return apply_filters( "{$this->getCustomRouteTag()}_template", $template, $route, $this->base_template_path );
	}
}
