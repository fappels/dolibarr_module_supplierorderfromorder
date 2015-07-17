<?php

$res=@include("../../main.inc.php");						// For root directory
if (! $res) $res=@include("../../../main.inc.php");			// For "custom" directory

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$langs->load("admin");
$langs->load('supplierorderfromorder@supplierorderfromorder');

global $db;

// Security check
if (! $user->admin) accessforbidden();

$action=GETPOST('action');
$id=GETPOST('id');

/*
 * Action
 */
if (preg_match('/set_(.*)/',$action,$reg))
{
	$code=$reg[1];
	if (dolibarr_set_const($db, $code, GETPOST($code), 'chaine', 0, '', $conf->entity) > 0)
	{
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}
	
if (preg_match('/del_(.*)/',$action,$reg))
{
	$code=$reg[1];
	if (dolibarr_del_const($db, $code, 0) > 0)
	{
		Header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

/*
 * View
 */

llxHeader('',$langs->trans("SupplierOrderFromOrder"));

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans("SupplierOrderFromOrder"),$linkback,'supplierorderfromorder@supplierorderfromorder');

print '<br>';

$form=new Form($db);
$var=true;
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameters").'</td>'."\n";
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";


// Create new order each time instead of filling existing draft one
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("CreateNewSupplierOrderAnyTime").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SOFO_CREATE_NEW_SUPPLIER_ODER_ANY_TIME">';
print $form->selectyesno("SOFO_CREATE_NEW_SUPPLIER_ODER_ANY_TIME",$conf->global->SOFO_CREATE_NEW_SUPPLIER_ODER_ANY_TIME,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

// Create a new line in supplier order for each line having a different description in the customer order
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("UseOrderLineDescInSupplierOrder").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SUPPORDERFROMORDER_USE_ORDER_DESC">';
print $form->selectyesno("SUPPORDERFROMORDER_USE_ORDER_DESC",$conf->global->SUPPORDERFROMORDER_USE_ORDER_DESC,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

// Create a new line in supplier order for each line having a different description in the customer order
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("GetOrderPaimentInfo").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SOFO_GET_INFOS_FROM_ORDER">';
print $form->selectyesno("SOFO_GET_INFOS_FROM_ORDER",$conf->global->SOFO_GET_INFOS_FROM_ORDER,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

// Create identical supplier order to order 
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("AddFreeLinesInSupplierOrder").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SOFO_ADD_FREE_LINES">';
print $form->selectyesno("SOFO_ADD_FREE_LINES",$conf->global->SOFO_ADD_FREE_LINES,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

print '</table>';

// Footer
llxFooter();
// Close database handler
$db->close();
