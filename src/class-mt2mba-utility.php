<?php
/**
 * Utility functions used by Markup-by-Attribute
 * 
 * @author  Mark Tomlinson
 * 
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit( );

class MT2MBA_UTILITY
{
	/**
	 * Initialization method visible before instantiation
	 */
	public static function init()
	{
		// As a static method, it can not use '$this' and must use an
		// instantiated version of itself
		$self	= new self();

		// Set initialization method to run on 'wp_loaded'.
		add_filter( 'wp_loaded', array( $self, 'on_loaded' ) );
	}

	/**
	 * Hook into Wordpress and WooCommerce
	 * Method runs on 'wp_loaded' hook
	 */
	public function on_loaded()
	{
        // Check database version
		if ( get_site_option( 'mt2mba_db_version' ) < MT2MBA_DB_VERSION )
		{
            // And upgrade if necessary
            $this->mt2mba_db_upgrade();
		}
	}

    /**
     * Database has been determined to be wrong version; upgrade
     */
	function mt2mba_db_upgrade()
	{
		// --------------------------------------------------------------
		// Update database from version 1.x. Leave 1.x data for fallback.
        // --------------------------------------------------------------
		global $wpdb;
	
        // Failsafe
		if ( get_site_option( 'mt2mba_db_version' ) >= MT2MBA_DB_VERSION )
		{
			return;
		}

        // Add prefix to attribute markup meta data key
		$results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}termmeta WHERE meta_key LIKE 'markup'" );
		foreach( $results as $row )
		{
			if( strpos($row->meta_key, 'mt2mba_' ) === FALSE )
			{
				add_term_meta( $row->term_id, "mt2mba_" . $row->meta_key, $row->meta_value, TRUE );
			}
		}

		// Add markup description to attribute terms
		global $attrb_markup_desc_beg;
		global $attrb_markup_desc_end;
		$results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}termmeta WHERE meta_key LIKE 'mt2mba_markup'" );
		foreach( $results as $row )
		{
			$term = get_term( (integer) $row->term_id );
			$description = trim( $this->remove_bracketed_string( $attrb_markup_desc_beg, $attrb_markup_desc_end, trim( $term->description ) ) );
			$description .= PHP_EOL . $attrb_markup_desc_beg . $row->meta_value . $attrb_markup_desc_end;
			wp_update_term( $row->term_id, $term->taxonomy, array( 'description' => trim( $description ) ) );
		}

		// Add prefix to product markup meta data
		$results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}postmeta WHERE `meta_key` LIKE '%_markup_amount'" );
		foreach( $results as $row )
		{
			if( strpos($row->meta_key, 'mt2mba_' ) === FALSE )
			{
				add_post_meta( $row->post_id, "mt2mba_" . $row->meta_key, $row->meta_value, TRUE );
			}
        }
        
		// Bracket description and save base regular price
        global $mt2mba_price_meta;
		global $product_markup_desc_beg;
		global $product_markup_desc_end;
		$last_parent_id = '';
        $results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}postmeta WHERE `meta_value` LIKE '%{$mt2mba_price_meta}%'" );
		foreach( $results as $row )
		{
			if( ( strpos( $row->meta_value, $product_markup_desc_beg ) === FALSE ) && ( strpos( $row->meta_value, $mt2mba_price_meta ) !== FALSE ) )
			{
                update_post_meta( $row->post_id, $row->meta_key, $product_markup_desc_beg . $row->meta_value . $product_markup_desc_end );
			}
			$v_product  = get_post( $row->post_id, 'ARRAY_A' );
			if( $last_parent_id != $v_product[ 'post_parent' ] )
			{
				$beg            = strpos( $row->meta_value, $mt2mba_price_meta ) + strlen( $mt2mba_price_meta );
				$end            = strpos( $row->meta_value, PHP_EOL );
				$base_price     = preg_replace( '/[^\p{L}\p{N}\s\.]/u', '', substr( $row->meta_value, $beg, $end - $beg ) );
				update_post_meta( $v_product[ 'post_parent' ], 'mt2mba_base_regular_price', $base_price );
				$last_parent_id = $v_product[ 'post_parent' ];
			}
        }

        // Made it this far, update database version
        update_option( 'mt2mba_db_version', MT2MBA_DB_VERSION );
	}

    /**
    * Remove pricing information from string
    * @param  string $beginning    Marker at the beginning of the string to be removed
    * @param  string $ending       Marker at the ending of the string to be removed
    * @param  string $string       The string to be processed
    * @return string               The string minus the text to be removed and the beginning and ending markers
    */
    public function remove_bracketed_string($beginning, $ending, $string)
	{
		$beginningPos = strpos( $string, $beginning, 0 );
		$endingPos    = strpos( $string, $ending, $beginningPos );
		if ( $beginningPos === FALSE || $endingPos === FALSE )
		{
			return $string;
		}
		$textToDelete = substr( $string, $beginningPos, ( $endingPos + strlen( $ending ) ) - $beginningPos );
		return str_replace( $textToDelete, '', $string );
    }
}
?>