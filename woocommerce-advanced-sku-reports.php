<?php
/**
 * Plugin Name: WooCommerce Advanced SKU Reports
 * Description: WooCommerce inventory report with SKU sort
 * Author: Websavers Inc.
 * Author URI: https://websavers.ca
 * Version: 1.1
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wcasr
== 
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

  add_action('woocommerce_loaded' , function (){
			
			add_filter( 'woocommerce_admin_reports', 'wcasr_add_sku_report' );
			function wcasr_add_sku_report($reports){
				$reports['stock']['reports']['all_by_sku'] = array(
						'title'       => __( 'All stock by SKU', 'wcasr' ),
						'description' => '',
						'hide_title'  => true,
						'callback'    => 'wcasr_admin_stock_report_page',
				);
				return $reports;
			}
			
			function wcasr_admin_stock_report_page() {
					$report = new WC_Report_All_Stock();
					$report->output_report();
			}
      
      
      if ( ! class_exists( 'WC_Report_Stock' ) ) {
        require_once WooCommerce::plugin_path() . '/includes/admin/reports/class-wc-report-stock.php';
      }

      class WC_Report_All_Stock extends WC_Report_Stock {
      	/**
      	 * No items found text.
      	 */
      	public function no_items() {
      		_e( 'No products found.', 'wcasr' );
      	}
      	/**
      	 * Get Products matching stock criteria.
      	 *
      	 * @param int $current_page
      	 * @param int $per_page
      	 */
      	public function get_items( $current_page, $per_page ) {
      		global $wpdb;
      		$this->max_items = 0;
      		$this->items     = array();
      		// Get products using a query - this is too advanced for get_posts :(
      		$query_from = apply_filters('woocommerce_report_all_stock_query_from', 
      		"	FROM {$wpdb->posts} as posts
      			INNER JOIN {$wpdb->postmeta} AS postmeta ON posts.ID = postmeta.post_id
      			INNER JOIN {$wpdb->postmeta} AS postmeta2 ON posts.ID = postmeta2.post_id
      			INNER JOIN {$wpdb->postmeta} AS sku ON posts.ID = sku.post_id AND sku.meta_key = '_sku' 
      			WHERE 1=1
      			AND posts.post_type IN ( 'product', 'product_variation' )
      			AND posts.post_status = 'publish'
      			AND postmeta2.meta_key = '_manage_stock' AND postmeta2.meta_value = 'yes'
      			AND postmeta.meta_key = '_stock'
      		"
      		);
      		$this->items     = $wpdb->get_results( $wpdb->prepare( "SELECT sku.meta_value as sku, posts.ID as id, posts.post_parent as parent {$query_from} GROUP BY posts.ID ORDER BY posts.post_title DESC LIMIT %d, %d;", ( $current_page - 1 ) * $per_page, $per_page ) );
      		$this->max_items = $wpdb->get_var( "SELECT COUNT( DISTINCT posts.ID ) {$query_from};" );
      	}
      	
      	/**
      	 * Get columns.
      	 *
      	 * @return array
      	 */
      	public function get_columns() {
      		$columns = array(
      			'sku'					 => __( 'SKU', 'woocommerce' ),
      			'product'      => __( 'Product', 'woocommerce' ),
      			'parent'       => __( 'Parent', 'woocommerce' ),
      			'stock_level'  => __( 'Units in stock', 'woocommerce' ),
      			'stock_status' => __( 'Stock status', 'woocommerce' ),
      			'wc_actions'   => __( 'Actions', 'woocommerce' ),
      		);
      		return $columns;
      	}
      	
      	/**
      	 * Method for name column
      	 *
      	 * @param array $item an array of DB data
      	 *
      	 * @return string
      	 */

      	function column_sku( $item ) {
      	  return '<strong>' . $item->sku . '</strong>';
      	}
      	
        /** Override this to remove the sku from it
         *  Should make it more efficient
         */
      	function column_product( $item ) {
      		global $product;
      		
      		if ( ! $product || $product->get_id() !== $item->id ) {
      			$product = wc_get_product( $item->id );
      		}
      		if ( ! $product ) {
      			return;
      		}
      		echo esc_html( $product->get_name() );
      		// Get variation data.
      		if ( $product->is_type( 'variation' ) ) {
      			echo '<div class="description">' . wp_kses_post( wc_get_formatted_variation( $product, true ) ) . '</div>';
      		}
      	}
      	
      	/**
      	 * Columns to make sortable.
      	 *
      	 * @return array
      	 */
      	public function get_sortable_columns() {
      	  $sortable_columns = array(
      	    'sku' => array( 'sku', true ),
      	    'product' => array( 'product', false ),
      			'stock_level' => array( 'stock_level', true ),
      	  );

      	  return $sortable_columns;
      	}
      	
      	function usort_reorder( $a, $b ) {
      	  // If no sort, default to title
      	  $orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'sku';
      	  // If no order, default to asc
      	  $order = ( ! empty($_GET['order'] ) ) ? $_GET['order'] : 'asc';
      	  // Determine sort order
      	  $result = strcmp( $a->$orderby, $b->$orderby );
      	  // Send final sort direction to usort
      	  return ( $order === 'asc' ) ? $result : -$result;
      	}
      	
      	/*
      	 * Prepare items.
      	 */
      	public function prepare_items() {
      		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
      		$current_page          = absint( $this->get_pagenum() );
      		$per_page              = apply_filters( 'woocommerce_admin_stock_report_products_per_page', 20 );
      		$this->get_items( $current_page, $per_page );
      		
      		usort( $this->items, array( &$this, 'usort_reorder' ) );
      		/**
      		 * Pagination.
      		 */
      		$this->set_pagination_args(
      			array(
      				'total_items' => $this->max_items,
      				'per_page'    => $per_page,
      				'total_pages' => ceil( $this->max_items / $per_page ),
      			)
      		);
      	}
      }
			
  });//woocomm loaded

}//woocomm installed
