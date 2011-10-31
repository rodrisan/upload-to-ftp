<?php
/*
Plugin Name: Upload to FTP
Plugin URI: http://wwpteach.com/upload-to-ftp
Description: let you can upload file to and download host 
Version: 0.0.6
Author: Richer Yang
Author URI: http://fantasyworld.idv.tw/
*/

set_time_limit(600);

if( is_admin() ) {
	$currentLocale = get_locale();
	if( !empty($currentLocale) ) {
		$moFile = dirname(__FILE__) . '/lang/' . $currentLocale . '.mo';
		if( @file_exists($moFile) && is_readable($moFile) ) {
			load_textdomain('upload-to-ftp', $moFile);
		}
	}
	include(dirname(__FILE__) . '/admin.php');
}

register_activation_hook(__FILE__, 'upload_to_ftp_init');
function upload_to_ftp_init() {
	$u2ftp_options = array();
	$u2ftp_options['ftp_host'] = '';
	$u2ftp_options['ftp_port'] = 21;
	$u2ftp_options['ftp_timeout'] = 15;
	$u2ftp_options['ftp_username'] = '';
	$u2ftp_options['ftp_password'] = '';
	$u2ftp_options['ftp_mode'] = 1;
	$u2ftp_options['ftp_dir'] = '/public_html/';
	$u2ftp_options['ftp_uplode_ok'] = false;
	$u2ftp_options['html_link_url'] = 'http://';
	$u2ftp_options['ftp_delete_ok'] = false;	
	add_option('U2FTP_options', $u2ftp_options, 'Upload to FTP Options');
}

class Upload_to_FTP {
	var $upload;
	var $delete;
	var $options;
	var $ftpc;

