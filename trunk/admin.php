<?php
add_filter('plugin_action_links', 'upload_to_ftp_links', 10, 2);
function upload_to_ftp_links($links, $file) {
	if( $file == plugin_basename(dirname(__FILE__) . '/upload-to-ftp.php' ) ) {
		$links[] = '<a href="options-general.php?page=upload-to-ftp">' . __('Settings', 'upload-to-ftp') . '</a>';
	}
	return $links;
}

add_filter('manage_media_columns', 'upload_to_ftp_add_columns');
function upload_to_ftp_add_columns($attr) {
	$attr['toftp'] = __('to FTP', 'upload-to-ftp');
	return $attr;
}

add_action('manage_media_custom_column', 'upload_to_ftp_display_column', 10, 2);
function upload_to_ftp_display_column($name, $id) {
	global $post;
	if( $name == 'toftp' ) {
		$metadate = get_post_meta($id, 'file_to_ftp', true);
		if( isset($metadate['up_time']) ) {
			if( $metadate['up_time'] == 1 ) {
				$metadate['up_time'] = strtotime($post->post_date);
				update_post_meta($id, 'file_to_ftp', $metadate);
			}
			if( $metadate['up_time'] ) {
				echo(date('Y/m/d G:i', $metadate['up_time']));
			}
		} else {
			_e('un-upload', 'upload-to-ftp');
		}
	}
}

add_action('admin_menu', 'upload_to_ftp_admin');
function upload_to_ftp_admin() {
	add_submenu_page('options-general.php', 'Upload to FTP', __('Upload to FTP', 'upload-to-ftp'), 'manage_options', 'upload-to-ftp', 'upload_to_ftp_set');
}

