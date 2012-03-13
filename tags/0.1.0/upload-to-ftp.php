<?php
/*
Plugin Name: Upload to FTP
Plugin URI: http://wwpteach.com/upload-to-ftp
Description: let you can upload file to and download host 
Version: 0.1.0
Author: Richer Yang
Author URI: http://fantasyworld.idv.tw/
*/

if( is_callable('set_time_limit') ) {
	set_time_limit(60);
}

if( is_admin() ) {
	include(dirname(__FILE__) . '/include/update.php');
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
	add_option('U2FTP_options', array(), 'Upload to FTP Options');
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
	$u2ftp_options['html_file_line_ok'] = false;
	$u2ftp_options['rename_file'] = 1;
	$u2ftp_options['auto_delete_local'] = 0;
	$u2ftp_options['save_original_file'] = 1;
	update_option('U2FTP_options', $u2ftp_options);
	update_option('U2FTP_version', '0.1.0');
}

class Upload_to_FTP {
	var $options;
	var $ftpc;

	function __construct() {
		$this->Upload_to_FTP();
	}

	function __desctruct() {
		$this->close_ftp();
	}

	protected function Upload_to_FTP() {
		$this->ftpc = false;
		$this->options = get_option('U2FTP_options', array());

		if( !isset($this->options['ftp_uplode_ok']) ) {
			add_action('admin_notices', array(&$this, 'show_notices'));
			$this->options['ftp_uplode_ok'] = false;
		}
		
		if( (bool) $this->options['rename_file'] ) {
			add_filter('sanitize_file_name', array(&$this, 'file_rename'));
		}

		add_action('add_attachment', array(&$this, 'upload_main_file'));
		add_filter('update_attached_file', array(&$this, 'upload_edit_file'));
		add_filter('image_make_intermediate_size', array(&$this, 'upload_intermediate_file'));

		add_filter('wp_delete_file', array(&$this, 'do_delete_file'));

		add_filter('load_image_to_edit_filesystempath', array(&$this, 'load_ftp_file_to_edit'), 10, 2);
		add_filter('wp_get_attachment_image_attributes', array(&$this, 'resrc_file'), 10, 2);
		add_filter('wp_get_attachment_url', array(&$this, 'reurl_file'), 10, 2);
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

	function upload_main_file($att_id) {
		$att_file = get_attached_file($att_id);
		if( $this->do_upload_file($att_file) ) {
			$dir = $this->clear_basedir($att_file);
			$dir = substr($dir, 0, strrpos($dir, '/') + 1);
			$metadate = array('up_time' => time(), 'up_dir' => $dir);
			add_post_meta($att_id, 'file_to_ftp', $metadate, true);
			if( $this->options['auto_delete_local'] == 1 && $this->options['save_original_file'] == 0 ) {
				unlink($att_file);
			}
		}
	}

	function upload_edit_file($file) {
		$this->do_upload_file($file);
		return $file;
	}

	function upload_intermediate_file($file) {
		if( $this->do_upload_file($file) ) {
			if( $this->options['auto_delete_local'] == 1 ) {
				@unlink($file);
			}
		}
		return $file;
	}

	function do_upload_file($file) {
		if( $this->options['ftp_uplode_ok'] && $this->open_ftp() ) {
			$dir = $this->clear_basedir($file);
			$dir = '/' . substr($dir, 0, strrpos($dir, '/'));
			$dir = $this->u2ftp_mkdir($dir);
			return @ftp_put($this->ftpc, $dir . basename($file), $file, FTP_BINARY);
		}
		return false;
	}

	function do_delete_file($file) {
		if( $this->options['ftp_delete_ok'] && $this->open_ftp() ) {
			$ftp_file = $this->clear_basedir($file);
			@ftp_delete($this->ftpc, $this->options['ftp_dir'] . $ftp_file);
		}
		return $file;
	}

	function load_ftp_file_to_edit($file) {
		if( !file_exists($file) ) {
			if( function_exists('fopen') && function_exists('ini_get') && true == ini_get('allow_url_fopen') ) {
				$file = $this->clear_basedir($file);
				$file = $this->options['html_link_url'] . '/' . $file;
			} else {
				return '';
			}
		}
		return $file;
	}

	function resrc_file($attr, $att) {
		$file_name = basename($attr['src']);
		$meta_date = get_post_meta($att->ID, 'file_to_ftp', true);
		if( isset($meta_date['up_time']) && $meta_date['up_time'] >= 1 ) {
			$attr['src'] = $this->options['html_link_url'] . $meta_date['up_dir'] . $file_name;
		}
		return $attr;
	}

	function reurl_file($url, $att_id) {
		$file_name = basename($url);
		$meta_date = get_post_meta($att_id, 'file_to_ftp', true);
		if( isset($meta_date['up_time']) && $meta_date['up_time'] >= 1 ) {
			$url = $this->options['html_link_url'] . $meta_date['up_dir'] .  $file_name;
		}
		return $url;
	}

	function clear_basedir($file) {
		if( ($uploads = wp_upload_dir()) && false === $uploads['error'] ) {
			if( 0 === strpos($file, $uploads['basedir']) ) {
				$file = str_replace($uploads['basedir'], '', $file);
				$file = ltrim($file, '/');
			}
		}
		return $file;
	}

	function open_ftp() {
		if( $this->ftpc ) {
			return true;
		}
		$this->ftpc = @ftp_connect($this->options['ftp_host'], $this->options['ftp_port'], $this->options['ftp_timeout']);
		if( $this->ftpc ) {
			if( @ftp_login($this->ftpc , $this->options['ftp_username'], $this->options['ftp_password']) ) {
				@ftp_pasv($this->ftpc, (bool) $this->options['ftp_mode']);
				return true;
			} else {
				@ftp_close($this->ftpc);
				$this->ftpc = false;
			}
		}
		return false;
	}

	function u2ftp_mkdir($dir) {
		if( !$this->ftpc ) {
			$this->open_ftp();
		}
		$dir = explode('/', $dir);
		$now_dir = $this->options['ftp_dir'];
		$len = count($dir);
		for( $i = 1; $i < $len; $i++ ) {
			$now_dir .= $dir[$i] . '/';
			if( !@ftp_chdir($this->ftpc, $now_dir) ) {
				@ftp_mkdir($this->ftpc, $now_dir);
			}
		}
		return $now_dir;
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