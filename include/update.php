<?php
$version = get_option('U2FTP_version', '0.1.5');
if( version_compare($version, '0.0.6', '<') ) {
	update_to_0_0_6();
}
if( version_compare($version, '0.0.8', '<') ) {
	update_to_0_0_8();
}
if( version_compare($version, '0.0.9', '<') ) {
	update_to_0_0_9();
}
if( version_compare($version, '0.1.0', '<') ) {
	update_to_0_1_0();
}
if( version_compare($version, '0.1.1', '<') ) {
	update_to_0_1_1();
}
if( version_compare($version, '0.1.2', '<') ) {
	update_option('U2FTP_version', '0.1.2');
}
if( version_compare($version, '0.1.3', '<') ) {
	update_option('U2FTP_version', '0.1.3');
}
if( version_compare($version, '0.1.4', '<') ) {
	update_option('U2FTP_version', '0.1.4');
}
if( version_compare($version, '0.1.5', '<') ) {
	update_to_0_1_5();
	update_option('U2FTP_version', '0.1.5');
}

function update_to_0_0_6() {
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

function update_to_0_0_8() {
	$u2ftp_options = get_option('U2FTP_options', array());
	$u2ftp_options['auto_delete_local'] = 0;
	$u2ftp_options['save_original_file'] = 1;
	update_option('U2FTP_options', $u2ftp_options);
	update_option('U2FTP_version', '0.0.8');
}

function update_to_0_0_9() {
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

function update_to_0_1_0() {
	$u2ftp_options = get_option('U2FTP_options', array());
	if( $u2ftp_options['ftp_uplode_ok'] && $u2ftp_options['ftp_delete_ok'] ) {
		$u2ftp_options['html_file_line_ok'] = true;
	} else {
		$u2ftp_options['html_file_line_ok'] = false;
	}
	update_option('U2FTP_options', $u2ftp_options);
	update_option('U2FTP_version', '0.1.0');
}

function update_to_0_1_1() {
	update_option('U2FTP_version', '0.1.1');
}

function update_to_0_1_5() {
	global $wpdb;
	$postmetas = $wpdb->get_results('SELECT `post_id` FROM ' . $wpdb->postmeta . " WHERE `meta_key` = 'file_to_ftp'");
	if( $postmetas ) {
		foreach( $postmetas as $postmeta ) {
			$meta_date = get_post_meta($postmeta->post_id, 'file_to_ftp', true);
			$meta_date['up_dir'] = trim($meta_date['up_dir'], '/');
			if( $meta_date['up_dir'] != '' ) {
				$meta_date['up_dir'] .= '/';
			}
			update_post_meta($postmeta->post_id, 'file_to_ftp', $meta_date);
		}
	}
}
?>