<?php
/**
 * Feature: OpenAPI 3.0 Specification.
 *
 * Intercepts `?format=openapi` and returns a complete OpenAPI 3.0.3 document
 * built dynamically from `rest_get_server()->get_routes()` (filtered through
 * `rest_endpoints` to honor `show_in_index`), so plugin-registered REST
 * routes appear automatically.
 *
 * @package Ajaco
 */

namespace Ajaco;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPENAPI_CACHE_KEY = 'ajaco_openapi_cache';

add_action( 'template_redirect', __NAMESPACE__ . '\\handle_openapi_request', 1 );

// Invalidate the cache when REST route registration could have changed.
add_action( 'activated_plugin', __NAMESPACE__ . '\\flush_openapi_cache' );
add_action( 'deactivated_plugin', __NAMESPACE__ . '\\flush_openapi_cache' );
add_action( 'switch_theme', __NAMESPACE__ . '\\flush_openapi_cache' );

/**
 * Delete the cached OpenAPI document.
 *
 * @return void
 */
function flush_openapi_cache(): void {
	delete_transient( OPENAPI_CACHE_KEY );
}

/**
 * Serve the OpenAPI document at /?format=openapi.
 *
 * Cached for one day in a transient. Invalidates automatically on plugin
 * activation/deactivation and theme switch (the events that can change
 * which REST routes are registered).
 *
 * @return void
 */
function handle_openapi_request(): void {
	// `?format=openapi` is a public output-format flag, not form data — no
	// nonce applies. The value is strictly compared, never executed or stored.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['format'] ) || $_GET['format'] !== 'openapi' ) {
		return;
	}

	if ( ! is_feature_enabled( 'openapi' ) ) {
		return;
	}

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );

	$cached = get_transient( OPENAPI_CACHE_KEY );
	if ( is_string( $cached ) && $cached !== '' ) {
		// JSON body served as application/json — already produced by wp_json_encode().
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $cached;
		exit;
	}

	$document = build_openapi_document();
	set_transient( OPENAPI_CACHE_KEY, $document, DAY_IN_SECONDS );

	// JSON body served as application/json — already produced by wp_json_encode().
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $document;
	exit;
}

/**
 * Build the OpenAPI document and return it as a JSON string.
 *
 * @return string
 */
