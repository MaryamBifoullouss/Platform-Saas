<?php
/* Copyright (C) 2011	   Dimitri Mouillard	<dmouillard@teclib.com>
 * Copyright (C) 2013-2020 Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2012-2016 Regis Houssin	<regis.houssin@inodbox.com>
 * Copyright (C) 2018      Charlene Benke	<charlie@patas-monkey.com>
 * Copyright (C) 2019-2021 Frédéric France		<frederic.france@netlogic.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *   	\file       htdocs/holiday/list.php
 *		\ingroup    holiday
 *		\brief      List of holiday
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';
require_once DOL_DOCUMENT_ROOT.'/holiday/class/holiday.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php';

// Load translation files required by the page
$langs->loadLangs(array('users', 'other', 'holiday', 'hrm'));

// Protection if external user
if ($user->socid > 0) {
	accessforbidden();
}

$action     = GETPOST('action', 'aZ09'); // The action 'add', 'create', 'edit', 'update', 'view', ...
$massaction = GETPOST('massaction', 'alpha'); // The bulk action (combo box choice into lists)
$show_files = GETPOST('show_files', 'int'); // Show files area generated by bulk actions ?
$confirm    = GETPOST('confirm', 'alpha'); // Result of a confirmation
$cancel     = GETPOST('cancel', 'alpha'); // We click on a Cancel button
$toselect   = GETPOST('toselect', 'array'); // Array of ids of elements selected into a list
$contextpage = GETPOST('contextpage', 'aZ') ?GETPOST('contextpage', 'aZ') : 'holidaylist'; // To manage different context of search

$backtopage = GETPOST('backtopage', 'alpha'); // Go back to a dedicated page
$optioncss = GETPOST('optioncss', 'aZ'); // Option for the css output (always '' except when 'print')

$id = GETPOST('id', 'int');

$childids = $user->getAllChildIds(1);

// Security check
$socid = 0;
if ($user->socid > 0) {	// Protection if external user
	//$socid = $user->socid;
	accessforbidden();
}
$result = restrictedArea($user, 'holiday', '', '');
// If we are on the view of a specific user
if ($id > 0) {
	$canread = 0;
	if ($id == $user->id) {
		$canread = 1;
	}
	if (!empty($user->rights->holiday->readall)) {
		$canread = 1;
	}
	if (!empty($user->rights->holiday->read) && in_array($id, $childids)) {
		$canread = 1;
	}
	if (!$canread) {
		accessforbidden();
	}
}

$diroutputmassaction = $conf->holiday->dir_output.'/temp/massgeneration/'.$user->id;


// Load variable for pagination
$limit = GETPOST('limit', 'int') ?GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page == -1) {
	$page = 0;
}     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (!$sortorder) {
	$sortorder = "DESC";
}
if (!$sortfield) {
	$sortfield = "cp.rowid";
}

$sall                = trim((GETPOST('search_all', 'alphanohtml') != '') ?GETPOST('search_all', 'alphanohtml') : GETPOST('sall', 'alphanohtml'));
$search_ref          = GETPOST('search_ref', 'alphanohtml');
$search_day_create   = GETPOST('search_day_create', 'int');
$search_month_create = GETPOST('search_month_create', 'int');
$search_year_create  = GETPOST('search_year_create', 'int');
$search_day_start    = GETPOST('search_day_start', 'int');
$search_month_start  = GETPOST('search_month_start', 'int');
$search_year_start   = GETPOST('search_year_start', 'int');
$search_day_end      = GETPOST('search_day_end', 'int');
$search_month_end    = GETPOST('search_month_end', 'int');
$search_year_end     = GETPOST('search_year_end', 'int');
$search_employee     = GETPOST('search_employee', 'int');
$search_valideur     = GETPOST('search_valideur', 'int');
$search_status       = GETPOST('search_statut', 'int');
$search_type         = GETPOST('search_type', 'int');

// Initialize technical objects
$object = new Holiday($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array('holidaylist')); // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array(
	'cp.ref'=>'Ref',
	'cp.description'=>'Description',
	'uu.lastname'=>'EmployeeLastname',
	'uu.firstname'=>'EmployeeFirstname',
	'uu.login'=>'Login'
);

$arrayfields = array(
	'cp.ref'=>array('label'=>$langs->trans("Ref"), 'checked'=>1),
	'cp.fk_user'=>array('label'=>$langs->trans("Employee"), 'checked'=>1, 'position'=>20),
	'cp.fk_validator'=>array('label'=>$langs->trans("ValidatorCP"), 'checked'=>1, 'position'=>30),
	'cp.fk_type'=>array('label'=>$langs->trans("Type"), 'checked'=>1, 'position'=>35),
	'duration'=>array('label'=>$langs->trans("NbUseDaysCPShort"), 'checked'=>1, 'position'=>38),
	'cp.date_debut'=>array('label'=>$langs->trans("DateStart"), 'checked'=>1, 'position'=>40),
	'cp.date_fin'=>array('label'=>$langs->trans("DateEnd"), 'checked'=>1, 'position'=>42),
	'cp.date_valid'=>array('label'=>$langs->trans("DateValidation"), 'checked'=>1, 'position'=>60),
	'cp.date_approve'=>array('label'=>$langs->trans("DateApprove"), 'checked'=>1, 'position'=>70),
	'cp.date_create'=>array('label'=>$langs->trans("DateCreation"), 'checked'=>0, 'position'=>500),
	'cp.tms'=>array('label'=>$langs->trans("DateModificationShort"), 'checked'=>0, 'position'=>501),
	'cp.statut'=>array('label'=>$langs->trans("Status"), 'checked'=>1, 'position'=>1000),
);
// Extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_array_fields.tpl.php';

if (empty($conf->holiday->enabled)) {
	llxHeader('', $langs->trans('CPTitreMenu'));
	print '<div class="tabBar">';
	print '<span style="color: #FF0000;">'.$langs->trans('NotActiveModCP').'</span>';
	print '</div>';
	llxFooter();
	exit();
}


/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) {
	$action = 'list'; $massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') {
	$massaction = '';
}

$parameters = array('socid'=>$socid);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	// Selection of new fields
	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
		$search_ref = "";
		$search_month_create = "";
		$search_year_create = "";
		$search_month_start = "";
		$search_year_start = "";
		$search_month_end = "";
		$search_year_end = "";
		$search_employee = "";
		$search_valideur = "";
		$search_status = "";
		$search_type = '';
		$toselect = '';
		$search_array_options = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
		$massaction = ''; // Protection to avoid mass action if we force a new search during a mass action confirmation
	}

	// Mass actions
	$objectclass = 'Holiday';
	$objectlabel = 'Holiday';
	$permissiontoread = $user->rights->holiday->read;
	$permissiontodelete = $user->rights->holiday->delete;
	$uploaddir = $conf->holiday->dir_output;
	include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';
}




/*
 * View
 */

