<?php
/*
 * Copyright (C) 2013   Cédric Salvador    <csalvador@gpcsolutions.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *  \file       htdocs/product/stock/replenish.php
 *  \ingroup    produit
 *  \brief      Page to list stocks to replenish
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';

//include_once(DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php');
/*$hookmanager=new HookManager($db);
$hookmanager->initHooks(array('context'));
//require_once './lib/replenishment.lib.php';

$parameters=array();
$reshook=$hookmanager->executeHooks('formObjectOptions',$parameters,$object,$action); // See description below
// Note that $action and $object may have been modified by hook
if (empty($reshook))
{
  // standard code that can be disabled/replaced by hook if return code > 0.
}*/

$prod = new Product($db);

$langs->load("products");
$langs->load("stocks");
$langs->load("orders");
$langs->load("supplierorderfromorder@supplierorderfromorder");

/*echo "<form name=\"formCreateSupplierOrder\" method=\"post\" action=\"ordercustomer.php\">";*/

// Security check
if ($user->societe_id) {
    $socid = $user->societe_id;
}
$result=restrictedArea($user,'produit|service');

//checks if a product has been ordered

$action = GETPOST('action','alpha');
$sref = GETPOST('sref', 'alpha');
$snom = GETPOST('snom', 'alpha');
$sall = GETPOST('sall', 'alpha');
$type = GETPOST('type','int');
$tobuy = GETPOST('tobuy', 'int');
$salert = GETPOST('salert', 'alpha');

$sortfield = GETPOST('sortfield','alpha');
$sortorder = GETPOST('sortorder','alpha');
$page = GETPOST('page','int');

if (!$sortfield) {
    $sortfield = 'p.ref';
}

if (!$sortorder) {
    $sortorder = 'ASC';
}
$limit = $conf->liste_limit;
$offset = $limit * $page ;

/*
 * Actions
 */

if (isset($_POST['button_removefilter']) || isset($_POST['valid'])) {
    $sref = '';
    $snom = '';
    $sal = '';
    $salert = '';
}

