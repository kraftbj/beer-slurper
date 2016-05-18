<?php
namespace Kraft\Beer_Slurper\CPT;

add_action( 'init', '\Kraft\Beer_Slurper\CPT\init_cpt', 0 );
add_action( 'init', '\Kraft\Beer_Slurper\CPT\init_tax_brewery', 0 );
add_action( 'init', '\Kraft\Beer_Slurper\CPT\init_tax_style', 0 );

// Register Custom Post Type
function init_cpt() {

	$labels = array(
		'name'                  => _x( 'Beers', 'Post Type General Name', 'beer_slurper' ),
		'singular_name'         => _x( 'Beer', 'Post Type Singular Name', 'beer_slurper' ),
		'menu_name'             => __( 'Beers', 'beer_slurper' ),
		'name_admin_bar'        => __( 'Beers', 'beer_slurper' ),
		'archives'              => __( 'Beer Archives', 'beer_slurper' ),
		'parent_item_colon'     => __( 'Parent Item:', 'beer_slurper' ),
		'all_items'             => __( 'All Beers', 'beer_slurper' ),
		'add_new_item'          => __( 'Add New Beer', 'beer_slurper' ),
		'add_new'               => __( 'Add New', 'beer_slurper' ),
		'new_item'              => __( 'New Beer', 'beer_slurper' ),
		'edit_item'             => __( 'Edit Beer', 'beer_slurper' ),
		'update_item'           => __( 'Update Beer', 'beer_slurper' ),
		'view_item'             => __( 'View Beer', 'beer_slurper' ),
		'search_items'          => __( 'Search Beer', 'beer_slurper' ),
		'not_found'             => __( 'Not found', 'beer_slurper' ),
		'not_found_in_trash'    => __( 'Not found in Trash', 'beer_slurper' ),
		'featured_image'        => __( 'Beer Image', 'beer_slurper' ),
		'set_featured_image'    => __( 'Set beer image', 'beer_slurper' ),
		'remove_featured_image' => __( 'Remove beer image', 'beer_slurper' ),
		'use_featured_image'    => __( 'Use as featured image', 'beer_slurper' ),
		'insert_into_item'      => __( 'Insert into beer', 'beer_slurper' ),
		'uploaded_to_this_item' => __( 'Uploaded to this beer', 'beer_slurper' ),
		'items_list'            => __( 'Beers list', 'beer_slurper' ),
		'items_list_navigation' => __( 'Beers list navigation', 'beer_slurper' ),
		'filter_items_list'     => __( 'Filter beers list', 'beer_slurper' ),
	);
	$rewrite = array(
		'slug'                  => 'beers',
		'with_front'            => false,
		'pages'                 => true,
		'feeds'                 => true,
	);
	$args = array(
		'label'                 => __( 'Beer', 'beer_slurper' ),
		'description'           => __( 'Individual beers', 'beer_slurper' ),
		'labels'                => $labels,
		//'supports'              => array( 'title', 'editor', 'thumbnail', 'comments', 'revisions', ),
		'taxonomies'            => array(),
		'hierarchical'          => false,
		'public'                => true,
		'show_ui'               => true,
		'show_in_menu'          => true,
		'menu_position'         => 5,
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => 'beers',
		'exclude_from_search'   => false,
		'publicly_queryable'    => true,
		'rewrite'               => $rewrite,
		'capability_type'       => 'page',
	);
	register_post_type( BEER_SLURPER_CPT, $args );

}