$form = new Form($db);
$formother = new FormOther($db);
$formfile = new FormFile($db);

$fuser = new User($db);
$holidaystatic = new Holiday($db);

// Update sold
$result = $object->updateBalance();

$title = $langs->trans('CPTitreMenu');
llxHeader('', $title);

$max_year = 5;
$min_year = 10;

// Get current user id
$user_id = $user->id;

if ($id > 0) {
	// Charge utilisateur edite
	$fuser->fetch($id, '', '', 1);
	$fuser->getrights();
	$user_id = $fuser->id;

	$search_employee = $user_id;
}

// Récupération des congés payés de l'utilisateur ou de tous les users de sa hierarchy
// Load array $object->holiday

$sql = "SELECT";
$sql .= " cp.rowid,";
$sql .= " cp.ref,";

$sql .= " cp.fk_user,";
$sql .= " cp.fk_type,";
$sql .= " cp.date_create,";
$sql .= " cp.tms as date_update,";
$sql .= " cp.description,";
$sql .= " cp.date_debut,";
$sql .= " cp.date_fin,";
$sql .= " cp.halfday,";
$sql .= " cp.statut as status,";
$sql .= " cp.fk_validator,";
$sql .= " cp.date_valid,";
$sql .= " cp.fk_user_valid,";
$sql .= " cp.date_refuse,";
$sql .= " cp.fk_user_refuse,";
$sql .= " cp.date_cancel,";
$sql .= " cp.fk_user_cancel,";
$sql .= " cp.detail_refuse,";

