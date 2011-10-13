<?php
/*
Plugin Name: Upload to FTP
Plugin URI: http://wwpteach.com/upload-to-ftp
Description: let you can upload file to and download host 
Version: 0.0.1
Author: Richer Yang
Author URI: http://fantasyworld.idv.tw/
*/

if( is_admin() ) {
	$currentLocale = get_locale();
	if( !empty($currentLocale) )
	{
		$moFile = dirname(__FILE__) . '/lang/' . $currentLocale . '.mo';
		if( @file_exists($moFile) && is_readable($moFile) )
		{
			load_textdomain('upload-to-ftp', $moFile);
		}
	}
	include(dirname( __FILE__ ) . '/admin.php');
}

add_action('activate_upload-to-ftp/upload-to-ftp.php', 'upload_to_ftp_init');
function upload_to_ftp_init() {
	$u2ftp_options = array();
	$u2ftp_options['ftp_host'] = '';
	$u2ftp_options['ftp_username'] = '';
	$u2ftp_options['ftp_password'] = '';
	$u2ftp_options['ftp_port'] = 21;
	$u2ftp_options['ftp_dir'] = '/public_html/';
	$u2ftp_options['html_link_url'] = 'http://';
	$u2ftp_options['rename_file'] = 1;
	add_option('U2FTP_options', $u2ftp_options, 'Upload to FTP Options');
}

class Upload_to_FTP {
	var $files;
	var $options;

	function Upload_to_FTP() {
		$this->files = array(
			'upload' => array(),
			'dir' => wp_upload_dir()
		);
		$this->options = get_option('U2FTP_options', array());

		if( $this->options['rename_file'] == 1 ) {
			add_filter('sanitize_file_name', array(&$this, 'file_rename'));
		}
		add_action('add_attachment', array(&$this, 'add_main_file'));
		add_filter('wp_generate_attachment_metadata', array(&$this, 'add_thumbnail'), 10, 2);
//		add_filter('attachment_fields_to_save',  array(&$this, 'do_upload'), 10, 2);
		add_filter('wp_get_attachment_image_attributes', array(&$this, 'resrc_file'), 10, 2);
		add_filter('wp_get_attachment_url', array(&$this, 'reurl_file'), 10, 2);
	}

	function add_main_file($att_id) {
		echo('add_main_file<br />');
		$att_file = pathinfo(get_attached_file($att_id));
		$this->files['att_id'] = $att_id;
		$this->files['upload'][] = $att_file['basename'];
	}

	function add_thumbnail($att_file) {
		echo('add_thumbnail<br />');
		if( isset($att_file['sizes']['thumbnail']['file']) ) {
			$this->files['upload'][] = $att_file['sizes']['thumbnail']['file'];
		}
		if( isset($att_file['sizes']['medium']['file']) ) {
			$this->files['upload'][] = $att_file['sizes']['medium']['file'];
		}
		if( isset($att_file['sizes']['large']['file']) ) {
			$this->files['upload'][] = $att_file['sizes']['large']['file'];
		}
		$this->do_upload();
		return $att_file;
	}

	function file_rename($file_name) {
		echo('file_rename<br />');
		$parts = explode('.', $file_name);
		if( count($parts) < 2 ) {
			return md5($file_name);
		}
		$filename = array_shift($parts);
		$extension = array_pop($parts);
		return substr(md5($filename), 0, 10) . '.' . $extension;
	}

	function do_upload($metadata = '') {
		echo('do_upload<br />');
		set_time_limit(600);
		$ftpc = @ftp_connect($this->options['ftp_host'], $this->options['ftp_port'], 30);
		if( @ftp_login($ftpc , $this->options['ftp_username'], $this->options['ftp_password']) ) {
			ftp_pasv($ftpc, true);
			$subdir = explode('/', $this->files['dir']['subdir']);
			$now_dir = $this->options['ftp_dir'];
			$len = count($subdir);
			for( $i = 1; $i < $len; $i++ ) {
				$now_dir .= $subdir[$i] . '/';
				if( @!ftp_chdir($ftpc, $now_dir) ) {
					ftp_mkdir($ftpc, $now_dir);
				}
			}
			foreach( $this->files['upload'] as $file_name ) {
				ftp_put($ftpc, $now_dir . $file_name, $this->files['dir']['path'] . '/' . $file_name, FTP_BINARY);
			}
			add_post_meta($this->files['att_id'], 'file_to_ftp', 1, true);
		}
		return $metadata;
	}

	function resrc_file($attr) {
			global $wpdb;
		$file_name = substr($attr['src'], strlen($this->files['dir']['url']) + 1);
		$att_id = $wpdb->get_var('SELECT post_id FROM ' . $wpdb->postmeta . ' WHERE meta_key="_wp_attachment_metadata" AND meta_value LIKE "%' . $file_name . '%" LIMIT 1');
		$is_upload = get_post_meta($att_id, 'file_to_ftp', true);
		if( $is_upload ) {
			$attr['src'] = $this->options['html_link_url'] . $file_name;
		}
		return $attr;
	}

	function reurl_file($url) {
		global $wpdb;
		$file_name = substr($url, strlen($this->files['dir']['url']) + 1);
		$att_id = $wpdb->get_var('SELECT post_id FROM ' . $wpdb->postmeta . ' WHERE (meta_key="_wp_attachment_metadata" OR meta_key="_wp_attached_file") AND meta_value LIKE "%' . $file_name . '%" LIMIT 1');
		$is_upload = get_post_meta($att_id, 'file_to_ftp', true);
		if( $is_upload ) {
			$url = $this->options['html_link_url'] . $file_name;
		}
		return $url;
	}
}

$u2ftp = new Upload_to_FTP;
?>