	function Upload_to_FTP() {
		$this->upload = array();
		$this->delete = array();
		$this->ftpc = false;
		$this->options = get_option('U2FTP_options', array());

		if( !isset($this->options['ftp_uplode_ok']) ) {
			add_action('admin_notices', array(&$this, 'show_notices'));
			$this->options['ftp_uplode_ok'] = false;
		}
		
		if( $this->options['rename_file'] ) {
			add_filter('sanitize_file_name', array(&$this, 'file_rename'));
		}
		add_action('add_attachment', array(&$this, 'add_main_file'));
		add_filter('wp_generate_attachment_metadata', array(&$this, 'add_thumbnail'));

		add_action('delete_attachment', array(&$this, 'delete_file_name'));
		add_filter('wp_delete_file', array(&$this, 'add_delete_file'));
		add_action('clean_post_cache', array(&$this, 'do_delete_file'));

		add_filter('wp_get_attachment_image_attributes', array(&$this, 'resrc_file'), 10, 2);
		add_filter('wp_get_attachment_url', array(&$this, 'reurl_file'), 10, 2);

		$version = get_option('U2FTP_version', '0.0.5.1');
		if( version_compare($version, '0.0.6', '<') ) {
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
	}

	function show_notices() {
		printf('<div id="up2ftp_notices" class="updated"><p>' . __('Please go to <a href="%s">Upload to ftp setting page</a> update you options.', 'upload-to-ftp') . '</p></div>', 'options-general.php?page=upload-to-ftp');
	}
	
	function file_rename($file_name) {
		$parts = explode('.', $file_name);
		if( count($parts) < 2 ) {
			return md5($file_name);
		}
		$filename = array_shift($parts);
		$extension = array_pop($parts);
		return substr(md5($filename), 0, 10) . '.' . $extension;
	}

	function add_main_file($att_id) {
		$att_file = pathinfo(get_attached_file($att_id));
		$this->upload['att_id'] = $att_id;
		$this->upload[] = $att_file['basename'];
	}

	function add_thumbnail($att_file) {
		if( isset($att_file['sizes']['thumbnail']['file']) ) {
			$this->upload[] = $att_file['sizes']['thumbnail']['file'];
		}
		if( isset($att_file['sizes']['medium']['file']) ) {
			$this->upload[] = $att_file['sizes']['medium']['file'];
		}
		if( isset($att_file['sizes']['large']['file']) ) {
			$this->upload[] = $att_file['sizes']['large']['file'];
		}
		if( $this->options['ftp_uplode_ok'] ) {
			$this->do_upload();
		}
		return $att_file;
	}

	function do_upload() {
		if( $this->options['ftp_uplode_ok'] && $this->open_ftp() ) {
			$parent_id = wp_get_post_parent_id($this->upload['att_id']);
			if( $parent_id ) {
				$dir = wp_upload_dir(get_the_time('Y-m-d H:i:s', $parent_id));
			} else {
				$dir = wp_upload_dir(get_the_time('Y-m-d H:i:s', $this->upload['att_id']));
			}
			$subdir = explode('/', $dir['subdir']);
			$now_dir = $this->options['ftp_dir'];
			$len = count($subdir);
			for( $i = 1; $i < $len; $i++ ) {
				$now_dir .= $subdir[$i] . '/';
				if( !@ftp_chdir($this->ftpc, $now_dir) ) {
					@ftp_mkdir($this->ftpc, $now_dir);
				}
			}
			for( $i = 0; isset($this->upload[$i]); $i++ ) {
				@ftp_put($this->ftpc, $now_dir . $this->upload[$i], $dir['path'] . '/' . $this->upload[$i], FTP_BINARY);
			}
			$metadate = array('up_time' => time(), 'up_dir' => $dir['subdir']);
			if( substr($metadate['up_dir'], 0, 1) == '/' ) {
				$metadate['up_dir'] = substr($metadate['up_dir'], 1);
			}
			if( substr($ftp_dir, -1) == '/' ) {
				$metadate['up_dir'] = substr($metadate['up_dir'], 0, -1);
			}
			add_post_meta($this->upload['att_id'], 'file_to_ftp', $metadate, true);
			$this->close_ftp();
		}
	}

	function resrc_file($attr, $att) {
		$file_name = basename($attr['src']);
		$meta_date = get_post_meta($att->ID, 'file_to_ftp', true);
		if( $meta_date['up_time'] ) {
			$attr['src'] = $this->options['html_link_url'] . $meta_date['up_dir'] . '/' . $file_name;
		}
		return $attr;
	}

	function reurl_file($url, $att_id) {
		$file_name = basename($url);
		$meta_date = get_post_meta($att_id, 'file_to_ftp', true);
		if( $meta_date['up_time'] ) {
			$url = $this->options['html_link_url'] . $meta_date['up_dir'] . '/' .  $file_name;
		}
		return $url;
	}

	function delete_file_name($att_id) {
		$file_name = get_post_meta($att_id, '_wp_attached_file', true);
		$this->delete['att_id'] = $att_id;
		$this->delete['basename'] = basename($file_name);
	}

	function add_delete_file($file) {
		$file_name = basename($file);
		if( $file_name == $this->delete['basename'] ) {
			$this->delete[] = $file_name;
		}
		return $file;
	}

	function do_delete_file() {
		if( isset($this->delete[0]) ) {
			if( $this->options['ftp_delete_ok'] && $this->open_ftp() ) {
				$meta_date = get_post_meta($att_id, 'file_to_ftp', true);
				for( $i = 0; isset($this->delete[$i]); $i++ ) {
					@ftp_delete($this->ftpc, substr($this->options['ftp_dir'], 0, -1) . $meta_date['subdir'] . '/' . $this->delete[$i]);
				}
				$this->close_ftp();
			}
		}
	}

	function open_ftp() {
		$this->ftpc = @ftp_connect($this->options['ftp_host'], $this->options['ftp_port'], $this->options['ftp_timeout']);
		if( $this->ftpc ) {
			if( @ftp_login($this->ftpc , $this->options['ftp_username'], $this->options['ftp_password']) ) {
				ftp_pasv($this->ftpc, (bool) $this->options['ftp_mode']);
				return true;
			} else {
				@ftp_close($this->ftpc);
				$this->ftpc = false;
			}
		}
		return false;
	}

	function close_ftp() {
		if( $this->ftpc ) {
			@ftp_close($this->ftpc);
			$this->ftpc = false;
		}
	}
}

$u2ftp = new Upload_to_FTP;
?>