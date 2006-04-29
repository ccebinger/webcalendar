<?php
/*
 * $Id$
 *
 * Page Description:
 *  This page will handle adding blobs into the database.  It will
 *  present the form page on a GET and handle updating the database
 *  on a POST.
 *  This includes:
 *    Add comment to an event
 *    Add attachment to an event
 *
 * Input Parameters:
 *  For GET:
 *    id - event id (optional for some types)
 *    type - C=comment, A=attachment
 *  For POST:
 *    id - event id (optional for some types)
 *    type - C=comment, A=attachment
 *    description - (for type=C and A)
 *    comment - (for type=C)
 *    FileName - (for type=A)
 *
 * Comments:
 *  TODO: add email notification when attachment or comment is added
 */
include_once 'includes/init.php';

$id = getIntValue ( 'id' );
$type = getValue ( 'type' );
$user = getValue ( 'user' );
$error = '';

switch ( $type ) {
  case 'C':
    if ( empty ( $id ) )
      $error = 'No id specified';
    $title = translate ( "Add Comment" );
    break;
  case 'A':
    if ( empty ( $id ) )
      $error = 'No id specified';
    $title = translate ( "Add Attachment" );
    $upload = ini_get ( "file_uploads" );
    $upload_enabled = ! empty ( $upload ) &&
      preg_match ( "/(On|1|true|yes)/i", $upload );
    if ( ! $upload_enabled ) {
      $error = "You must enable file_uploads in php.ini";
    }
    break;
  default:
    $error = 'Invalid type';
    break;
}

$can_add = false;
if ( $is_admin )
  $can_add = true;

// Get event details if this is associated with an event
if ( empty ( $error ) && ! empty ( $id ) ) {
  // is this user a participant or the creator of the event?
  $sql = "SELECT webcal_entry.cal_id FROM webcal_entry, " .
    "webcal_entry_user WHERE webcal_entry.cal_id = " .
    "webcal_entry_user.cal_id AND webcal_entry.cal_id = ? " .
    "AND (webcal_entry.cal_create_by = ? " .
    "OR webcal_entry_user.cal_login = ?)";
  $res = dbi_execute ( $sql, array( $id, $login, $login ) );
  if ( $res ) {
    $row = dbi_fetch_row ( $res );
    if ( $row && $row[0] > 0 ) {
      $is_my_event = true; // user is participant
    }
    dbi_free_result ( $res );
  }
}

if ( $type == 'A' ) {
  if ( empty ( $ALLOW_ATTACH ) || $ALLOW_ATTACH != 'Y' )
    $error = translate('You are not authorized');
  else if ( empty ( $error ) && $ALLOW_ATTACH_PART == 'Y' && $is_my_event )
    $can_add = true;
  else if ( $ALLOW_ATTACH_ANY == 'Y' )
    $can_add = true;
} else if ( $type == 'C' ) {
  if ( empty ( $ALLOW_COMMENTS ) || $ALLOW_COMMENTS != 'Y' )
    $error = translate('You are not authorized');
  else if ( empty ( $error ) && $ALLOW_COMMENTS_PART == 'Y' && $is_my_event )
    $can_add = true;
  else if ( $ALLOW_COMMENTS_ANY == 'Y' )
    $can_add = true;
}
//check UAC
if ( access_is_enabled () ) {
  $can_add = $can_add || access_user_calendar ( 'edit', $user );
}

if ( ! $can_add )
  $error = translate ( "You are not authorized" );

if ( ! empty ( $error ) ) {
  print_header ();
  echo "<h2>" . translate("Error") . "</h2>" . $error;
  print_trailer ();
  echo "</body></html>\n";
  exit;
}

// Handle possible POST first
if ( empty ( $REQUEST_METHOD ) )
  $REQUEST_METHOD = $_SERVER['REQUEST_METHOD'];
