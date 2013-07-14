<?php
/*
Plugin Name: Upload to FTP
Plugin URI: http://wwpteach.com/upload-to-ftp
Description: let you can upload file to and download host 
Version: 0.1.3.1
Author: Richer Yang
Author URI: http://fantasyworld.idv.tw/
*/

register_activation_hook(__FILE__, 'upload_to_ftp_init');
function upload_to_ftp_init() {
	$test = get_option('U2FTP_options', array());
	if( count($test) == 0 ) {
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
		update_option('U2FTP_version', '0.1.3.1');
	}
}

if( is_admin() ) {
	if( version_compare(get_option('U2FTP_version', '0.1.3.1'), '0.1.3.1', '!=') ) {
		include(dirname(__FILE__) . '/include/update.php');
	}
	$currentLocale = get_locale();
	if( !empty($currentLocale) ) {
		$moFile = dirname(__FILE__) . '/lang/' . $currentLocale . '.mo';
		if( @is_file($moFile) && is_readable($moFile) ) {
			load_textdomain('upload-to-ftp', $moFile);
		}
	}
	include(dirname(__FILE__) . '/admin.php');
}

class Upload_to_FTP {
	var $options;
	var $ftpc;
	var $add_list;

	function __construct() {
		$this->options = get_option('U2FTP_options', array());
		$this->ftpc = false;
		$this->add_list = array();

		if( !isset($this->options['ftp_uplode_ok']) ) {
			add_action('admin_notices', array(&$this, 'show_notices'));
			$this->options['ftp_uplode_ok'] = false;
		}
		
		if( (bool) $this->options['rename_file'] ) {
			add_filter('sanitize_file_name', array(&$this, 'file_rename'));
		}

		add_filter('wp_update_attachment_metadata', array(&$this, 'set_upload_file'), 10, 2);

		add_filter('wp_delete_file', array(&$this, 'do_delete_file'));

		add_filter('load_image_to_edit_filesystempath', array(&$this, 'load_ftp_file_to_edit'), 10, 2);
		add_filter('wp_get_attachment_image_attributes', array(&$this, 'resrc_file'), 10, 2);
		add_filter('wp_get_attachment_url', array(&$this, 'reurl_file'), 10, 2);

		add_action('shutdown', array(&$this, 'ftp_shutdown'));
	}

	function show_notices() {
		printf('<div id="up2ftp_notices" class="updated"><p>' . __('Please go to <a href="%s">Upload to ftp setting page</a> update you options.', 'upload-to-ftp') . '</p></div>', 'options-general.php?page=upload-to-ftp');
	}

	function file_rename($file_name) {
		$parts = explode('.', $file_name);
		if( preg_match('@^[a-z0-9\-_]*$@i', $parts[0]) ) {
			$file_name = $parts[0];
		} else {
			$file_name = substr(md5($parts[0]), 0, 10);
		}
		if( count($parts) < 2 ) {
			return $file_name;
		} else {
			$extension = array_pop($parts);
			return $file_name . '.' . $extension;
		}
	}

	function set_upload_file($data, $id) {
		$pid = wp_get_post_parent_id($id);
		if( $pid > 0 ) {
			if( $post = get_post($pid) ) {
				if( substr($post->post_date, 0, 4) > 0 ) {
					$time = $post->post_date;
				}
			}
		}
		if( !isset($time) ) {
			$post = get_post($id);
			$time = $post->post_date;
		}
		$uploads = wp_upload_dir($time);
		$local_file = $uploads['basedir'] . '/' . $data['file'];
		$this->add_list[] = array(
			'id' => $id,
			'local_file' => $local_file,
			'ftp_file' => $this->make_local_to_ftp($local_file)
		);
		foreach( $data['sizes'] as $size_data ) {
			$local_file = $uploads['path'] . '/' . $size_data['file'];
			$this->add_list[] = array(
				'id' => 0,
				'local_file' => $local_file,
				'ftp_file' => $this->make_local_to_ftp($local_file)
			);
		}
		$this->do_ftp_upload();
		return $data;
	}

	function do_delete_file($file) {
		if( $this->options['ftp_delete_ok'] && $this->open_ftp() ) {
			$ftp_file = $this->clear_basedir($file);
			@ftp_delete($this->ftpc, $this->options['ftp_dir'] . $ftp_file);
		}
		return $file;
	}

	function load_ftp_file_to_edit($file) {
		if( !is_file($file) ) {
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

	function ftp_shutdown() {
		if( $this->ftpc ) {
			@ftp_close($this->ftpc);
			$this->ftpc = false;
		}
	}

	protected function make_local_to_ftp($local_file) {
		$dir = $this->clear_basedir($local_file);
		$dir = '/' . substr($dir, 0, strrpos($dir, '/'));
		$dir = $this->ftp_mkdir($dir);
		return $dir . basename($local_file);
	}

	protected function ftp_mkdir($dir) {
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

	protected function clear_basedir($file) {
		if( ($uploads = wp_upload_dir()) && false === $uploads['error'] ) {
			if( 0 === strpos($file, $uploads['basedir']) ) {
				$file = str_replace($uploads['basedir'], '', $file);
				$file = ltrim($file, '/');
			}
		}
		return $file;
	}

	protected function do_ftp_upload() {
		if( count($this->add_list) > 0 ) {
			$up_time = current_time('timestamp');
			foreach( $this->add_list as $file ) {
				if( $this->do_upload_file($file['ftp_file'], $file['local_file']) ) {
					if( $file['id'] != 0 ) {
						$up_dir = dirname($file['ftp_file']);
						if( $this->options['ftp_dir'] != '/' ) {
							$up_dir = str_replace($this->options['ftp_dir'], '', $up_dir);
						}
						$up_dir .= '/';
						$metadate = array(
							'up_time' => $up_time,
							'up_dir' => $up_dir
						);
						add_post_meta($file['id'], 'file_to_ftp', $metadate, true);
						if( $this->options['auto_delete_local'] == 1 && $this->options['save_original_file'] == 0 ) {
							file_put_contents($file['local_file'], '');
						}
					} else {
						if( $this->options['auto_delete_local'] == 1 ) {
							@unlink($file['local_file']);
						}
					}
				}
			}
		}
	}

	protected function do_upload_file($ftp_file, $local_file) {
		if( $this->options['ftp_uplode_ok'] && $this->open_ftp() ) {
			return @ftp_put($this->ftpc, $ftp_file, $local_file, FTP_BINARY);
		}
		return false;
	}

	protected function open_ftp() {
		if( $this->ftpc ) {
			return true;
		}
		if( is_callable('set_time_limit') ) {
			set_time_limit(60);
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
}

$u2ftp = new Upload_to_FTP();
?>