<?php
//
// SourceForge: Breaking Down the Barriers to Open Source Development
// Copyright 1999-2000 (c) The SourceForge Crew
// http://sourceforge.net
//
// 

header("Cache-Control: no-cache, no-store, must-revalidate");

require_once('pre.php');


require_once('common/event/EventManager.class.php');
$em =& EventManager::instance();
$em->processEvent('before_lostpw', array());

$HTML->header(array('title'=>$Language->getText('account_lostpw', 'title')));

?>

<h2><?php echo $Language->getText('account_lostpw', 'title'); ?></h2>
<P><?php echo $Language->getText('account_lostpw', 'message'); ?></P>

<FORM action="lostpw-confirm.php" method="post" class="form-inline">
<P>
Login Name:
<INPUT type="text" name="form_loginname">
<INPUT class="btn btn-primary" type="submit" name="Send Lost Password Hash" value="<?php echo $Language->getText('account_lostpw', 'send_hash'); ?>">
</FORM>

<P><A href="/">[<?php echo $Language->getText('global', 'back_home'); ?>]</A>

<?php
$HTML->footer(array());

?>