//orders creation
//FIXME: could go in the lib
if ($action == 'order' && isset($_POST['valid'])) {
    $linecount = GETPOST('linecount', 'int');
    $box = false;
    unset($_POST['linecount']);
    if ($linecount > 0) {
        $suppliers = array();
        for ($i = 0; $i < $linecount; $i++) {
            if(GETPOST('check'.$i, 'alpha') === 'on'
              && GETPOST('fourn' . $i, 'int') > 0) { //one line
                $box = $i;
                $supplierpriceid = GETPOST('fourn'.$i, 'int');
                //get all the parameters needed to create a line
                $qty = GETPOST('tobuy'.$i, 'int');
                $desc = GETPOST('desc'.$i, 'alpha');
                $sql = 'SELECT fk_product, fk_soc, ref_fourn';
                $sql .= ', tva_tx, unitprice FROM ';
                $sql .= MAIN_DB_PREFIX . 'product_fournisseur_price';
                $sql .= ' WHERE rowid = ' . $supplierpriceid;
                $resql = $db->query($sql);
                if ($resql && $db->num_rows($resql) > 0) {
                    //might need some value checks
                    $obj = $db->fetch_object($resql);
                    $line = new CommandeFournisseurLigne($db);
                    $line->qty = $qty;
                    $line->desc = $desc;
                    $line->fk_product = $obj->fk_product;
                    $line->tva_tx = $obj->tva_tx;
                    $line->subprice = $obj->unitprice;
                    $line->total_ht = $obj->unitprice * $qty;
                    $tva = $line->tva_tx / 100;
                    $line->total_tva = $line->total_ht * $tva;
                    $line->total_ttc = $line->total_ht + $line->total_tva;
                    $line->ref_fourn = $obj->ref_fourn;
                    $suppliers[$obj->fk_soc]['lines'][] = $line;
                } else {
                    $error=$db->lasterror();
                    dol_print_error($db);
                    dol_syslog('replenish.php: '.$error, LOG_ERR);
                }
                $db->free($resql);
                unset($_POST['fourn' . $i]);
            }
            unset($_POST[$i]);
        }
        //we now know how many orders we need and what lines they have
        $i = 0;
        $orders = array();
        $suppliersid = array_keys($suppliers);
        foreach ($suppliers as $idsupplier => $supplier) {
        	$sql2 = 'SELECT rowid, ref';
			$sql2 .= ' FROM ' . MAIN_DB_PREFIX . 'commande_fournisseur';
			$sql2 .= ' WHERE fk_soc = '.$idsupplier;
			$sql2 .= ' AND fk_statut = 0'; // 0 = DRAFT (Brouillon)
			$sql2 .= ' ORDER BY rowid DESC';
			$sql2 .= ' LIMIT 1';
						
			$db->query($sql2);
			$obj = $db->fetch_object($sql2);
			if($obj) {
				//echo "ok !";
				$order = new CommandeFournisseur($db);
				$order->fetch($obj->rowid);
				$order->socid = $suppliersid[$i];
				//$order->add_object_linked('commande', $_REQUEST['id']);
				$id++; //$id doit être renseigné dans tous les cas pour que s'affiche le message 'Vos commandes ont été générées'
				$newCommande = false;
			} else {
				$order = new CommandeFournisseur($db);
				$order->socid = $suppliersid[$i];
				$id = $order->create($user);
				$order->add_object_linked('commande', $_REQUEST['id']);
				$newCommande = true;
				//echo "pas ok";
			}
            //trick to know which orders have been generated this way
            $order->source = 42;
			
            foreach ($supplier['lines'] as $line) {
	            $done = false;	
            	foreach($order->lines as $lineOrderFetched) {
            		if($line->fk_product == $lineOrderFetched->fk_product) {
            			$order->updateline($lineOrderFetched->id, $lineOrderFetched->desc, $lineOrderFetched->total_ht, $lineOrderFetched->qty+$line->qty, $lineOrderFetched->remise_percent, $lineOrderFetched->tva_tx);							
						$done = true;
						break;
            			//function updateline($rowid, $desc, $pu, $qty, $remise_percent, $txtva, $txlocaltax1=0, $txlocaltax2=0, $price_base_type='HT', $info_bits=0, $type=0, $notrigger=false)
            		}
            	}
				if(!$done) {
					$order->addline($line->desc, $line->total_ht, $line->qty, $line->tva_tx, 0, 0, $line->fk_product, 0, $line->ref_fourn);
					//function addline($desc, $pu_ht, $qty, $txtva, $txlocaltax1=0, $txlocaltax2=0, $fk_product=0, $fk_prod_fourn_price=0, $fourn_ref='', $remise_percent=0, $price_base_type='HT', $pu_ttc=0, $type=0, $info_bits=0, $notrigger=false)
				}
				
            }

            $order->cond_reglement_id = 0;
            $order->mode_reglement_id = 0;
			//function updateFromCommandeClient($user, $idc, $comclientid)
			/*if(!$newCommande) {
				$id = $order->updateFromCommandeClient($user, $idc, $comclientid);
			} else {
				$id = $order->create($user);
			}
            */
            if ($id < 0) {
                $fail++;
                $msg = $langs->trans('OrderFail') . "&nbsp;:&nbsp;";
                $msg .= $order->error;
                setEventMessage($msg, 'errors');
            }
            $i++;
        }

		/*if($newCommande) {

			setEventMessage("Commande fournisseur créée avec succès !", 'errors');	
			
		} else {
			
			setEventMessage("Produits ajoutés à la commande en cours !", 'errors');
								
		}*/
			
        if (!$fail && $id) {
            setEventMessage($langs->trans('OrderCreated'), 'mesgs');
            header('Location: '.DOL_URL_ROOT.'/commande/fiche.php?id='.$_REQUEST['id']);
        }
    }
    if ($box === false) {
        setEventMessage($langs->trans('SelectProduct'), 'warnings');
    }
}