function upload_to_ftp_set() {
?>
<div class="wrap">
<?php screen_icon(); ?>
<h2 style="padding-bottom:1em;"><?php _e('Upload to FTP Options', 'upload-to-ftp'); ?></h2>
<?php
$u2ftp_options = get_option('U2FTP_options', array());
if( !defined('FTP_BINARY') ) {
	?>
	<div id="message" class="error"><p>
		<span style="color:rad"><?php _e('Your server does not support FTP-related functions', 'upload-to-ftp'); ?></span>
	</p></div>
</div>
	<?php
	return true;
}

if( !empty($_POST['U2FTP_Update_ftpsetting']) ) {
	$ftp_host = trim($_POST['u2ftp_ftp_host']);
	$ftp_port = intval($_POST['u2ftp_ftp_port']);
	$ftp_timeout = intval($_POST['u2ftp_ftp_timeout']);
	$ftp_username = trim($_POST['u2ftp_ftp_username']);
	$ftp_password = trim($_POST['u2ftp_ftp_password']);
	if( empty($ftp_password) ) {
		$ftp_password = $u2ftp_options['ftp_password'];
	}
	$ftp_mode = (intval($_POST['u2ftp_ftp_mode']) == 1) ? 1 : 0;
	$ftp_dir = trim($_POST['u2ftp_ftp_dir']);
	$html_link_url = trim($_POST['u2ftp_html_link_url']);
	$ftp_uplode_ok = false;
	$ftp_delete_ok = false;
	$html_file_line_ok = false;
	
	preg_match('/ftp[s]?:\/\//i', $ftp_host, $temp);
	if( isset($temp[0]) ) {
		$ftp_host = substr($ftp_host, strlen($temp[0]));
	}
	if( substr($ftp_host, -1) == '/' ) {
		$ftp_host = substr($ftp_host, 0, -1);
	}
	if( $ftp_port <= 0 || $ftp_port > 65535 ) {
		$ftp_port = 21;
	}
	if( $ftp_timeout <= 0 || $ftp_timeout > 61 ) {
		$ftp_timeout = 15;
	}
	if( substr($ftp_dir, 0, 1) != '/' ) {
		$ftp_dir = '/' . $ftp_dir;
	}
	if( substr($ftp_dir, -1) != '/' ) {
		$ftp_dir .= '/';
	}
	preg_match('/http[s]?:\/\//i', $html_link_url , $temp);
	if( !isset($temp[0]) ) {
		$html_link_url = 'http://' . $html_link_url;
	}
	if( substr($html_link_url , -1) != '/' ) {
		$html_link_url .= '/';
	}

	$ftpc = @ftp_connect($ftp_host, $ftp_port, $ftp_timeout);
	if( !$ftpc ) {
		$error = '<span style="color:rad">' . __('FTP connect error', 'upload-to-ftp') . ' ' . $ftp_host . ':' . $ftp_port . '</span>';
		$ftp_host = '';
		$ftp_port = 21;
		$ftp_timeout = 15;
	} else {
		if( @!ftp_login($ftpc , $ftp_username, $ftp_password) ) {
			$error = '<span style="color:rad">' . __('FTP login error with username', 'upload-to-ftp') . ' <strong>' . $ftp_username . '</strong></span>';
			$ftp_username = '';
			$ftp_password = '';
		} else {
			ftp_pasv($ftpc, (bool) $ftp_mode);
			if( @!ftp_chdir($ftpc, $ftp_dir) ) {
				$error = '<span style="color:rad">' . __('FTP open directory failure', 'upload-to-ftp') . ' <strong>' . $ftp_dir . '</strong></span>';
			} else {
				if( @!ftp_put($ftpc, $ftp_dir . 'test-file.txt', dirname(__FILE__) . '/test-file.txt', FTP_BINARY) ) {
					$error = '<span style="color:rad">' . __('FTP is not writable', 'upload-to-ftp') . ' <strong>' . $ftp_dir . '</strong></span>';
					$error .= '<br />' . $ftp_dir . 'test-file.txt => ' . dirname(__FILE__) . '/test-file.txt';
				} else {
					$ftp_uplode_ok = true;
					$body = '';
					if( ini_get('allow_url_fopen') ) {
						$body = @file_get_contents($html_link_url . 'test-file.txt');
					} elseif ( function_exists('curl_init') ) {
						$ch = curl_init();
						curl_setopt($ch, CURLOPT_URL, $html_link_url . 'test-file.txt');
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
						curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
						curl_setopt($ch, CURLOPT_COOKIE, '');
						curl_setopt($ch, CURLOPT_TIMEOUT, 15);
						$body = @curl_exec($ch);
						$info = @curl_getinfo($ch);
						@curl_close($ch);
					}
					if( $body != 'This is a test file for "Upload to FTP"' ) {
						$error = '<span style="color:rad">' . __('HTML link url don\'t match FTP dir', 'upload-to-ftp') . '</span>';
						$error .= '<br />' . $html_link_url . 'test-file.txt';
					} else {
						$html_file_line_ok = true;
					}
					if( @ftp_delete($ftpc, $ftp_dir . 'test-file.txt') ) {
						$ftp_delete_ok = true;
					}
				}
			}
		}
		ftp_close($ftpc);
	}
	$u2ftp_options['ftp_host'] = $ftp_host;
	$u2ftp_options['ftp_port'] = $ftp_port;
	$u2ftp_options['ftp_timeout'] = $ftp_timeout;
	$u2ftp_options['ftp_username'] = $ftp_username;
	$u2ftp_options['ftp_password'] = $ftp_password;
	$u2ftp_options['ftp_mode'] = $ftp_mode;
	$u2ftp_options['ftp_dir'] = $ftp_dir;
	$u2ftp_options['ftp_uplode_ok'] = $ftp_uplode_ok;
	$u2ftp_options['html_link_url'] = $html_link_url;
	$u2ftp_options['html_file_line_ok'] = $html_file_line_ok;
	$u2ftp_options['ftp_delete_ok'] = $ftp_delete_ok;
	if( update_option('U2FTP_options', $u2ftp_options) ) {
		$text = '<span style="color:green">' . __('Updated FTP Options Success', 'upload-to-ftp') . '</span>';
	}
	$u2ftp_options = get_option('U2FTP_options', array());
}

if( !empty($_POST['U2FTP_Update_setting']) ) {
	$u2ftp_options['rename_file'] = intval($_POST['u2ftp_rename_file']) ? 1 : 0;
	$u2ftp_options['auto_delete_local'] = intval($_POST['u2ftp_auto_delete_local']) ? 1 : 0;
	if( $u2ftp_options['auto_delete_local'] ) {
		$u2ftp_options['save_original_file'] = intval($_POST['u2ftp_save_original_file']) ? 1 : 0;
	} else {
		$u2ftp_options['save_original_file'] = 1;
	}
	if( update_option('U2FTP_options', $u2ftp_options) ) {
		$text = '<span style="color:green">' . __('Updated Basic Options Success', 'upload-to-ftp') . '</span>';
	}
	$u2ftp_options = get_option('U2FTP_options', array());
}
?>
<?php if(!empty($text)) { echo '<div id="message" class="updated"><p>'.$text.'</p></div>'; } ?>
<?php if(!empty($error)) { echo '<div id="message" class="error"><p>'.$error.'</p></div>'; } ?>
<table width="100%"><tr>
<td><form method="post" action="">
	<table class="widefat">
		<thead><tr>
			<th colspan="2"><strong><?php _e('FTP Options', 'upload-to-ftp'); ?></strong></th>
		</tr></thead>
		<tr>
			<td rowspan="3"><strong><?php _e('FTP Status:', 'upload-to-ftp'); ?></strong></td>
			<td><?php _e('Upload Status:', 'upload-to-ftp'); ?> <strong><?php $u2ftp_options['ftp_uplode_ok'] ? _e('Can upload', 'upload-to-ftp') : _e('Can not upload', 'upload-to-ftp'); ?></strong></td>
		</tr>
		<tr>
			<td><?php _e('Delete Status:', 'upload-to-ftp'); ?><strong><?php $u2ftp_options['ftp_delete_ok'] ? _e('Can delete', 'upload-to-ftp') : _e('Can not delete', 'upload-to-ftp'); ?></strong></td>
		</tr>
		<tr>
			<td><?php _e('File Link Status:', 'upload-to-ftp'); ?><strong><?php $u2ftp_options['html_file_line_ok'] ? _e('HTML File Link is OK', 'upload-to-ftp') : _e('HTML File Link is Error', 'upload-to-ftp'); ?></strong></td>
		</tr>
		<tr class="alternate">
			<td><strong><?php _e('FTP Host:', 'upload-to-ftp'); ?></strong></td>
			<td>ftp://<input type="text" id="u2ftp_ftp_host" name="u2ftp_ftp_host" size="30" value="<?php echo($u2ftp_options['ftp_host']); ?>" /></td>
		</tr>
		<tr>
			<td><strong><?php _e('FTP Port:', 'upload-to-ftp'); ?></strong></td>
			<td><input type="text" id="u2ftp_ftp_port" name="u2ftp_ftp_port" size="6" value="<?php echo($u2ftp_options['ftp_port']); ?>" /></td>
		</tr>
		<tr class="alternate">
			<td><strong><?php _e('FTP Timeout:', 'upload-to-ftp'); ?></strong></td>
			<td><input type="text" id="u2ftp_ftp_timeout" name="u2ftp_ftp_timeout" size="4" value="<?php echo($u2ftp_options['ftp_timeout']); ?>" /></td>
		</tr>
		<tr>
			<td><strong><?php _e('FTP Username:', 'upload-to-ftp'); ?></strong></td>
			<td><input type="text" id="u2ftp_ftp_username" name="u2ftp_ftp_username" size="30" value="<?php echo($u2ftp_options['ftp_username']); ?>" /></td>
		</tr>
		<tr class="alternate">
			<td><strong><?php _e('FTP Password:', 'upload-to-ftp'); ?></strong></td>
			<td><input type="text" id="u2ftp_ftp_password" name="u2ftp_ftp_password" size="30" /><br /><?php if( !empty($u2ftp_options['ftp_password']) ) { _e('Only when you want to change your password, enter it.', 'upload-to-ftp'); } ?></td>
		</tr>
		<tr>
			<td><strong><?php _e('FTP Mode:', 'upload-to-ftp'); ?></strong></td>
			<td><input type="radio" id="u2ftp_ftp_mode" name="u2ftp_ftp_mode" value="1" <?php checked('1', $u2ftp_options['ftp_mode']); ?> /> <?php _e('Passive', 'upload-to-ftp'); ?> <input type="radio" id="u2ftp_ftp_mode" name="u2ftp_ftp_mode" value="0" <?php checked('0', $u2ftp_options['ftp_mode']); ?> /> <?php _e('Active', 'upload-to-ftp'); ?></td>
		</tr>
		<tr class="alternate">
			<td><strong><?php _e('FTP Directory:', 'upload-to-ftp'); ?></strong></td>
			<td><input type="text" id="u2ftp_ftp_dir" name="u2ftp_ftp_dir" size="60" value="<?php echo($u2ftp_options['ftp_dir']); ?>" /></td>
		</tr>
		<tr>
			<td><strong><?php _e('HTML link url:', 'upload-to-ftp'); ?></strong></td>
			<td><input type="text" id="u2ftp_html_link_url" name="u2ftp_html_link_url" size="60" value="<?php echo($u2ftp_options['html_link_url']); ?>" /></td>
		</tr>
		<tfoot><tr>
			<td align="center"><input type="submit" name="U2FTP_Update_ftpsetting" class="button-primary" value="<?php _e('Save & Test Changes', 'upload-to-ftp'); ?>" /></td>
			<td>&nbsp;</td>
		</tr></tfoot>
	</table>
</form>
<p>&nbsp;</p>
<form method="post" action="">
	<table class="widefat">
		<thead><tr>
			<th colspan="2"><strong><?php _e('Basic Options', 'upload-to-ftp'); ?></strong></th>
		</tr></thead>
		<tr>
			<td><strong><?php _e('Rename file:', 'upload-to-ftp'); ?></strong></td>
			<td><select name="u2ftp_rename_file" size="1"><option value="0"<?php selected('0', $u2ftp_options['rename_file']); ?>><?php _e('disable', 'upload-to-ftp'); ?></option><option value="1"<?php selected('1', $u2ftp_options['rename_file']); ?>><?php _e('enable', 'upload-to-ftp'); ?></option></select>
			<br /><em><?php _e('Proposal enabled! Because the file name to avoid some of the resulting error can not be expected', 'upload-to-ftp'); ?></em></td>
		</tr>
		<tr class="alternate">
			<td rowspan="2"><strong><?php _e('Auto delete local file:', 'upload-to-ftp'); ?></strong></td>
			<td><input type="radio" id="u2ftp_auto_delete_local" name="u2ftp_auto_delete_local" value="0" <?php checked('0', $u2ftp_options['auto_delete_local']); ?> /> &nbsp; <?php _e('disable', 'upload-to-ftp'); ?><input type="radio" id="u2ftp_auto_delete_local" name="u2ftp_auto_delete_local" value="1" <?php checked('1', $u2ftp_options['auto_delete_local']); ?> /> <?php _e('enable', 'upload-to-ftp'); ?>
			<br /><em><?php _e('Only enable the when you local storage space have limited.', 'upload-to-ftp'); ?></em></td>
		</tr>
		<tr>
			<td>
				<input type="checkbox" name="u2ftp_save_original_file" id="u2ftp_save_original_file" value="1" <?php checked('1', $u2ftp_options['save_original_file']); ?> /> <?php _e('Save original file', 'upload-to-ftp'); ?>
				<br /><em><?php _e('If don\'t save original file, still have an 0 Byte file to keep the file name unique.', 'upload-to-ftp'); ?></em>
			</td>
		</tr>
		<tfoot><tr>
			<td align="center"><input type="submit" name="U2FTP_Update_setting" class="button-primary" value="<?php _e('Save Changes', 'upload-to-ftp'); ?>" /></td>
			<td>&nbsp;</td>
		</tr></tfoot>
	</table>
</form></td>
<td width="20">&nbsp;</td>
<td width="150" valign="top">
	<table class="widefat">
		<thead><tr>
			<th><strong><?php _e('About Plugin', 'upload-to-ftp'); ?></strong></th>
		</tr></thead>
		<tr>
			<td>
				<p><a class="ure_rsb_link" target="_blank" href="http://fantasyworld.idv.tw/"><?php _e('Author\'s website', 'upload-to-ftp'); ?></a></p>
				<p><a class="ure_rsb_link" target="_blank" href="http://wwpteach.com/upload-to-ftp"><?php _e('Plugin webpage', 'upload-to-ftp'); ?></a></p>
				<p><a class="ure_rsb_link" target="_blank" href="http://wwpteach.com/upload-to-ftp/history"><?php _e('Version history', 'upload-to-ftp'); ?></a></p>
				<p><?php _e('Version: ', 'upload-to-ftp'); echo(get_option('U2FTP_version'));?></p>
			</td>
		</tr>
	</table>
	<p>&nbsp;</p>
	<table class="widefat">
		<thead><tr>
			<th><strong><?php _e('Donate me', 'upload-to-ftp'); ?></strong></th>
		</tr></thead>
		<tr>
			<td>
				<small><?php _e('You can buy me some special coffees if you like this plugin, thank you!', 'upload-to-ftp'); ?></small>
				<?php if( defined('WPLANG') && WPLANG == 'zh_TW' ) { ?>
<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
	<input type="hidden" name="cmd" value="_s-xclick">
	<input type="hidden" name="hosted_button_id" value="RLG8RJZGN7UEJ">
	<input type="image" src="https://www.paypal.com/zh_HK/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal — The safer, easier way to pay online.">
	<img alt="" border="0" src="https://www.paypalobjects.com/zh_TW/i/scr/pixel.gif" width="1" height="1">
</form>
				<?php } else { ?>
<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
	<input type="hidden" name="cmd" value="_s-xclick">
	<input type="hidden" name="hosted_button_id" value="FKDTJ2FJXZDC2">
	<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal — The safer, easier way to pay online.">
	<img alt="" border="0" src="https://www.paypalobjects.com/zh_TW/i/scr/pixel.gif" width="1" height="1">
</form>
				<?php } ?>
			</td>
		</tr>
	</table>
</td></tr>
</table>
</div>
  <?php
}
?>
