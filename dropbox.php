<?php
/**
 * Plugin Name: ASPL Dropbox File Upload
 * Description: Another Best Plugin for Integrate Dropbox With Your Upload Form.
 * Version: 1.1.0
 * Author: Acespritech
 * Author URI: https://acespritech.com
 * Text Domain: acespritech
 * Domain Path: /i18n/languages/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package acespritech
 */



add_action( 'admin_menu', 'aspl_dfu_plugin_menu' );	
function aspl_dfu_plugin_menu() {
	add_options_page( 'Dropbox', 'Dropbox', 'manage_options', 'my-unique-identifier', 'aspl_dfu_plugin_options' );
}
function aspl_dfu_plugin_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
    @$acess_token = get_option('dfu_access_token');

	?>
	<div class="wrap">
        <h2></h2>
        <h2 class="title"> Dropbox File Upload</h2>
		<form method="post">
			<table>
				<tr>
					<td>Access Token</td>
					<td><input type="text" name="access_token" value='<?php _e($acess_token); ?>'>
				</tr>
                <tr>
                    <td colspan="2">
                        <label>* click <a href="https://www.dropbox.com/developers/apps/create">here</a> to Create app in your dropbox account</label>
                    </td>
                </tr>
				<tr>
					<td><br><input type="submit" class="button button-primary" name="dfu_submit" value="Save Changes"></td>
				</tr>
			</table>
		</form>
	</div>
	<?php
	if(isset($_POST['dfu_submit'])){

		$access_token = sanitize_text_field($_POST['access_token']);
		update_option('dfu_access_token', $access_token);

	}
	// _e('<p>Here is where the form would go if I actually had options.</p>');
	// _e('</div>');
}

add_action('wp_enqueue_scripts', 'aspl_dfu_script');
function aspl_dfu_script(){  
 	// wp_enqueue_script('aspl_dfu_script', 'https://unpkg.com/dropbox@5.2.1/dist/Dropbox-sdk.min.js', array('jquery')); //   dropbox-sdk.min.js	
    wp_enqueue_style('aspl_dfu_style',plugins_url('/custom_css.css', __FILE__));
    wp_enqueue_script('aspl_dfu_script',plugins_url('/dropbox-sdk.min.js', __FILE__) , array('jquery'));
}

function upload_form(){
    $result = '';
    return $result;
}
function aspl_dfu_footag_func( $attr ) {
    $result = '';
    $result .= '<div class="aspl-dropbox-form-upload-section">';
    $result .='<form onSubmit="event.preventDefault(); return uploadFile()">';
    $result .='
        <div class="row"><div class="col medium-6 small-12 large-6 aspl-file-field-outer"><div class="file-upload-wrapper"><label for="file-upload">File Upload</label><input type="file" id="file-upload" /></div></div><div class="col medium-6 small-12 large-6">
        <button type="submit" style="color:#fff;">Submit</button></div></div>
    </form>';
    $result .= '</div>';
    $result .='<div id="results" class="result"></div>';
    return $result;
}
add_shortcode( 'aspl_dropbox', 'aspl_dfu_footag_func' );

add_action('wp_footer', 'aspl_dfu_footer_script');
function aspl_dfu_footer_script(){
    $loader_image = plugins_url('/image/file-upload.gif', __FILE__); 
    // _e($loader_image );  
    ?>
    <script type='text/javascript'>
    function uploadFile() {
        
        var elem = document.createElement("img");
        elem.setAttribute("src", "<?php _e($loader_image); ?>");
        elem.setAttribute("height", "100");
        elem.setAttribute("width", "100");
        elem.setAttribute("alt", "Flower");

        var results = document.getElementById('results');
        results.appendChild(elem);
        const UPLOAD_FILE_SIZE_LIMIT = 150 * 1024 * 1024;
        
        var ACCESS_TOKEN = '<?php _e(get_option('dfu_access_token')); ?>';
        // return;
        var dbx = new Dropbox.Dropbox({accessToken: ACCESS_TOKEN });
        var fileInput = document.getElementById('file-upload');
        var file = fileInput.files[0];
      
        if (file.size < UPLOAD_FILE_SIZE_LIMIT) { // File is smaller than 150 Mb - use filesUpload API
            dbx.filesUpload({path: '/' + file.name, contents: file})
                .then(function(response) {
                    var results = document.getElementById('results');
                    results.innerHTML = '';
                    results.appendChild(document.createTextNode('File uploaded!'));
                    console.log(response);
                })
                .catch(function(error) {
                    console.error(error);
                });
        } else { 
            // File is bigger than 150 Mb - use filesUploadSession* API
            const maxBlob = 8 * 1000 * 1000; // 8Mb - Dropbox JavaScript API suggested max file / chunk size
            var workItems = [];     
            var offset = 0;

            while (offset < file.size) {
                var chunkSize = Math.min(maxBlob, file.size - offset);
                // console.log(chunkSize);
                workItems.push(file.slice(offset, offset + chunkSize));
                offset += chunkSize;
            } 
          
            const task = workItems.reduce((acc, blob, idx, items) => {
            if (idx == 0) {
                // Starting multipart upload of file
                return acc.then(function() {
                    return dbx.filesUploadSessionStart({ close: false, contents: blob})
                        .then(response => response.session_id)
                });          
            } else if (idx < items.length-1) {  
                // Append part to the upload session
                return acc.then(function(sessionId) {
                 var cursor = { session_id: sessionId, offset: idx * maxBlob };
                 return dbx.filesUploadSessionAppendV2({ cursor: cursor, close: false, contents: blob }).then(() => sessionId); 
                });
            } else {
                // Last chunk of data, close session
                return acc.then(function(sessionId) {
                  var cursor = { session_id: sessionId, offset: file.size - blob.size };
                  var commit = { path: '/' + file.name, mode: 'add', autorename: true, mute: false };              
                  return dbx.filesUploadSessionFinish({ cursor: cursor, commit: commit, contents: blob });           
                });
            }          
        }, Promise.resolve());
        
        task.then(function(result) {
            var results = document.getElementById('results');
            results.appendChild(document.createTextNode('File uploaded!'));
        }).catch(function(error) {
            console.error(error);
        });
        
      }
      return false;
    }
  </script>
  <?php
}