/*
 * View
 */
$title = $langs->trans('Status');

$sql = 'SELECT p.rowid, p.ref, p.label, p.price';
$sql .= ', p.price_ttc, p.price_base_type,p.fk_product_type';
$sql .= ', p.tms as datem, p.duration, p.tobuy, p.seuil_stock_alerte,';
$sql .= ' SUM(COALESCE(s.reel, 0)) as stock_physique';
//$sql .= ', p.desiredstock';
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'product as p';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'commandedet as cd';
$sql .= ' ON p.rowid = cd.fk_product';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product_stock as s';
$sql .= ' ON p.rowid = s.fk_product';
$sql.= ' WHERE p.entity IN (' . getEntity("product", 1) . ')';
$sql .= ' AND cd.fk_commande = '.$_REQUEST['id'];

if ($sall) {
    $sql .= ' AND (p.ref LIKE "%'.$db->escape($sall).'%" ';
    $sql .= 'OR p.label LIKE "%'.$db->escape($sall).'%" ';
    $sql .= 'OR p.description LIKE "%'.$db->escape($sall).'%" ';
    $sql .= 'OR p.note LIKE "%'.$db->escape($sall).'%")';
}
// if the type is not 1, we show all products (type = 0,2,3)
if (dol_strlen($type)) {
    if ($type == 1) {
        $sql .= ' AND p.fk_product_type = 1';
    } else {
        $sql .= ' AND p.fk_product_type != 1';
    }
}
if ($sref) {
    //natural search
    $scrit = explode(' ', $sref);
    foreach ($scrit as $crit) {
        $sql .= ' AND p.ref LIKE "%' . $crit . '%"';
    }
}
if ($snom) {
    //natural search
    $scrit = explode(' ', $snom);
    foreach ($scrit as $crit) {
        $sql .= ' AND p.label LIKE "%' . $db->escape($crit) . '%"';
    }
}

$sql .= ' AND p.tobuy = 1';

if (!empty($canvas)) {
    $sql .= ' AND p.canvas = "' . $db->escape($canvas) . '"';
}
$sql .= ' GROUP BY p.rowid, p.ref, p.label, p.price';
$sql .= ', p.price_ttc, p.price_base_type,p.fk_product_type, p.tms';
$sql .= ', p.duration, p.tobuy, p.seuil_stock_alerte';
//$sql .= ', p.desiredstock'; 
$sql .= ', s.fk_product';
//$sql .= ' HAVING p.desiredstock > SUM(COALESCE(s.reel, 0))';
//$sql .= ' HAVING p.desiredstock > 0';
if ($salert == 'on') {
    $sql .= ' HAVING SUM(COALESCE(s.reel, 0)) < p.seuil_stock_alerte AND p.seuil_stock_alerte is not NULL';
    $alertchecked = 'checked="checked"';
}
$sql .= $db->order($sortfield,$sortorder);
$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);