$sql .= " uu.lastname as user_lastname,";
$sql .= " uu.firstname as user_firstname,";
$sql .= " uu.admin as user_admin,";
$sql .= " uu.email as user_email,";
$sql .= " uu.login as user_login,";
$sql .= " uu.statut as user_status,";
$sql .= " uu.photo as user_photo,";

$sql .= " ua.lastname as validator_lastname,";
$sql .= " ua.firstname as validator_firstname,";
$sql .= " ua.admin as validator_admin,";
$sql .= " ua.email as validator_email,";
$sql .= " ua.login as validator_login,";
$sql .= " ua.statut as validator_status,";
$sql .= " ua.photo as validator_photo";
// Add fields from extrafields
if (!empty($extrafields->attributes[$object->table_element]['label'])) {
	foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) {
		$sql .= ($extrafields->attributes[$object->table_element]['type'][$key] != 'separate' ? ", ef.".$key.' as options_'.$key : '');
	}
}
// Add fields from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters); // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;
$sql .= " FROM ".MAIN_DB_PREFIX."holiday as cp";
if (is_array($extrafields->attributes[$object->table_element]['label']) && count($extrafields->attributes[$object->table_element]['label'])) {
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX.$object->table_element."_extrafields as ef on (cp.rowid = ef.fk_object)";
}
$sql .= ", ".MAIN_DB_PREFIX."user as uu, ".MAIN_DB_PREFIX."user as ua";
$sql .= " WHERE cp.entity IN (".getEntity('holiday').")";
$sql .= " AND cp.fk_user = uu.rowid AND cp.fk_validator = ua.rowid "; // Hack pour la recherche sur le tableau
// Search all
if (!empty($sall)) {
	$sql .= natural_search(array_keys($fieldstosearchall), $sall);
}
// Ref
if (!empty($search_ref)) {
	$sql .= natural_search("cp.ref", $search_ref);
}
// Start date
$sql .= dolSqlDateFilter("cp.date_debut", $search_day_start, $search_month_start, $search_year_start);
// End date
$sql .= dolSqlDateFilter("cp.date_fin", $search_day_end, $search_month_end, $search_year_end);
// Create date
$sql .= dolSqlDateFilter("cp.date_create", $search_day_create, $search_month_create, $search_year_create);
// Employee
if (!empty($search_employee) && $search_employee != -1) {
	$sql .= " AND cp.fk_user = '".$db->escape($search_employee)."'\n";
}
// Validator
if (!empty($search_valideur) && $search_valideur != -1) {
	$sql .= " AND cp.fk_validator = '".$db->escape($search_valideur)."'\n";
}
// Type
if (!empty($search_type) && $search_type != -1) {
	$sql .= ' AND cp.fk_type IN ('.$db->sanitize($db->escape($search_type)).')';
}
// Status
if (!empty($search_status) && $search_status != -1) {
	$sql .= " AND cp.statut = '".$db->escape($search_status)."'\n";
}

if (empty($user->rights->holiday->readall)) {
	$sql .= ' AND cp.fk_user IN ('.$db->sanitize(join(',', $childids)).')';
}
if ($id > 0) {
	$sql .= " AND cp.fk_user IN (".$db->sanitize($id).")";
}

// Add where from extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_sql.tpl.php';
// Add where from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters); // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;

$sql .= $db->order($sortfield, $sortorder);

// Count total nb of records
$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST)) {
	$result = $db->query($sql);
	$nbtotalofrecords = $db->num_rows($result);
	if (($page * $limit) > $nbtotalofrecords) {	// if total resultset is smaller then paging size (filtering), goto and load page 0
		$page = 0;
		$offset = 0;
	}
}

$sql .= $db->plimit($limit + 1, $offset);


