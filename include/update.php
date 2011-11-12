<?php
function update_to_006() {
	global $wpdb;
	$postmetas = $wpdb->get_results('SELECT post_id FROM ' . $wpdb->postmeta . ' WHERE meta_key LIKE "file_to_ftp"');
	if( $postmetas ) {
		foreach( $postmetas as $postmeta ) {
			$meta_date = get_post_meta($postmeta->post_id, 'file_to_ftp', true);
			if( !is_array($meta_date) ) {
				$dir = wp_upload_dir(get_the_time('Y-m-d H:i:s', $postmeta->post_id));
				$metadate = array('up_time' => $meta_date, 'up_dir' => $dir['subdir']);
				if( substr($metadate['up_dir'], 0, 1) == '/' ) {
					$metadate['up_dir'] = substr($metadate['up_dir'], 1);
				}
				if( substr($ftp_dir, -1) == '/' ) {
					$metadate['up_dir'] = substr($metadate['up_dir'], 0, -1);
				}
				update_post_meta($postmeta->post_id, 'file_to_ftp', $metadate);
			}
		}
	}
	update_option('U2FTP_version', '0.0.6');
}

function update_to_008() {
	$u2ftp_options = get_option('U2FTP_options', array());
	$u2ftp_options['auto_delete_local'] = 0;
	$u2ftp_options['save_original_file'] = 1;
	update_option('U2FTP_options', $u2ftp_options);
	update_option('U2FTP_version', '0.0.8');
}

function update_to_009() {
	global $wpdb;
	$postmetas = $wpdb->get_results('SELECT post_id FROM ' . $wpdb->postmeta . ' WHERE meta_key LIKE "file_to_ftp"');
	if( $postmetas ) {
		foreach( $postmetas as $postmeta ) {
			$meta_date = get_post_meta($postmeta->post_id, 'file_to_ftp', true);
			if( strlen($meta_date['up_dir']) > 1 ) {
				if( substr($meta_date['up_dir'], -1) != '/' ) {
					$meta_date['up_dir'] .= '/';
					echo($meta_date['up_dir']);
					update_post_meta($postmeta->post_id, 'file_to_ftp', $meta_date);
				}
			} else {
				$meta_date['up_dir'] = '';
				update_post_meta($postmeta->post_id, 'file_to_ftp', $meta_date);
			}
		}
	}
	update_option('U2FTP_version', '0.0.9');
}
?>