if ($resql) {
    $num = $db->num_rows($resql);
    $i = 0;

    $helpurl = 'EN:Module_Stocks_En|FR:Module_Stock|';
    $helpurl .= 'ES:M&oacute;dulo_Stocks';
    llxHeader('', $title, $helpurl, $title);
    $head = array();
    /*$head[0][0] = DOL_URL_ROOT.'/product/stock/replenish.php';
    $head[0][1] = $title;
    $head[0][2] = 'replenish';
    $head[1][0] = DOL_URL_ROOT.'/product/stock/ordercustomer.php';
    $head[1][1] = $langs->trans("ReplenishmentOrders");
    $head[1][2] = 'replenishorders';*/
    dol_fiche_head($head, 'replenish', $langs->trans('Replenishment'), 0, 'stock');
    if ($sref || $snom || $sall || $salert || GETPOST('search', 'alpha')) {
        $filters = '&sref=' . $sref . '&snom=' . $snom;
        $filters .= '&sall=' . $sall;
        $filters .= '&salert=' . $salert;
        print_barre_liste(
        		$texte,
        		$page,
        		'replenish.php',
        		$filters,
        		$sortfield,
        		$sortorder,
        		'',
        		$num
        );
    } else {
        $filters = '&sref=' . $sref . '&snom=' . $snom;
        $filters .= '&fourn_id=' . $fourn_id;
        $filters .= (isset($type)?'&type=' . $type:'');
        $filters .=  '&salert=' . $salert;
        print_barre_liste(
        		$texte,
        		$page,
        		'replenish.php',
        		$filters,
        		$sortfield,
        		$sortorder,
        		'',
        		$num
        );
    }

    print '<form action="ordercustomer.php" method="post" name="formulaire">'.
         '<input type="hidden" name="id" value="' .$_REQUEST['id'] . '">'.
         '<input type="hidden" name="token" value="' .$_SESSION['newtoken'] . '">'.
         '<input type="hidden" name="sortfield" value="' . $sortfield . '">'.
         '<input type="hidden" name="sortorder" value="' . $sortorder . '">'.
         '<input type="hidden" name="type" value="' . $type . '">'.
         '<input type="hidden" name="linecount" value="' . $num . '">'.
         '<input type="hidden" name="action" value="order">'.

         '<table class="liste" width="100%">';

    $param = (isset($type)? '&type=' . $type : '');
    $param .= '&fourn_id=' . $fourn_id . '&snom='. $snom . '&salert=' . $salert;
    $param .= '&sref=' . $sref;

    // Lignes des titres
    print '<tr class="liste_titre">'.
         '<td><input type="checkbox" onClick="toggle(this)" /></td>';
    print_liste_field_titre(
    		$langs->trans('Ref'),
    		'ordercustomer.php',
    		'p.ref',
    		$param,
    		'id='.$_REQUEST['id'],
    		'',
    		$sortfield,
    		$sortorder
    );
    print_liste_field_titre(
    		$langs->trans('Label'),
    		'ordercustomer.php',
    		'p.label',
    		$param,
    		'id='.$_REQUEST['id'],
    		'',
    		$sortfield,
    		$sortorder
    );
    if (!empty($conf->service->enabled) && $type == 1) 
    {
    	print_liste_field_titre(
    			$langs->trans('Duration'),
    			'ordercustomer.php',
    			'p.duration',
    			$param,
    			'id='.$_REQUEST['id'],
    			'align="center"',
    			$sortfield,
    			$sortorder
    	);
    }
    print_liste_field_titre(
    		$langs->trans('DesiredStock'),
    		'ordercustomer.php',
    		'p.desiredstock',
    		$param,
    		'id='.$_REQUEST['id'],
    		'align="right"',
    		$sortfield,
    		$sortorder
    );
    if ($conf->global->USE_VIRTUAL_STOCK) 
    {
        $stocklabel = $langs->trans('VirtualStock');
    }
    else 
    {
        $stocklabel = $langs->trans('PhysicalStock');
    }
    print_liste_field_titre(
    		$stocklabel,
    		'ordercustomer.php',
    		'stock_physique',
    		$param,
    		'id='.$_REQUEST['id'],
    		'align="right"',
    		$sortfield,
    		$sortorder
    );
    print_liste_field_titre(
    		$langs->trans('Ordered'),
    		'ordercustomer.php',
    		'',
    		$param,
    		'id='.$_REQUEST['id'],
    		'align="right"',
    		$sortfield,
    		$sortorder
    );
    print_liste_field_titre(
    		$langs->trans('StockToBuy'),
    		'ordercustomer.php',
    		'',
    		$param,
    		'id='.$_REQUEST['id'],
    		'align="right"',
    		$sortfield,
    		$sortorder
    );
    print_liste_field_titre(
    		$langs->trans('Supplier'),
    		'ordercustomer.php',
    		'',
    		$param,
    		'id='.$_REQUEST['id'],
    		'align="right"',
    		$sortfield,
    		$sortorder
    );
    print '<td>&nbsp;</td>'.
         '</tr>'.

	    // Lignes des champs de filtre
         '<tr class="liste_titre">'.
         '<td class="liste_titre">&nbsp;</td>'.
         '<td class="liste_titre">'.
         '<input class="flat" type="text" name="sref" value="' . $sref . '">'.
         '</td>'.
         '<td class="liste_titre">'.
         '<input class="flat" type="text" name="snom" value="' . $snom . '">'.
         '</td>';
    if (!empty($conf->service->enabled) && $type == 1) 
    {
        print '<td class="liste_titre">'.
             '&nbsp;'.
             '</td>';
    }
    print '<td class="liste_titre">&nbsp;</td>'.
         '<td class="liste_titre" align="right">' . $langs->trans('AlertOnly') . '&nbsp;<input type="checkbox" name="salert" ' . $alertchecked . '></td>'.
         '<td class="liste_titre" align="right">&nbsp;</td>'.
         '<td class="liste_titre">&nbsp;</td>'.
         '<td class="liste_titre">&nbsp;</td>'.
         '<td class="liste_titre" align="right">'.
         '<input type="image" class="liste_titre" name="button_search"'.
         'src="' . DOL_URL_ROOT . '/theme/' . $conf->theme . '/img/search.png" alt="' . $langs->trans("Search") . '">'.
         '<input type="image" class="liste_titre" name="button_removefilter"
          src="'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/searchclear.png" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">'.
         '</td>'.
         '</tr>';

    $prod = new Product($db);

    $var = True;
    while ($i < min($num, $limit)) {
        $objp = $db->fetch_object($resql);
        if ($conf->global->STOCK_SUPPORTS_SERVICES
           || $objp->fk_product_type == 0) {
            // Multilangs
            if (! empty($conf->global->MAIN_MULTILANGS)) {
                $sql = 'SELECT label';
                $sql .= ' FROM ' . MAIN_DB_PREFIX . 'product_lang';
                $sql .= ' WHERE fk_product = ' . $objp->rowid;
                $sql .= ' AND lang = "' . $langs->getDefaultLang() . '"';
                $sql .= ' LIMIT 1';

                $result = $db->query($sql);
                if ($result) {
                    $objtp = $db->fetch_object($result);
                    if (!empty($objtp->label)) {
                        $objp->label = $objtp->label;
                    }
                }
            }
            $form = new Form($db);
            $var =! $var;
            $prod->ref = $objp->ref;
            $prod->id = $objp->rowid;
            $prod->type = $objp->fk_product_type;
            //$ordered = ordered($prod->id);

            if ($conf->global->USE_VIRTUAL_STOCK) {
                //compute virtual stock
                $prod->fetch($prod->id);
                $result=$prod->load_stats_commande(0, '1,2');
                if ($result < 0) {
                    dol_print_error($db, $prod->error);
                }
                $stock_commande_client = $prod->stats_commande['qty'];
                $result=$prod->load_stats_commande_fournisseur(0, '3');
                if ($result < 0) {
                    dol_print_error($db,$prod->error);
                }
                $stock_commande_fournisseur = $prod->stats_commande_fournisseur['qty'];
                $stock = $objp->stock_physique - $stock_commande_client + $stock_commande_fournisseur;
            } else {
                $stock = $objp->stock_physique;
            }
            $warning='';
            if ($objp->seuil_stock_alerte
                && ($stock < $objp->seuil_stock_alerte)) {
                    $warning = img_warning($langs->trans('StockTooLow')) . ' ';
            }
            //depending on conf, use either physical stock or
            //virtual stock to compute the stock to buy value
            $stocktobuy = max($objp->desiredstock - $stock - $ordered, 0);
            $disabled = '';
            if($ordered > 0) {
                if($ordered + $stock >= $objp->desiredstock) {
                    $picto = img_picto('', './img/yes', '', 1);
                    $disabled = 'disabled="disabled"';
                }
                else {
                    $picto = img_picto('', './img/no', '', 1);
                }
            } else {
                $picto = img_picto('', './img/no', '', 1);
            }
            print '<tr ' . $bc[$var] . '>'.
                 '<td><input type="checkbox" class="check" name="check' . $i . '"' . $disabled . '></td>'.
                 '<td class="nowrap">'.
                 $prod->getNomUrl(1, '', 16).
                 '</td>'.
                 '<td>' . $objp->label . '</td>'.
                 '<input type="hidden" name="desc' . $i . '" value="' . $objp->label . '" >';

            if (!empty($conf->service->enabled) && $type == 1) {
                if (preg_match('/([0-9]+)y/i', $objp->duration, $regs)) {
                    $duration =  $regs[1] . ' ' . $langs->trans('DurationYear');
                } elseif (preg_match('/([0-9]+)m/i', $objp->duration, $regs)) {
                    $duration =  $regs[1] . ' ' . $langs->trans('DurationMonth');
                } elseif (preg_match('/([0-9]+)d/i', $objp->duration, $regs)) {
                    $duration =  $regs[1] . ' ' . $langs->trans('DurationDay');
                } else {
                    $duration = $objp->duration;
                }
                print '<td align="center">'.
                     $duration.
                     '</td>';
            }

            print '<td align="right">' . $objp->desiredstock . '</td>'.
                 '<td align="right">'.
                 $warning . $stock.
                 '</td>'.
                 '<td align="right">'.
                 '<a href="ordercustomer.php?sproduct=' . $prod->id . '">'.
                 $ordered . '</a> ' . $picto.
                 '</td>'.
                 '<td align="right">'.
                 '<input type="text" name="tobuy' . $i .
                 '" value="' . $stocktobuy . '" ' . $disabled . '>'.
                 '</td>'.
                 '<td align="right">'.
                 $form->select_product_fourn_price($prod->id, 'fourn'.$i, 1).
                 '</td>'.
                 '<td>&nbsp</td>'.
                 '</tr>';
        }
        $i++;
    }
    $value = $langs->trans("generateSupplierOrder");
    print '</table>'.
         '</div>'.
         '<table width="100%">'.
         '<tr><td align="right">'.
         '<input class="butAction" type="submit" name="valid" value="' . $value . '">'.
         '</td></tr></table>'.
         '</form>';

    if ($num > $conf->liste_limit) 
    {
        if ($sref || $snom || $sall || $salert || GETPOST('search', 'alpha')) 
        {
            $filters = '&sref=' . $sref . '&snom=' . $snom;
            $filters .= '&sall=' . $sall;
            $filters .= '&salert=' . $salert;
            print_barre_liste(
            		'',
            		$page,
            		'replenish.php',
            		$filters,
            		$sortfield,
            		$sortorder,
            		'',
            		$num,
            		0,
            		''
            );
        } else {
            $filters = '&sref=' . $sref . '&snom=' . $snom;
            $filters .= '&fourn_id=' . $fourn_id;
            $filters .= (isset($type)? '&type=' . $type : '');
            $filters .= '&salert=' . $salert;
            print_barre_liste(
            		'',
            		$page,
            		'replenish.php',
            		$filters,
            		$sortfield,
            		$sortorder,
            		'',
            		$num,
            		0,
            		''
            );
        }
    }

    $db->free($resql);
print ' <script type="text/javascript">
     function toggle(source)
     {
       checkboxes = document.getElementsByClassName("check");
       for (var i=0; i < checkboxes.length;i++) {
         if (!checkboxes[i].disabled) {
            checkboxes[i].checked = source.checked;
        }
       }
     } </script>';
} else {
    dol_print_error($db);
}

llxFooter();

$db->close();
?>