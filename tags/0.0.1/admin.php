<?php
add_filter('plugin_action_links', 'upload_to_ftp_links', 10, 2);
function upload_to_ftp_links($links, $file)
{
	if( $file == plugin_basename(dirname(__FILE__) . '/upload-to-ftp.php' ) )
	{
		$links[] = '<a href="options-general.php?page=upload-to-ftp">' . __('Settings', 'upload-to-ftp') . '</a>';
	}
	return $links;
}

add_action('admin_menu', 'upload_to_ftp_admin');
function upload_to_ftp_admin()
{
	add_submenu_page('options-general.php', 'Upload to FTP', __('Upload to FTP', 'upload-to-ftp'), 'manage_options', 'upload-to-ftp', 'upload_to_ftp_set');
}

function upload_to_ftp_set(){
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

if( !empty($_POST['Update_FTP']) ) {
	$ftp_test_ok = false;
	$ftp_host = trim($_POST['u2ftp_ftp_host']);
	$ftp_username = trim($_POST['u2ftp_ftp_username']);
	$ftp_password = trim($_POST['u2ftp_ftp_password']);
	$ftp_port = intval($_POST['u2ftp_ftp_port']);
	$ftp_dir = trim($_POST['u2ftp_ftp_dir']);

	$ftpc = @ftp_connect($ftp_host, $ftp_port, 30);
	if( !$ftpc ) {
		$error = '<span style="color:rad">' . __('FTP connect error', 'upload-to-ftp') . '</span>';
	} else {
		$u2ftp_options['ftp_host'] = $ftp_host;
		$u2ftp_options['ftp_port'] = $ftp_port;
		if( @!ftp_login($ftpc , $ftp_username, $ftp_password) ) {
			$error = '<span style="color:rad">' . __('FTP login error with username', 'upload-to-ftp') . ' <strong>' . $ftp_username . '</strong></span>';
		} else {
			$u2ftp_options['ftp_username'] = $ftp_username;
			$u2ftp_options['ftp_password'] = $ftp_password;
			ftp_pasv($ftpc, true);
			if( @!ftp_chdir($ftpc, $ftp_dir) ) {
				$error = '<span style="color:rad">' . __('FTP open directory failure', 'upload-to-ftp') . ' <strong>' . $ftp_dir . '</strong></span>';
			} else {
				$u2ftp_options['ftp_dir'] = $ftp_dir;
				if( @!ftp_put($ftpc, $ftp_dir . '/test-file.txt', dirname(__FILE__) . '/test-file.txt', FTP_BINARY) ) {
					$error = '<span style="color:rad">' . __('FTP is not writable', 'upload-to-ftp') . ' <strong>' . $ftp_dir . '</strong></span>';
				} else {
					ftp_delete($ftpc, $ftp_dir . '/test-file.txt') ;
					$ftp_test_ok = true;
				}
			}
		}
		ftp_close($ftpc);  
	}
	$u2ftp_options['html_link_url'] = trim($_POST['u2ftp_html_link_url']);
	if( update_option('U2FTP_options', $u2ftp_options) ) {
		$text = '<span style="color:green">' . __('Updated FTP Options Success', 'upload-to-ftp') . '</span>';
	}
	$u2ftp_options = get_option('U2FTP_options', array());
}

if( !empty($_POST['Update']) ) {
	$u2ftp_options['rename_file'] = (trim($_POST['u2ftp_rename_file']) == 1) ? 1 : 0;
	if( update_option('U2FTP_options', $u2ftp_options) ) {
		$text = '<span style="color:green">' . __('Updated Basic Options Success', 'upload-to-ftp') . '</span>';
	}
	$u2ftp_options = get_option('U2FTP_options', array());
}
?>
<form method="post" action="">
	<?php if(!empty($text)) { echo '<div id="message" class="updated"><p>'.$text.'</p></div>'; } ?>
	<?php if(!empty($error)) { echo '<div id="message" class="error"><p>'.$error.'</p></div>'; } ?>
	<table class="widefat">
		<thead><tr>
			<th colspan="2"><?php _e('FTP Options', 'upload-to-ftp'); ?></th>
		</tr></thead>
		<tr>
			<td valign="top" width="30%"><strong><?php _e('FTP Host:', 'upload-to-ftp'); ?></strong></td>
			<td valign="top">
				ftp://<input type="text" id="u2ftp_ftp_host" name="u2ftp_ftp_host" size="30" value="<?php echo($u2ftp_options['ftp_host']); ?>" />
			</td>
		</tr>
		<tr>
			<td valign="top" width="30%"><strong><?php _e('FTP Username:', 'upload-to-ftp'); ?></strong></td>
			<td valign="top">
				<input type="text" id="u2ftp_ftp_username" name="u2ftp_ftp_username" size="30" value="<?php echo($u2ftp_options['ftp_username']); ?>" />
			</td>
		</tr>
		<tr>
			<td valign="top" width="30%"><strong><?php _e('FTP Password:', 'upload-to-ftp'); ?></strong></td>
			<td valign="top">
				<input type="text" id="u2ftp_ftp_password" name="u2ftp_ftp_password" size="30" value="<?php echo($u2ftp_options['ftp_password']); ?>" />
			</td>
		</tr>
		<tr>
			<td valign="top" width="30%"><strong><?php _e('FTP Port:', 'upload-to-ftp'); ?></strong></td>
			<td valign="top">
				<input type="text" id="u2ftp_ftp_port" name="u2ftp_ftp_port" size="6" value="<?php echo($u2ftp_options['ftp_port']); ?>" />
			</td>
		</tr>
		<tr>
			<td valign="top" width="30%"><strong><?php _e('FTP Directory:', 'upload-to-ftp'); ?></strong></td>
			<td valign="top">
				<input type="text" id="u2ftp_ftp_dir" name="u2ftp_ftp_dir" size="50" value="<?php echo($u2ftp_options['ftp_dir']); ?>" />
			</td>
		</tr>
		<tr>
			<td valign="top" width="30%"><strong><?php _e('HTML link url:', 'upload-to-ftp'); ?></strong></td>
			<td valign="top">
				<input type="text" id="u2ftp_html_link_url" name="u2ftp_html_link_url" size="50" value="<?php echo($u2ftp_options['html_link_url']); ?>" />
			</td>
		</tr>
	</table>
	<p class="submit">
		<input type="submit" name="Update_FTP" class="button-primary" value="<?php _e('Save & Test Changes', 'upload-to-ftp'); ?>" />
	</p>
</form>
<form method="post" action="">
	<table class="widefat">
		<thead><tr>
			<th colspan="2"><?php _e('Basic Options', 'upload-to-ftp'); ?></th>
		</tr></thead>
		<tr>
			<td valign="top" width="30%"><strong><?php _e('Rename file:', 'upload-to-ftp'); ?></strong></td>
			<td valign="top">
				<select name="u2ftp_rename_file" size="1">
					<option value="0"<?php selected('0', $u2ftp_options['rename_file']); ?>><?php _e('disable', 'upload-to-ftp'); ?></option>
					<option value="1"<?php selected('1', $u2ftp_options['rename_file']); ?>><?php _e('enable', 'upload-to-ftp'); ?></option>
				</select>
				<br />
				<?php _e('Proposal enabled! Because the file name to avoid some of the resulting error can not be expected', 'upload-to-ftp'); ?>
			</td>
		</tr>
	</table>
	<p class="submit">
		<input type="submit" name="Update" class="button-primary" value="<?php _e('Save Changes', 'upload-to-ftp'); ?>" />
	</p>
</form>
</div>
<?php
}
?>