//print $sql;
$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);

	$arrayofselected = is_array($toselect) ? $toselect : array();

	$param = '';
	if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
		$param .= '&contextpage='.urlencode($contextpage);
	}
	if ($limit > 0 && $limit != $conf->liste_limit) {
		$param .= '&limit='.urlencode($limit);
	}
	if ($optioncss != '') {
		$param .= '&optioncss='.urlencode($optioncss);
	}
	if ($search_ref) {
		$param .= '&search_ref='.urlencode($search_ref);
	}
	if ($search_day_create) {
		$param .= '&search_day_create='.urlencode($search_day_create);
	}
	if ($search_month_create) {
		$param .= '&search_month_create='.urlencode($search_month_create);
	}
	if ($search_year_create) {
		$param .= '&search_year_create='.urlencode($search_year_create);
	}
	if ($search_day_start) {
		$param .= '&search_day_start='.urlencode($search_day_start);
	}
	if ($search_month_start) {
		$param .= '&search_month_start='.urlencode($search_month_start);
	}
	if ($search_year_start) {
		$param .= '&search_year_start='.urlencode($search_year_start);
	}
	if ($search_day_end) {
		$param .= '&search_day_end='.urlencode($search_day_end);
	}
	if ($search_month_end) {
		$param .= '&search_month_end='.urlencode($search_month_end);
	}
	if ($search_year_end) {
		$param .= '&search_year_end='.urlencode($search_year_end);
	}
	if ($search_employee > 0) {
		$param .= '&search_employee='.urlencode($search_employee);
	}
	if ($search_valideur > 0) {
		$param .= '&search_valideur='.urlencode($search_valideur);
	}
	if ($search_type > 0) {
		$param .= '&search_type='.urlencode($search_type);
	}
	if ($search_status > 0) {
		$param .= '&search_status='.urlencode($search_status);
	}
	// Add $param from extra fields
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_param.tpl.php';

	// List of mass actions available
	$arrayofmassactions = array(
		//'generate_doc'=>img_picto('', 'pdf', 'class="pictofixedwidth"').$langs->trans("ReGeneratePDF"),
		//'builddoc'=>img_picto('', 'pdf', 'class="pictofixedwidth"').$langs->trans("PDFMerge"),
		//'presend'=>img_picto('', 'email', 'class="pictofixedwidth"').$langs->trans("SendByMail"),
	);
	if (!empty($user->rights->holiday->delete)) {
		$arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans("Delete");
	}
	if (in_array($massaction, array('presend', 'predelete'))) {
		$arrayofmassactions = array();
	}
	$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

	// Lines of title fields
	print '<form id="searchFormList" action="'.$_SERVER["PHP_SELF"].'" method="POST">'."\n";
	if ($optioncss != '') {
		print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
	}
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
	print '<input type="hidden" name="action" value="'.($action == 'edit' ? 'update' : 'list').'">';
	print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
	print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
	print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
	if ($id > 0) {
		print '<input type="hidden" name="id" value="'.$id.'">';
	}

	if ($id > 0) {		// For user tab
		$title = $langs->trans("User");
		$linkback = '<a href="'.DOL_URL_ROOT.'/user/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
		$head = user_prepare_head($fuser);

		print dol_get_fiche_head($head, 'paidholidays', $title, -1, 'user');

		dol_banner_tab($fuser, 'id', $linkback, $user->rights->user->user->lire || $user->admin);

		if (empty($conf->global->HOLIDAY_HIDE_BALANCE)) {
			print '<div class="underbanner clearboth"></div>';

			print '<br>';

			showMyBalance($object, $user_id);
		}

		print dol_get_fiche_end();

		// Buttons for actions

		print '<div class="tabsAction">';

		$canedit = (($user->id == $user_id && $user->rights->holiday->write) || ($user->id != $user_id && (!empty($conf->global->MAIN_USE_ADVANCED_PERMS) && !empty($user->rights->holiday->writeall_advance))));

		if ($canedit) {
			print '<a href="'.DOL_URL_ROOT.'/holiday/card.php?action=create&fuserid='.$user_id.'" class="butAction">'.$langs->trans("AddCP").'</a>';
		}

		print '</div>';
	} else {
		$title = $langs->trans("ListeCP");

		$newcardbutton = dolGetButtonTitle($langs->trans('MenuAddCP'), '', 'fa fa-plus-circle', DOL_URL_ROOT.'/holiday/card.php?action=create', '', $user->rights->holiday->write);

		print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'title_hrm', 0, $newcardbutton, '', $limit, 0, 0, 1);
	}

	$topicmail = "Information";
	$modelmail = "leaverequest";
	$objecttmp = new Holiday($db);
	$trackid = 'leav'.$object->id;
	include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';

	if ($sall) {
		foreach ($fieldstosearchall as $key => $val) {
			$fieldstosearchall[$key] = $langs->trans($val);
		}
		print '<div class="divsearchfieldfilter">'.$langs->trans("FilterOnInto", $sall).join(', ', $fieldstosearchall).'</div>';
	}

	$moreforfilter = '';

	$parameters = array();
	$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters); // Note that $action and $object may have been modified by hook
	if (empty($reshook)) {
		$moreforfilter .= $hookmanager->resPrint;
	} else {
		$moreforfilter = $hookmanager->resPrint;
	}

	if (!empty($moreforfilter)) {
		print '<div class="liste_titre liste_titre_bydiv centpercent">';
		print $moreforfilter;
		print '</div>';
	}

	$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
	$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage); // This also change content of $arrayfields
	$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');


	$include = '';
	if (empty($user->rights->holiday->readall)) {
		$include = 'hierarchyme'; // Can see only its hierarchyl
	}

	print '<div class="div-table-responsive">';
	print '<table class="tagtable liste'.($moreforfilter ? " listwithfilterbefore" : "").'">'."\n";


	// Filters
	print '<tr class="liste_titre_filter">';

	if (!empty($arrayfields['cp.ref']['checked'])) {
		print '<td class="liste_titre">';
		print '<input class="flat maxwidth50" type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'">';
		print '</td>';
	}

	if (!empty($arrayfields['cp.fk_user']['checked'])) {
		$morefilter = '';
		if (!empty($conf->global->HOLIDAY_HIDE_FOR_NON_SALARIES)) {
			$morefilter = 'AND employee = 1';
		}

		// User
		$disabled = 0;
		// If into the tab holiday of a user ($id is set in such a case)
		if ($id && !GETPOSTISSET('search_employee')) {
			$search_employee = $id;
			$disabled = 1;
		}

		print '<td class="liste_titre maxwidthonsmartphone left">';
		print $form->select_dolusers($search_employee, "search_employee", 1, "", $disabled, $include, '', 0, 0, 0, $morefilter, 0, '', 'maxwidth150');
		print '</td>';
	}

	// Approver
	if (!empty($arrayfields['cp.fk_validator']['checked'])) {
		if ($user->rights->holiday->readall) {
			print '<td class="liste_titre maxwidthonsmartphone left">';
			$validator = new UserGroup($db);
			$excludefilter = $user->admin ? '' : 'u.rowid <> '.$user->id;
			$valideurobjects = $validator->listUsersForGroup($excludefilter);
			$valideurarray = array();
			foreach ($valideurobjects as $val) {
				$valideurarray[$val->id] = $val->id;
			}
			print $form->select_dolusers($search_valideur, "search_valideur", 1, "", 0, $valideurarray, '', 0, 0, 0, $morefilter, 0, '', 'maxwidth150');
			print '</td>';
		} else {
			print '<td class="liste_titre">&nbsp;</td>';
		}
	}

	// Type
	if (!empty($arrayfields['cp.fk_type']['checked'])) {
		print '<td class="liste_titre">';
		if (empty($mysoc->country_id)) {
			setEventMessages(null, array($langs->trans("ErrorSetACountryFirst"), $langs->trans("CompanyFoundation")), 'errors');
		} else {
			$typeleaves = $holidaystatic->getTypes(1, -1);
			$arraytypeleaves = array();
			foreach ($typeleaves as $key => $val) {
				$labeltoshow = ($langs->trans($val['code']) != $val['code'] ? $langs->trans($val['code']) : $val['label']);
				//$labeltoshow .= ($val['delay'] > 0 ? ' ('.$langs->trans("NoticePeriod").': '.$val['delay'].' '.$langs->trans("days").')':'');
				$arraytypeleaves[$val['rowid']] = $labeltoshow;
			}
			print $form->selectarray('search_type', $arraytypeleaves, $search_type, 1, 0, 0, '', 0, 0, 0, '', '', 1);
		}
		print '</td>';
	}

	// Duration
	if (!empty($arrayfields['duration']['checked'])) {
		print '<td class="liste_titre">&nbsp;</td>';
	}

	// Start date
	if (!empty($arrayfields['cp.date_debut']['checked'])) {
		print '<td class="liste_titre center nowraponall">';
		print '<input class="flat valignmiddle maxwidth25" type="text" maxlength="2" name="search_month_start" value="'.dol_escape_htmltag($search_month_start).'">';
		$formother->select_year($search_year_start, 'search_year_start', 1, $min_year, $max_year);
		print '</td>';
	}

	// End date
	if (!empty($arrayfields['cp.date_fin']['checked'])) {
		print '<td class="liste_titre center nowraponall">';
		print '<input class="flat valignmiddle maxwidth25" type="text" maxlength="2" name="search_month_end" value="'.dol_escape_htmltag($search_month_end).'">';
		$formother->select_year($search_year_end, 'search_year_end', 1, $min_year, $max_year);
		print '</td>';
	}

	// Extra fields
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_input.tpl.php';
	// Fields from hook
	$parameters = array('arrayfields'=>$arrayfields);
	$reshook = $hookmanager->executeHooks('printFieldListOption', $parameters); // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;

	// Create date
	if (!empty($arrayfields['cp.date_create']['checked'])) {
		print '<td class="liste_titre center nowraponall">';
		print '<input class="flat valignmiddle maxwidth25" type="text" maxlength="2" name="search_month_create" value="'.dol_escape_htmltag($search_month_create).'">';
		$formother->select_year($search_year_create, 'search_year_create', 1, $min_year, 0);
		print '</td>';
	}

	// Create date
	if (!empty($arrayfields['cp.tms']['checked'])) {
		print '<td class="liste_titre center nowraponall">';
		print '<input class="flat valignmiddle maxwidth25" type="text" maxlength="2" name="search_month_update" value="'.dol_escape_htmltag($search_month_update).'">';
		$formother->select_year($search_year_update, 'search_year_update', 1, $min_year, 0);
		print '</td>';
	}

	// Status
	if (!empty($arrayfields['cp.statut']['checked'])) {
		print '<td class="liste_titre maxwidthonsmartphone maxwidth200 right">';
		$object->selectStatutCP($search_status, 'search_status');
		print '</td>';
	}

	// Action column
	print '<td class="liste_titre maxwidthsearch">';
	$searchpicto = $form->showFilterButtons();
	print $searchpicto;
	print '</td>';

	print "</tr>\n";

	print '<tr class="liste_titre">';
	if (!empty($arrayfields['cp.ref']['checked'])) {
		print_liste_field_titre($arrayfields['cp.ref']['label'], $_SERVER["PHP_SELF"], "cp.ref", "", $param, '', $sortfield, $sortorder);
	}
	if (!empty($arrayfields['cp.fk_user']['checked'])) {
		print_liste_field_titre($arrayfields['cp.fk_user']['label'], $_SERVER["PHP_SELF"], "cp.fk_user", "", $param, '', $sortfield, $sortorder);
	}
	if (!empty($arrayfields['cp.fk_validator']['checked'])) {
		print_liste_field_titre($arrayfields['cp.fk_validator']['label'], $_SERVER["PHP_SELF"], "cp.fk_validator", "", $param, '', $sortfield, $sortorder);
	}
	if (!empty($arrayfields['cp.fk_type']['checked'])) {
		print_liste_field_titre($arrayfields['cp.fk_type']['label'], $_SERVER["PHP_SELF"], '', '', $param, '', $sortfield, $sortorder);
	}
	if (!empty($arrayfields['duration']['checked'])) {
		print_liste_field_titre($arrayfields['duration']['label'], $_SERVER["PHP_SELF"], '', '', $param, '', $sortfield, $sortorder, 'right maxwidth100');
	}
	if (!empty($arrayfields['cp.date_debut']['checked'])) {
		print_liste_field_titre($arrayfields['cp.date_debut']['label'], $_SERVER["PHP_SELF"], "cp.date_debut", "", $param, '', $sortfield, $sortorder, 'center ');
	}
	if (!empty($arrayfields['cp.date_fin']['checked'])) {
		print_liste_field_titre($arrayfields['cp.date_fin']['label'], $_SERVER["PHP_SELF"], "cp.date_fin", "", $param, '', $sortfield, $sortorder, 'center ');
	}
	// Extra fields
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_title.tpl.php';
	// Hook fields
	$parameters = array('arrayfields'=>$arrayfields, 'param'=>$param, 'sortfield'=>$sortfield, 'sortorder'=>$sortorder);
	$reshook = $hookmanager->executeHooks('printFieldListTitle', $parameters); // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;
	if (!empty($arrayfields['cp.date_create']['checked'])) {
		print_liste_field_titre($arrayfields['cp.date_create']['label'], $_SERVER["PHP_SELF"], "cp.date_create", "", $param, '', $sortfield, $sortorder, 'center ');
	}
	if (!empty($arrayfields['cp.tms']['checked'])) {
		print_liste_field_titre($arrayfields['cp.tms']['label'], $_SERVER["PHP_SELF"], "cp.tms", "", $param, '', $sortfield, $sortorder, 'center ');
	}
	if (!empty($arrayfields['cp.statut']['checked'])) {
		print_liste_field_titre("Status", $_SERVER["PHP_SELF"], "cp.statut", "", $param, '', $sortfield, $sortorder, 'right ');
	}
	print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', '', 'align="center"', $sortfield, $sortorder, 'maxwidthsearch ');
	print "</tr>\n";

	$listhalfday = array('morning'=>$langs->trans("Morning"), "afternoon"=>$langs->trans("Afternoon"));


	// If we ask a dedicated card and not allow to see it, we force on user.
	if ($id && empty($user->rights->holiday->readall) && !in_array($id, $childids)) {
		$langs->load("errors");
		print '<tr class="oddeven opacitymediuem"><td colspan="10">'.$langs->trans("NotEnoughPermissions").'</td></tr>';
		$result = 0;
	} elseif ($num > 0 && !empty($mysoc->country_id)) {
		// Lines
		$userstatic = new User($db);
		$approbatorstatic = new User($db);

		$typeleaves = $object->getTypes(1, -1);

		$i = 0;
		$totalarray = array();
		while ($i < min($num, $limit)) {
			$obj = $db->fetch_object($resql);

			// Leave request
			$holidaystatic->id = $obj->rowid;
			$holidaystatic->ref = ($obj->ref ? $obj->ref : $obj->rowid);
			$holidaystatic->statut = $obj->status;

			// User
			$userstatic->id = $obj->fk_user;
			$userstatic->lastname = $obj->user_lastname;
			$userstatic->firstname = $obj->user_firstname;
			$userstatic->admin = $obj->user_admin;
			$userstatic->email = $obj->user_email;
			$userstatic->login = $obj->user_login;
			$userstatic->statut = $obj->user_status;
			$userstatic->photo = $obj->user_photo;

			// Validator
			$approbatorstatic->id = $obj->fk_validator;
			$approbatorstatic->lastname = $obj->validator_lastname;
			$approbatorstatic->firstname = $obj->validator_firstname;
			$approbatorstatic->admin = $obj->validator_admin;
			$approbatorstatic->email = $obj->validator_email;
			$approbatorstatic->login = $obj->validator_login;
			$approbatorstatic->statut = $obj->validator_status;
			$approbatorstatic->photo = $obj->validator_photo;

			$date = $obj->date_create;
			$date_modif = $obj->date_update;

			$starthalfday = ($obj->halfday == -1 || $obj->halfday == 2) ? 'afternoon' : 'morning';
			$endhalfday = ($obj->halfday == 1 || $obj->halfday == 2) ? 'morning' : 'afternoon';

			print '<tr class="oddeven">';

			if (!empty($arrayfields['cp.ref']['checked'])) {
				print '<td class="nowraponall">';
				print $holidaystatic->getNomUrl(1, 1);
				print '</td>';
				if (!$i) {
					$totalarray['nbfield']++;
				}
			}
			if (!empty($arrayfields['cp.fk_user']['checked'])) {
				print '<td class="tdoverflowmax150">'.$userstatic->getNomUrl(-1, 'leave').'</td>';
				if (!$i) {
					$totalarray['nbfield']++;
				}
			}
			if (!empty($arrayfields['cp.fk_validator']['checked'])) {
				print '<td class="tdoverflowmax150">'.$approbatorstatic->getNomUrl(-1).'</td>';
				if (!$i) {
					$totalarray['nbfield']++;
				}
			}
			if (!empty($arrayfields['cp.fk_type']['checked'])) {
				print '<td>';
				$labeltypeleavetoshow = ($langs->trans($typeleaves[$obj->fk_type]['code']) != $typeleaves[$obj->fk_type]['code'] ? $langs->trans($typeleaves[$obj->fk_type]['code']) : $typeleaves[$obj->fk_type]['label']);
				print empty($typeleaves[$obj->fk_type]['label']) ? $langs->trans("TypeWasDisabledOrRemoved", $obj->fk_type) : $labeltypeleavetoshow;
				print '</td>';
				if (!$i) {
					$totalarray['nbfield']++;
				}
			}
			if (!empty($arrayfields['duration']['checked'])) {
				print '<td class="right">';
				$nbopenedday = num_open_day($db->jdate($obj->date_debut, 1), $db->jdate($obj->date_fin, 1), 0, 1, $obj->halfday);
				print $nbopenedday.' '.$langs->trans('DurationDays');
				print '</td>';
				if (!$i) {
					$totalarray['nbfield']++;
				}
			}
			if (!empty($arrayfields['cp.date_debut']['checked'])) {
				print '<td class="center">';
				print dol_print_date($db->jdate($obj->date_debut), 'day');
				print ' <span class="opacitymedium nowraponall">('.$langs->trans($listhalfday[$starthalfday]).')</span>';
				print '</td>';
				if (!$i) {
					$totalarray['nbfield']++;
				}
			}
			if (!empty($arrayfields['cp.date_fin']['checked'])) {
				print '<td class="center">';
				print dol_print_date($db->jdate($obj->date_fin), 'day');
				print ' <span class="opacitymedium nowraponall">('.$langs->trans($listhalfday[$endhalfday]).')</span>';
				print '</td>';
				if (!$i) {
					$totalarray['nbfield']++;
				}
			}

			// Extra fields
			include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';
			// Fields from hook
			$parameters = array('arrayfields'=>$arrayfields, 'obj'=>$obj, 'i'=>$i, 'totalarray'=>&$totalarray);
			$reshook = $hookmanager->executeHooks('printFieldListValue', $parameters); // Note that $action and $object may have been modified by hook
			print $hookmanager->resPrint;

			// Date creation
			if (!empty($arrayfields['cp.date_create']['checked'])) {
				print '<td style="text-align: center;">'.dol_print_date($date, 'dayhour').'</td>';
				if (!$i) {
					$totalarray['nbfield']++;
				}
			}
			if (!empty($arrayfields['cp.tms']['checked'])) {
				print '<td style="text-align: center;">'.dol_print_date($date_modif, 'dayhour').'</td>';
				if (!$i) {
					$totalarray['nbfield']++;
				}
			}
			if (!empty($arrayfields['cp.statut']['checked'])) {
				print '<td class="right nowrap">'.$holidaystatic->getLibStatut(5).'</td>';
				if (!$i) {
					$totalarray['nbfield']++;
				}
			}

			// Action column
			print '<td class="nowrap center">';
			if ($massactionbutton || $massaction) {   // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
				$selected = 0;
				if (in_array($obj->rowid, $arrayofselected)) {
					$selected = 1;
				}
				print '<input id="cb'.$obj->rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->rowid.'"'.($selected ? ' checked="checked"' : '').'>';
			}
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}

			print '</tr>'."\n";

			$i++;
		}
	}

	// Si il n'y a pas d'enregistrement suite à une recherche
	if ($num == 0) {
		$colspan = 1;
		foreach ($arrayfields as $key => $val) {
			if (!empty($val['checked'])) {
				$colspan++;
			}
		}
		print '<tr><td colspan="'.$colspan.'" class="opacitymedium">'.$langs->trans("NoRecordFound").'</td></tr>';
	}

	print '</table>';
	print '</div>';

	print '</form>';
} else {
	dol_print_error($db);
}

// End of page
llxFooter();
$db->close();





/**
 * Show balance of user
 *
 * @param 	Holiday	$holiday	Object $holiday
 * @param	int		$user_id	User id
 * @return	string				Html code with balance
 */
function showMyBalance($holiday, $user_id)
{
	global $conf, $langs;

	$alltypeleaves = $holiday->getTypes(1, -1); // To have labels

	$out = '';
	$nb_holiday = 0;
	$typeleaves = $holiday->getTypes(1, 1);
	foreach ($typeleaves as $key => $val) {
		$nb_type = $holiday->getCPforUser($user_id, $val['rowid']);
		$nb_holiday += $nb_type;
		$out .= ' - '.$val['label'].': <strong>'.($nb_type ?price2num($nb_type) : 0).'</strong><br>';
	}
	print $langs->trans('SoldeCPUser', round($nb_holiday, 5)).'<br>';
	print $out;
}
