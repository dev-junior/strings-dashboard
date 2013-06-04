<?php

$this->extend('/Common/standard');

$this->assign('title', 'User Management');

//Set sidebar content
$this->start('sidebar');
echo $this->element('activity_log',array(
    'activityLogUri' => ''
));
$this->end();

//Main content
echo $this->element('Datatables/default',array(
	'model' => 'sudoRole',
	'title' => 'Sudo roles',
    'columnHeadings' => $sudoTableColumns,
	'ctaTitle' => 'Sudo Role',
	'ctaButtonText' => 'Create sudo role',
	'ctaSrc' => '/sudoRoles/create.json',
	'ctaWidth' => '500',
    'ctaEnabled' => true
));
