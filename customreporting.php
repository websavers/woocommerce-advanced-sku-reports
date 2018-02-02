<?php
/**
 * Plugin Name: WooCommerce Custom Stock Reporting
 * Description: Provides ability to customize the Admin Stock reporting of WooCommerce
 * Plugin URI: https://plugins.websavers.ca/wc_csr
 * Author: Moe Saidi / Websaavers, Inc.
 * Author URI: https://moe.saidi.ca / https://websavers.ca
 * Version: 0.1a
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: watchlist-textdomain
== 
*/

 
/*
* Create a top level menu for now
* TODO: Menu should be sub to wooCommerce
*/
add_action('admin_menu', 'stock_report_admin_menu');

/*
* Basic functionality to create the stock report administration menu
*/
function stock_report_admin_menu() {
	add_submenu_page( 'woocommerce', 'Advanced Reports', 'Advanced Reports', 'manage_options', 'stock_report', 'admin_stock_report_page' );
}

/*
* Basic page creation
* TODO: Include options and a form submission
*/
function admin_stock_report_page() {
	
	global $plugin_page;
	if (!isset($_POST['submit'])){
		?>
		<div class="wrap">
		    <h2>Websavers (WooCommerce) Advanced Stock Reporting</h2>
		    <p>Make your selection and click 'Submit' to prepare a table of results</p>
		    <div class="wrap">
		    	<form method="post" id="advanced_report" action="">
		    		Show Sku: (Not Working yet) <input type="checkbox" name="sku" value="checked"/> <br/>
		    		Order: (Not Working Yet) <select name="order">
		    			<option value="asc">Ascending</option>
		    			<option value="desc">Descending</option>
		    		</select>
		    		<?php submit_button('Stock Report', 'primary', 'submit'); ?>
		    	</form>
		    </div>
		</div>
		<?php 
	}else{
		?>
		<div class="wrap">
		<h2>Websavers (WooCommerce) Advanced Stock Reporting</h2>
		<p>Your stock report is outlined below</p>
		<?php generate_stock_report_sku_asc(); ?>
		</div>
		<?php
	}
}



/*
* I gave up trying 
*/
function generate_stock_report_sku_asc() {
	$args=array(
		'post_type'			=>	'product',
		'post_status'		=>	'publish',
		'posts_per_page'	=>	-1,
		'orderby'			=>	'sku',
		'order'				=>	'DESC',
		'meta_query'		=> array(
			array(
				'key'		=>	'_manage_stock',
				'value'		=>	'yes'
			)
		),
		'tax_query'			=>	array(
			array(
				'taxonomy'	=>	'product_type',
				'field'		=>	'slug',
				'terms'		=>	array('simple'),
				'operator'	=>	'IN'
			)
		)
	);
	?>
	<table border="0" align="left" width="40%">
		<tr><th>SKU</th>
			<th>Product</th>
			<th>QUantity</th>
	<?php
	$loop = new WP_Query ($args);
	while($loop->have_posts()) : $loop->the_post();
		global $product;
		$row = array($product->get_sku(),$product->get_title(),$product->get_stock_quantity());
		?>
		<tr align="center"><td><?php echo $row[0]; ?></td>
		<td><?php echo $row[1]; ?></td>
		<td><?php echo $row[2]; ?></td></tr>
		<?php
	endwhile;
	echo "</table>";

	$args = array(
		'post_type'			=> 'product_variation',
		'post_status' 		=> 'publish',
        'posts_per_page' 	=> -1,
        'orderby'			=> 'sku',
        'order'				=> 'ASC',
		'meta_query' => array(
			array(
				'key' 		=> '_stock',
				'value' 	=> array('', false, null),
				'compare' 	=> 'NOT IN'
			)
		)
	);
	
	$loop = new WP_Query( $args );
	while ( $loop->have_posts() ) : $loop->the_post();
	
        $product = new WC_Product_Variation( $loop->post->ID );
		
		$row = array( $product->get_title() . ', ' . get_the_title( $product->variation_id ), $product->stock );
		var_dump($row);
	endwhile;


}