function init_tax_brewery(){
	$labels = array(
		'name'                       => _x( 'Breweries', 'Taxonomy General Name', 'beer_slurper' ),
		'singular_name'              => _x( 'Brewery', 'Taxonomy Singular Name', 'beer_slurper' ),
		'menu_name'                  => __( 'Breweries', 'beer_slurper' ),
		'all_items'                  => __( 'All Breweries', 'beer_slurper' ),
		'parent_item'                => __( 'Parent Brewery', 'beer_slurper' ),
		'parent_item_colon'          => __( 'Parent Brewery:', 'beer_slurper' ),
		'new_item_name'              => __( 'New Brewery Name', 'beer_slurper' ),
		'add_new_item'               => __( 'Add New Brewery', 'beer_slurper' ),
		'edit_item'                  => __( 'Edit Brewery', 'beer_slurper' ),
		'update_item'                => __( 'Update Brewery', 'beer_slurper' ),
		'view_item'                  => __( 'View Brewery', 'beer_slurper' ),
		'separate_items_with_commas' => __( 'Separate items with commas', 'beer_slurper' ),
		'add_or_remove_items'        => __( 'Add or remove breweries', 'beer_slurper' ),
		'choose_from_most_used'      => __( 'Choose from the most used', 'beer_slurper' ),
		'popular_items'              => __( 'Popular Breweries', 'beer_slurper' ),
		'search_items'               => __( 'Search Breweries', 'beer_slurper' ),
		'not_found'                  => __( 'Not Found', 'beer_slurper' ),
		'no_terms'                   => __( 'No breweries', 'beer_slurper' ),
		'items_list'                 => __( 'Breweries list', 'beer_slurper' ),
		'items_list_navigation'      => __( 'Breweries list navigation', 'beer_slurper' ),
	);
	$rewrite = array(
		'slug'                       => 'brewery',
		'with_front'                 => false,
		'hierarchical'               => true,
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => true,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
		'rewrite'                    => $rewrite,
	);
	register_taxonomy( BEER_SLURPER_TAX_BREWERY, BEER_SLURPER_CPT, $args );
	register_taxonomy_for_object_type( BEER_SLURPER_TAX_BREWERY, BEER_SLURPER_CPT );
}

// BEER_SLURPER_TAX_STYLE
function init_tax_style(){
$labels = array(
		'name'                       => _x( 'Styles', 'Taxonomy General Name', 'beer_slurper' ),
		'singular_name'              => _x( 'Style', 'Taxonomy Singular Name', 'beer_slurper' ),
		'menu_name'                  => __( 'Styles', 'beer_slurper' ),
		'all_items'                  => __( 'All Styles', 'beer_slurper' ),
		'parent_item'                => __( 'Parent Style', 'beer_slurper' ),
		'parent_item_colon'          => __( 'Parent Style:', 'beer_slurper' ),
		'new_item_name'              => __( 'New Style Name', 'beer_slurper' ),
		'add_new_item'               => __( 'Add New Style', 'beer_slurper' ),
		'edit_item'                  => __( 'Edit Style', 'beer_slurper' ),
		'update_item'                => __( 'Update Style', 'beer_slurper' ),
		'view_item'                  => __( 'View Style', 'beer_slurper' ),
		'separate_items_with_commas' => __( 'Separate items with commas', 'beer_slurper' ),
		'add_or_remove_items'        => __( 'Add or removes styles', 'beer_slurper' ),
		'choose_from_most_used'      => __( 'Choose from the most used', 'beer_slurper' ),
		'popular_items'              => __( 'Popular Styles', 'beer_slurper' ),
		'search_items'               => __( 'Search Styles', 'beer_slurper' ),
		'not_found'                  => __( 'Not Found', 'beer_slurper' ),
		'no_terms'                   => __( 'No styles', 'beer_slurper' ),
		'items_list'                 => __( 'Styles list', 'beer_slurper' ),
		'items_list_navigation'      => __( 'Styles list navigation', 'beer_slurper' ),
	);
	$rewrite = array(
		'slug'                       => 'style',
		'with_front'                 => false,
		'hierarchical'               => true,
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => false,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
		'rewrite'                    => $rewrite,
	);
	register_taxonomy( BEER_SLURPER_TAX_STYLE, BEER_SLURPER_CPT, $args );
	register_taxonomy_for_object_type( BEER_SLURPER_TAX_STYLE, BEER_SLURPER_CPT );
}