function build_openapi_document(): string {
	$site_name        = get_bloginfo( 'name' );
	$site_description = get_bloginfo( 'description' );
	$site_version     = get_bloginfo( 'version' );

	$server    = rest_get_server();
	$endpoints = apply_filters( 'rest_endpoints', $server->get_routes() );

	$paths = array();
	foreach ( $endpoints as $route => $handlers ) {
		if ( trim( $route, '/' ) === '' ) {
			continue;
		}

		$oas_path = convert_route_to_openapi_path( $route );

		foreach ( $handlers as $handler ) {
			if ( isset( $handler['show_in_index'] ) && $handler['show_in_index'] === false ) {
				continue;
			}

			$methods = isset( $handler['methods'] ) && is_array( $handler['methods'] )
				? array_keys( array_filter( $handler['methods'] ) )
				: array();
			$args    = isset( $handler['args'] ) && is_array( $handler['args'] ) ? $handler['args'] : array();

			foreach ( $methods as $method ) {
				$method_lc = strtolower( $method );
				if ( ! in_array( $method_lc, array( 'get', 'post', 'put', 'patch', 'delete' ), true ) ) {
					continue;
				}

				$operation = array(
					'summary'   => strtoupper( $method ) . ' ' . $oas_path,
					'responses' => array(
						'200' => array( 'description' => 'Successful response' ),
					),
				);

				$params = build_openapi_parameters( $route, $args, $method_lc );
				if ( ! empty( $params ) ) {
					$operation['parameters'] = $params;
				}

				if ( in_array( $method_lc, array( 'post', 'put', 'patch' ), true ) ) {
					$body = build_openapi_request_body( $args, $route );
					if ( $body !== null ) {
						$operation['requestBody'] = $body;
					}
				}

				$paths[ $oas_path ][ $method_lc ] = $operation;
			}
		}
	}

	ksort( $paths );

	$openapi = array(
		'openapi'    => '3.0.3',
		'info'       => array(
			'title'       => $site_name . ' REST API',
			'description' => $site_description !== ''
				? $site_description
				: 'WordPress REST API for ' . $site_name,
			'version'     => (string) ( $site_version ?: '1.0' ),
		),
		'servers'    => array(
			array(
				'url'         => home_url( '/' ),
				'description' => $site_name,
			),
		),
		'paths'      => (object) $paths,
		'components' => array(
			'schemas' => build_openapi_schemas(),
		),
	);

	/**
	 * Filter the complete OpenAPI document before serialization.
	 *
	 * Plugins can add `securitySchemes`, custom `tags`, additional `servers`
	 * entries (e.g. staging URLs), or modify `info` metadata. The schema is
	 * already populated with all REST routes and component shapes by this point.
	 *
	 * @param array $openapi
	 */
	$openapi = apply_filters( 'ajaco_openapi_spec', $openapi );

	return (string) wp_json_encode( $openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
}

/**
 * Convert a WordPress REST route regex (e.g. `/wp/v2/posts/(?P<id>[\d]+)`)
 * into an OpenAPI templated path (`/wp/v2/posts/{id}`). Handles nested
 * parentheses within the param's regex by counting paren depth.
 *
 * @param string $route
 * @return string
 */
function convert_route_to_openapi_path( string $route ): string {
	$out = '';
	$i   = 0;
	$len = strlen( $route );

	while ( $i < $len ) {
		if ( substr( $route, $i, 4 ) === '(?P<' ) {
			$name_end = strpos( $route, '>', $i + 4 );
			if ( $name_end === false ) {
				$out .= $route[ $i++ ];
				continue;
			}
			$name = substr( $route, $i + 4, $name_end - $i - 4 );

			$depth = 1;
			$j     = $name_end + 1;
			while ( $j < $len && $depth > 0 ) {
				$c = $route[ $j ];
				if ( $c === '\\' && $j + 1 < $len ) {
					$j += 2;
					continue;
				}
				if ( $c === '(' ) {
					++$depth;
				} elseif ( $c === ')' ) {
					--$depth;
				}
				++$j;
			}
			$out .= '{' . $name . '}';
			$i    = $j;
		} else {
			$out .= $route[ $i++ ];
		}
	}

	return $out;
}

/**
 * Translate a WP REST arg definition to an OpenAPI 3.0 schema fragment.
 *
 * @param array $arg
 * @return array
 */
function wp_arg_to_openapi_schema( array $arg ): array {
	$schema = array();

	if ( isset( $arg['type'] ) ) {
		$type = is_array( $arg['type'] ) ? reset( $arg['type'] ) : $arg['type'];
		if ( $type === 'integer' || $type === 'number' || $type === 'boolean'
			|| $type === 'string' || $type === 'array' || $type === 'object' ) {
			$schema['type'] = $type;
		} else {
			$schema['type'] = 'string';
		}
		if ( $type === 'array' ) {
			$schema['items'] = isset( $arg['items'] ) && is_array( $arg['items'] )
				? $arg['items']
				: array( 'type' => 'string' );
		}
	}

	if ( array_key_exists( 'default', $arg ) && $arg['default'] !== null ) {
		$schema['default'] = $arg['default'];
	}
	if ( ! empty( $arg['enum'] ) && is_array( $arg['enum'] ) ) {
		$schema['enum'] = array_values( $arg['enum'] );
	}
	if ( isset( $arg['format'] ) ) {
		$schema['format'] = $arg['format'];
	}

	if ( empty( $schema ) ) {
		$schema = array( 'type' => 'string' );
	}
	return $schema;
}

/**
 * Build the OpenAPI parameters array for a given route + method, splitting
 * path placeholders (from the route regex) from query parameters (from args).
 *
 * @param string $route
 * @param array  $args
 * @param string $method_lc Lowercased HTTP method.
 * @return array
 */
function build_openapi_parameters( string $route, array $args, string $method_lc ): array {
	$params           = array();
	$path_param_names = array();

	if ( preg_match_all( '#\(\?P<(\w+)>#', $route, $m ) ) {
		$path_param_names = $m[1];
	}

	foreach ( $path_param_names as $name ) {
		$arg   = isset( $args[ $name ] ) && is_array( $args[ $name ] ) ? $args[ $name ] : array();
		$param = array(
			'name'     => $name,
			'in'       => 'path',
			'required' => true,
			'schema'   => wp_arg_to_openapi_schema( $arg ),
		);
		if ( ! empty( $arg['description'] ) ) {
			$param['description'] = $arg['description'];
		}
		$params[] = $param;
	}

	if ( in_array( $method_lc, array( 'get', 'delete' ), true ) ) {
		foreach ( $args as $name => $arg ) {
			if ( in_array( $name, $path_param_names, true ) ) {
				continue;
			}
			if ( ! is_array( $arg ) ) {
				continue;
			}
			$param = array(
				'name'   => $name,
				'in'     => 'query',
				'schema' => wp_arg_to_openapi_schema( $arg ),
			);
			if ( ! empty( $arg['required'] ) ) {
				$param['required'] = true;
			}
			if ( ! empty( $arg['description'] ) ) {
				$param['description'] = $arg['description'];
			}
			$params[] = $param;
		}
	}

	return $params;
}

/**
 * Build the requestBody object for write methods, using non-path args as the
 * JSON body schema.
 *
 * @param array  $args
 * @param string $route
 * @return array|null
 */
function build_openapi_request_body( array $args, string $route ): ?array {
	$path_param_names = array();
	if ( preg_match_all( '#\(\?P<(\w+)>#', $route, $m ) ) {
		$path_param_names = $m[1];
	}

	$properties = array();
	$required   = array();
	foreach ( $args as $name => $arg ) {
		if ( in_array( $name, $path_param_names, true ) || ! is_array( $arg ) ) {
			continue;
		}
		$prop = wp_arg_to_openapi_schema( $arg );
		if ( ! empty( $arg['description'] ) ) {
			$prop['description'] = $arg['description'];
		}
		$properties[ $name ] = $prop;
		if ( ! empty( $arg['required'] ) ) {
			$required[] = $name;
		}
	}

	if ( empty( $properties ) ) {
		return null;
	}

	$schema = array(
		'type'       => 'object',
		'properties' => $properties,
	);
	if ( ! empty( $required ) ) {
		$schema['required'] = $required;
	}

	return array(
		'content' => array(
			'application/json' => array( 'schema' => $schema ),
		),
	);
}

/**
 * Component schemas for the canonical WP REST resource shapes.
 *
 * @return array<string, array>
 */
function build_openapi_schemas(): array {
	$rendered = array(
		'type'       => 'object',
		'properties' => array( 'rendered' => array( 'type' => 'string' ) ),
	);

	return array(
		'Post'  => array(
			'type'       => 'object',
			'properties' => array(
				'id'             => array( 'type' => 'integer' ),
				'date'           => array(
					'type'   => 'string',
					'format' => 'date-time',
				),
				'date_gmt'       => array(
					'type'   => 'string',
					'format' => 'date-time',
				),
				'modified'       => array(
					'type'   => 'string',
					'format' => 'date-time',
				),
				'slug'           => array( 'type' => 'string' ),
				'status'         => array( 'type' => 'string' ),
				'type'           => array( 'type' => 'string' ),
				'link'           => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'title'          => $rendered,
				'content'        => $rendered,
				'excerpt'        => $rendered,
				'author'         => array( 'type' => 'integer' ),
				'featured_media' => array( 'type' => 'integer' ),
				'categories'     => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'tags'           => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
			),
		),
		'Page'  => array(
			'type'       => 'object',
			'properties' => array(
				'id'         => array( 'type' => 'integer' ),
				'date'       => array(
					'type'   => 'string',
					'format' => 'date-time',
				),
				'slug'       => array( 'type' => 'string' ),
				'status'     => array( 'type' => 'string' ),
				'type'       => array( 'type' => 'string' ),
				'link'       => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'title'      => $rendered,
				'content'    => $rendered,
				'excerpt'    => $rendered,
				'parent'     => array( 'type' => 'integer' ),
				'menu_order' => array( 'type' => 'integer' ),
			),
		),
		'Media' => array(
			'type'       => 'object',
			'properties' => array(
				'id'            => array( 'type' => 'integer' ),
				'date'          => array(
					'type'   => 'string',
					'format' => 'date-time',
				),
				'slug'          => array( 'type' => 'string' ),
				'media_type'    => array( 'type' => 'string' ),
				'mime_type'     => array( 'type' => 'string' ),
				'source_url'    => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'alt_text'      => array( 'type' => 'string' ),
				'caption'       => $rendered,
				'media_details' => array( 'type' => 'object' ),
			),
		),
		'Term'  => array(
			'type'       => 'object',
			'properties' => array(
				'id'          => array( 'type' => 'integer' ),
				'count'       => array( 'type' => 'integer' ),
				'description' => array( 'type' => 'string' ),
				'link'        => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'name'        => array( 'type' => 'string' ),
				'slug'        => array( 'type' => 'string' ),
				'taxonomy'    => array( 'type' => 'string' ),
				'parent'      => array( 'type' => 'integer' ),
			),
		),
		'User'  => array(
			'type'       => 'object',
			'properties' => array(
				'id'          => array( 'type' => 'integer' ),
				'name'        => array( 'type' => 'string' ),
				'url'         => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'description' => array( 'type' => 'string' ),
				'link'        => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'slug'        => array( 'type' => 'string' ),
				'avatar_urls' => array( 'type' => 'object' ),
			),
		),
	);
}
