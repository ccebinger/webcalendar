<?php
/*
 * $Id$
 *
 * Page Description:
 *	This page will present the user with forms for submitting
 *	a data file to import.
 *
 * Input Parameters:
 *	None
 *
 * Comments:
 *	Might be nice to allow user to set the category for all imported
 *	events.  So, a user could easily export events from the work
 *	calendar and import them into WebCalendar with a category
 *	"work".
 */
include_once 'includes/init.php';

$INC = array('js/export.php','js/visible.php');
print_header($INC);

// Generate the selection list for calendar user selection.
// Only ask for calendar user if user is an administrator.
// We may enhance this in the future to allow
// - selection of more than one user
// - non-admin users this functionality
function print_user_list () {
  global $single_user, $is_admin, $nonuser_enabled, $login,
    $is_nonuser_admin, $is_assistant;

  if ( $single_user == "N" && $is_admin ) {
    $userlist = get_my_users ();
    if ($nonuser_enabled == "Y" ) {
      $nonusers = get_nonuser_cals ();
      $userlist = ( ! empty ( $nonuser_at_top ) && $nonuser_at_top == "Y") ?
        array_merge($nonusers, $userlist) : array_merge($userlist, $nonusers);
    }
    $num_users = 0;
    $size = 0;
    $users = "";
    for ( $i = 0; $i < count ( $userlist ); $i++ ) {
      $l = $userlist[$i]['cal_login'];
      $size++;
      $users .= "<option value=\"" . $l . "\"";
      if ( ! empty ( $id ) && $id > 0 ) {
        if ( ! empty ( $participants[$l] ) )
          $users .= " selected=\"selected\"";
      } else {
        if ( $l == $login && ! $is_assistant  && ! $is_nonuser_admin )
          $users .= " selected=\"selected\"";
      }
      $users .= ">" . $userlist[$i]['cal_fullname'] . "</option>\n";
    }
  
    if ( $size > 50 )
      $size = 15;
    else if ( $size > 5 )
      $size = 5;
    print "<tr><td style=\"vertical-align:top;\"><label for=\"caluser\">" . translate("Calendar") . "</label></td>\n";
    print "<td><select name=\"calUser\" id=\"caluser\" size=\"$size\">$users\n";
    print "</select>\n";
    print "</td></tr>\n";
  }
}
?>

<h2>Import&nbsp;<img src="help.gif" alt="<?php etranslate("Help")?>" class="help" onclick="window.open ( 'help_import.php', 'cal_help', 'dependent,menubar,scrollbars,height=400,width=400');" /> / <?php etranslate("Export")?></h2>

<!-- TABS -->
<div id="tabs">
	<span class="tabfor" id="tab_import"><a href="#tabimport" onclick="return showTab('import')">Import</a></span>
	<span class="tabbak" id="tab_export"><a href="#tabexport" onclick="return showTab('export')">Export</a></span>
</div>

<!-- TABS BODY -->
<div id="tabscontent">
	<!-- DETAILS -->
	<a name="tabimport"></a>
	<div id="tabscontent_import">
	<form action="import_handler.php" method="post" name="importform" enctype="multipart/form-data">
<form action="export_handler.php" method="post" name="exportform">
<table style="border-width:0px;">
<tr><td>
	<label for="importtype"><?php etranslate("Import format")?>:</label></td><td>
		<select name="ImportType">
			<option value="PALMDESKTOP">Palm Desktop < v4.1.1</option>
			<option value="vcal">vCal</option>
			<option value="ICAL">iCal</option>
		</select>
</td></tr>
<tr id="palm"><td>
	<label><?php etranslate("Exclude private records")?>:</label></td><td>
	<label><input type="radio" name="exc_private" value="1" checked="checked" />&nbsp;<?php etranslate("Yes")?></label> 
	<label><input type="radio" name="exc_private" value="0" />&nbsp;<?php etranslate("No")?></label>
</td></tr>
<!-- /PALM -->

<tr id="ivcal"><td>
	<label><?php etranslate("Overwrite Prior Import")?>:</label></td<td>
	<label><input type="radio" name="overwrite" value="Y" checked="checked" />&nbsp;<?php etranslate("Yes");?></label> 
	<label><input type="radio" name="overwrite" value="N" />&nbsp;<?php etranslate("No");?></label>
</td></tr>
<!-- /IVCAL -->

<tr class="browse"><td>
	<label>Upload file:</label></td><td>
	<input type="file" name="FileName" size="45" maxlength="50" />
</td></tr>

<?php print_user_list(); ?>
</table>
<br /><input type="submit" value="<?php etranslate("Import")?>" />
</form>
</div> <!-- /IMPORT -->

<?php include_once 'export.php'; ?>
</div>
<?php print_trailer (); ?>
</body>
</html>