if ( $REQUEST_METHOD == 'POST' ) {

  // get next id first
  $res = dbi_execute ( "SELECT MAX(cal_blob_id) FROM webcal_blob" );
  if ( ! $res ) {
    die_miserable_death ( translate("Database error") . ": " .
      dbi_error () );
  }
  if ( $row = dbi_fetch_row ( $res ) )
    $nextid = $row[0] + 1;
  else
    $nextid = 1;
  dbi_free_result ( $res );

  if ( $type == 'C' ) {
    // Comment
    $description = getValue ( 'description' );
    $comment = getValue ( 'comment' );
    $sql = "INSERT INTO webcal_blob ( cal_blob_id, " .
      "cal_id, cal_login, cal_name, cal_description, " .
      "cal_size, cal_mime_type, cal_type, cal_mod_date, " .
      "cal_mod_time, cal_blob ) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )";
    if ( ! dbi_execute ( $sql, array( $nextid, $id, $login, NULL, $description, 0, 'text/plain', 'C', date("Ymd"), date("His"), NULL ) ) ) {
      $error = translate ( "Database error" ) . ": " . dbi_error ();
    } else {
      if ( ! dbi_update_blob ( 'webcal_blob', 'cal_blob',
        "cal_blob_id = $nextid", $comment ) ) {
        $error = translate ( "Database error" ) . ": " . dbi_error ();
      } else {
        // success!  redirect to view event page
        activity_log ( $id, $login, $login, LOG_COMMENT, '' );
        do_redirect ( "view_entry.php?id=$id" );
      }
    }
  } else if ( $type == 'A' ) {
    // Attachment
    $description = getValue ( 'description' );
    if ( ! empty ( $_FILES['FileName'] ) ) {
      $file = $_FILES['FileName'];
    } else if ( ! empty ( $HTTP_POST_FILES['FileName'] ) ) {
      $file = $HTTP_POST_FILES['FileName'];
    }
    if ( empty ( $file['file'] ) )
      $error = "File Upload error!<br/>";

    //print_r ( $file ); exit;
    $mimetype = $file['type'];
    $filesize = $file['size'];
    $filename = $file['name'];
    $tmpfile = $file['tmp_name'];
    if ( empty ( $description ) )
      $description = $filename;

    $data = '';
    $fd = @fopen ( $tmpfile, 'r' );
    if ( ! $fd )
      die_miserable_death ( "Error reading temp file: $tmpfile" );
    if ( ! empty ( $error ) ) {
      while ( ! feof ( $fd ) ) {
        $data .= fgets ( $fd, 4096 );
      }
    }
    fclose ( $fd );

    $comment = getValue ( 'description' );
    $sql = "INSERT INTO webcal_blob ( cal_blob_id, " .
      "cal_id, cal_login, cal_name, cal_description, " .
      "cal_size, cal_mime_type, cal_type, cal_mod_date, " .
      "cal_mod_time, cal_blob ) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )";
    if ( ! dbi_execute ( $sql, array( $nextid, $id, $login, $filename, $description, $filesize, $mimetype, 'A', date("Ymd"), date("His"), NULL ) ) ) {
      $error = translate ( "Database error" ) . ": " . dbi_error ();
    } else {
      if ( ! dbi_update_blob ( 'webcal_blob', 'cal_blob',
        "cal_blob_id = $nextid", $data ) ) {
        $error = translate ( "Database error" ) . ": " . dbi_error ();
      } else {
        // success!  redirect to view event page
        activity_log ( $id, $login, $login, LOG_ATTACHMENT, $filename );
        do_redirect ( "view_entry.php?id=$id" );
      }
    }
  } else {
    die_miserable_death ( "Unsupported type" ); // programmer error
  }

  if ( ! empty ( $error ) ) {
    print_header ();
    echo "<h2>" . translate("Error") . "</h2>" . $error;
    print_trailer ();
    echo "</body></html>\n";
    exit;
  }
}


print_header ();
?>
<h2><?php echo $title;?></h2>

<?php if ( $type == 'C' ) {
  // Comment
?>
<form action="docadd.php" method="post" name="docform">
<input type="hidden" name="id" value="<?php echo $id?>" />
<input type="hidden" name="type" value="C" />

<table style="border-width:0px;">

<tr><td style="vertical-align:top;"><label for="description">
  <?php etranslate("Subject")?>:</label></td>
  <td><input type="text" name="description" size="50" maxlength="127" /></td></tr>
<!-- TODO: htmlarea or fckeditor support -->
<tr><td style="vertical-align:top;"><label for="comment">
  <?php etranslate("Comment")?>:</label></td>
  <td><textarea name="comment" rows="15" cols="60" wrap="auto"></textarea></td></tr>
<tr><td colspan="2">
<input type="submit" value="<?php etranslate("Add Comment")?>" /></td></tr>
</table>
</form>




<?php } else if ( $type == 'A' ) {
  // Attachment
?>
<form action="docadd.php" method="post" name="docform" enctype="multipart/form-data">
<input type="hidden" name="id" value="<?php echo $id?>" />
<input type="hidden" name="type" value="A" />
<table style="border-width:0px;">
<tr class="browse"><td>
 <label for="fileupload"><?php etranslate("Upload file");?>:</label></td><td>
 <input type="file" name="FileName" id="fileupload" size="45" maxlength="50" />
<tr><td style="vertical-align:top;"><label for="description">
  <?php etranslate("Description")?>:</label></td>
  <td><input type="text" name="description" size="50" maxlength="127" /></td></tr>

<tr><td colspan="2">
<input type="submit" value="<?php etranslate("Add Attachment")?>" /></td></tr>

</table>
</form>

<?php } ?>


<?php print_trailer (); ?>
</body>
</html>