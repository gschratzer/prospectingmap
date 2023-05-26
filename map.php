<?php
/* Copyright (C) 2001-2004  Rodolphe Quiedeville    <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2016  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012  Regis Houssin           <regis.houssin@capnetworks.com>
 * Copyright (C) 2012       Marcos García           <marcosgdf@gmail.com>
 * Copyright (C) 2013-2015  Raphaël Doursenaud      <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2015       Florian Henry           <florian.henry@open-concept.pro>
 * Copyright (C) 2016       Josep Lluis Amador      <joseplluis@lliuretic.cat>
 * Copyright (C) 2016       Ferran Marcet      		<fmarcet@2byte.es>
 * Copyright (C) 2017       Juanjo Menent      		<jmenent@2byte.es>
 * Copyright (C) 2023       Guido Schratzer         <guido@schratzer.at>

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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/prospectingmap/map.php
 *	\ingroup    prospectingmap
 *	\brief      Page to show map of prospects
 */

// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include '../main.inc.php';			// to work if your module directory is into a subdir of root htdocs directory
if (! $res && file_exists("../../main.inc.php")) $res=@include '../../main.inc.php';		// to work if your module directory is into a subdir of root htdocs directory
if (! $res) die("Include of main fails");
include_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/client.class.php';
dol_include_once('/prospectingmap/lib/prospectingmap.lib.php');
dol_include_once('/advancedictionaries/class/html.formdictionary.class.php');
dol_include_once('/advancedictionaries/class/dictionary.class.php');

$langs->loadLangs(array("companies", "commercial", "customers", "suppliers", "bills", "compta", "categories", "prospectingmap@prospectingmap"));

// Security check
$socid = GETPOST('socid','int');
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user,'societe',$socid,'');

$search_sale    = GETPOST("search_sale",'array');
$search_stcomm  = GETPOST('search_stcomm','array');
$search_type    = GETPOST('search_type','array');
$search_state   = GETPOST("search_state",'array');
$search_categ_cus = GETPOST("search_categ_cus", 'int');
$search_categ_sup = GETPOST("search_categ_sup", 'int');

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('prospectmap'));

$object = new Societe($db);


/*
 * Actions
 */

if (GETPOST('cancel')) { $action='list'; $massaction=''; }
if (! GETPOST('confirmmassaction') && $massaction != 'presend' && $massaction != 'confirm_presend') { $massaction=''; }

$parameters=array();
$reshook=$hookmanager->executeHooks('doActions',$parameters, $object, $action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook)) {
    // Did we click on purge search criteria ?
    if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) // All tests are required to be compatible with all browsers
    {
        $search_sale = array();
        $search_stcomm = array();
        $search_type = array();
        $search_state = array();
        $search_categ_cus = 0;
        $search_categ_sup = 0;
    }
}

/*
 * View
 */

/*
 REM: Rules on permissions to see thirdparties
 Internal or External user + No permission to see customers => See nothing
 Internal user socid=0 + Permission to see ALL customers    => See all thirdparties
 Internal user socid=0 + No permission to see ALL customers => See only thirdparties linked to user that are sale representative
 External user socid=x + Permission to see ALL customers    => Can see only himself
 External user socid=x + No permission to see ALL customers => Can see only himself
 */

$form=new Form($db);
$formother=new FormOther($db);
$companystatic=new Societe($db);
$formcompany=new FormCompany($db);
$prospectstatic=new Client($db);
$prospectstatic->client=2;
$prospectstatic->loadCacheOfProspStatus();
$formdictionary = new FormDictionary($db);

$stcomm_dictionary = Dictionary::getDictionary($db, 'prospectingmap', 'prospectingmapstcomm');
$stcomm_dictionary->fetch_lines();

$title = $langs->trans("ProspectingMapMapOfProspects");

$sql = "SELECT s.rowid, s.nom as name, s.name_alias, s.town, s.zip, ";
$sql.= " st.libelle as stcomm, s.fk_stcomm as stcomm_id, s.fk_prospectlevel, s.prefix_comm, s.client, s.fournisseur, s.canvas, s.status as status,";
$sql.= " s.email, s.phone, s.fk_pays,";
$sql.= " s.address,";
$sql.= " state.code_departement as state_code, state.nom as state_name,";
$sql.= " pmc.longitude, pmc.latitude";
// Add fields from hooks
$parameters=array();
$reshook=$hookmanager->executeHooks('printFieldListSelect',$parameters);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;
$sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_country as country on (country.rowid = s.fk_pays)";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_departements as state on (state.rowid = s.fk_departement)";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."prospectingmap_coordinate as pmc on (pmc.fk_soc = s.rowid)";
if (!empty($search_state)) {
    $sql .= " ," . MAIN_DB_PREFIX . "c_prospectingmap_region as cpmr";
    $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "c_prospectingmap_region_cbl_department as cpmrcd on (cpmrcd.fk_line = cpmr.rowid)";
    $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "c_departements as cpmd on (cpmd.rowid = cpmrcd.fk_target)";
    $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "c_regions as cr on (cpmd.fk_region = cr.code_region)";
}
$sql.= " ,".MAIN_DB_PREFIX."c_stcomm as st";
// We'll need this table joined to the select in order to filter by sale
if ($search_sale || (!$user->rights->societe->client->voir && !$socid)) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
$sql.= " WHERE s.fk_stcomm = st.id";
$sql.= " AND s.entity IN (".getEntity('societe').")";
if ($search_sale || (!$user->rights->societe->client->voir && !$socid)) $sql .= " AND s.rowid = sc.fk_soc";        // Join for the needed table to filter by sale
if (!$user->rights->societe->client->voir && !$socid)	$sql.= " AND sc.fk_user = " .$user->id;
if (!empty($search_sale)) $sql .= " AND sc.fk_user IN (" . implode(',', $search_sale) . ")";
if (!empty($search_state)) {
    $sql .= 'AND cpmr.rowid IN (' . implode(',', $search_state) . ')';
    $sql .= 'AND (cpmrcd.fk_target = s.fk_departement OR (cr.fk_pays = s.fk_pays AND s.zip LIKE CONCAT(cpmd.code_departement, \'%\')))';
}
// Filter on type of thirdparty
if (!empty($search_type)) {
    $types_list = array();
    foreach ($search_type as $type) {
        $types_list = array_merge($types_list, explode(';', $type));
    }
    $or_stm = array();
    if (in_array('4', $types_list)) {
        $types_list = array_diff($types_list, array('4'));
        $or_stm[] = "s.fournisseur = 1";
    }
    if (in_array('0', $types_list)) {
        $types_list = array_diff($types_list, array('0'));
        $or_stm[] = "(s.client = 0 AND s.fournisseur = 0)";
    }
    if (count($types_list) > 0) $or_stm[] = "s.client IN (".implode(',', $types_list).")";
    $sql .= ' AND (' . implode(' OR ', $or_stm) . ')';
}
if (!empty($search_stcomm)) $sql .= " AND s.fk_stcomm IN (" . implode(',', $search_stcomm) . ")";
// Add where from hooks
$parameters=array();
$reshook=$hookmanager->executeHooks('printFieldListWhere',$parameters);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;

//categoriessearch
$searchCategoryCustomerList = $search_categ_cus ? array($search_categ_cus) : array();;
$searchCategoryCustomerOperator = 0;
// Search for tag/category ($searchCategoryCustomerList is an array of ID)
if (!empty($searchCategoryCustomerList)) {
    $searchCategoryCustomerSqlList = array();
    $listofcategoryid = '';
    foreach ($searchCategoryCustomerList as $searchCategoryCustomer) {
        if (intval($searchCategoryCustomer) == -2) {
            $searchCategoryCustomerSqlList[] = "NOT EXISTS (SELECT ck.fk_soc FROM ".MAIN_DB_PREFIX."categorie_societe as ck WHERE s.rowid = ck.fk_soc)";
        } elseif (intval($searchCategoryCustomer) > 0) {
            if ($searchCategoryCustomerOperator == 0) {
                $searchCategoryCustomerSqlList[] = " EXISTS (SELECT ck.fk_soc FROM ".MAIN_DB_PREFIX."categorie_societe as ck WHERE s.rowid = ck.fk_soc AND ck.fk_categorie = ".((int) $searchCategoryCustomer).")";
            } else {
                $listofcategoryid .= ($listofcategoryid ? ', ' : '') .((int) $searchCategoryCustomer);
            }
        }
    }
    if ($listofcategoryid) {
        $searchCategoryCustomerSqlList[] = " EXISTS (SELECT ck.fk_soc FROM ".MAIN_DB_PREFIX."categorie_societe as ck WHERE s.rowid = ck.fk_soc AND ck.fk_categorie IN (".$db->sanitize($listofcategoryid)."))";
    }
    if ($searchCategoryCustomerOperator == 1) {
        if (!empty($searchCategoryCustomerSqlList)) {
            $sql .= " AND (".implode(' OR ', $searchCategoryCustomerSqlList).")";
        }
    } else {
        if (!empty($searchCategoryCustomerSqlList)) {
            $sql .= " AND (".implode(' AND ', $searchCategoryCustomerSqlList).")";
        }
    }
}
$searchCategorySupplierList = $search_categ_sup ? array($search_categ_sup) : array();
$searchCategorySupplierOperator = 0;
// Search for tag/category ($searchCategorySupplierList is an array of ID)
if (!empty($searchCategorySupplierList)) {
    $searchCategorySupplierSqlList = array();
    $listofcategoryid = '';
    foreach ($searchCategorySupplierList as $searchCategorySupplier) {
        if (intval($searchCategorySupplier) == -2) {
            $searchCategorySupplierSqlList[] = "NOT EXISTS (SELECT ck.fk_soc FROM ".MAIN_DB_PREFIX."categorie_fournisseur as ck WHERE s.rowid = ck.fk_soc)";
        } elseif (intval($searchCategorySupplier) > 0) {
            if ($searchCategorySupplierOperator == 0) {
                $searchCategorySupplierSqlList[] = " EXISTS (SELECT ck.fk_soc FROM ".MAIN_DB_PREFIX."categorie_fournisseur as ck WHERE s.rowid = ck.fk_soc AND ck.fk_categorie = ".((int) $searchCategorySupplier).")";
            } else {
                $listofcategoryid .= ($listofcategoryid ? ', ' : '') .((int) $searchCategorySupplier);
            }
        }
    }
    if ($listofcategoryid) {
        $searchCategorySupplierSqlList[] = " EXISTS (SELECT ck.fk_soc FROM ".MAIN_DB_PREFIX."categorie_fournisseur as ck WHERE s.rowid = ck.fk_soc AND ck.fk_categorie IN (".$db->sanitize($listofcategoryid)."))";
    }
    if ($searchCategorySupplierOperator == 1) {
        if (!empty($searchCategorySupplierSqlList)) {
            $sql .= " AND (".implode(' OR ', $searchCategorySupplierSqlList).")";
        }
    } else {
        if (!empty($searchCategorySupplierSqlList)) {
            $sql .= " AND (".implode(' AND ', $searchCategorySupplierSqlList).")";
        }
    }
}
// categories end
$sql.= ' GROUP BY s.rowid, s.nom, s.name_alias, s.town, s.zip, ';
$sql.= " st.libelle, s.fk_stcomm, s.fk_prospectlevel, s.prefix_comm, s.client, s.fournisseur, s.canvas, s.status,";
$sql.= " s.email, s.phone, s.fk_pays,";
$sql.= " s.address,";
$sql.= " state.code_departement, state.nom,";
$sql.= " pmc.longitude, pmc.latitude";
// Add fields from hooks
$parameters=array();
$reshook=$hookmanager->executeHooks('printFieldListGroupBy',$parameters);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;
$sql.= $db->order('s.rowid', 'ASC');

$resql = $db->query($sql);
if (! $resql) {
    dol_print_error($db);
    exit;
}

$num = $db->num_rows($resql);

llxHeader('', $title);

print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" name="formfilter">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';

print_barre_liste($title, '', $_SERVER["PHP_SELF"], '', '', '', '', '', $num, 'title_companies');

$moreforfilter = '';

// Sales representatives
$moreforfilter.='<div class="divsearchfield">';
$moreforfilter.=$langs->trans('SalesRepresentatives'). ': ';
$moreforfilter.=multiselect_javascript_code($search_sale, 'search_sale');
$save_conf = $conf->use_javascript_ajax;
$conf->use_javascript_ajax = 0;
$moreforfilter.=$formother->select_salesrepresentatives('','search_sale',$user, 0, 0, 'minwidth300 maxwidth300');
$conf->use_javascript_ajax = $save_conf;
$moreforfilter.='</div>';

if (empty($type) || $type == 'c' || $type == 'p') {
    if (isModEnabled('categorie') && $user->hasRight("categorie", "lire")) {
        require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
        $moreforfilter .= '<div class="divsearchfield">';
        $tmptitle = $langs->trans('Categories');
        $moreforfilter .= img_picto($tmptitle, 'category', 'class="pictofixedwidth"');
        $moreforfilter .= $formother->select_categories('customer', $search_categ_cus, 'search_categ_cus', 1, $langs->trans('CustomersProspectsCategoriesShort'));
        $moreforfilter .= '</div>';
    }
}

if (empty($type) || $type == 'f') {
    if (isModEnabled("fournisseur") && isModEnabled('categorie') && $user->hasRight("categorie", "lire")) {
        require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
        $moreforfilter .= '<div class="divsearchfield">';
        $tmptitle = $langs->trans('Categories');
        $moreforfilter .= img_picto($tmptitle, 'category', 'class="pictofixedwidth"');
        $moreforfilter .= $formother->select_categories('supplier', $search_categ_sup, 'search_categ_sup', 1, $langs->trans('SuppliersCategoriesShort'));
        $moreforfilter .= '</div>';
    }
}
// States
$moreforfilter.='<div class="divsearchfield">';
$moreforfilter.=$langs->trans('ProspectingMapRegion'). ': ';
$moreforfilter.=multiselect_javascript_code($search_state, 'search_state');

if ($search_categ_cus > 0) {
    $param .= '&search_categ_cus='.urlencode($search_categ_cus);
}
if ($search_categ_sup > 0) {
    $param .= '&search_categ_sup='.urlencode($search_categ_sup);
}
$save_conf = $conf->use_javascript_ajax;
$conf->use_javascript_ajax = 0;
$moreforfilter.=$formdictionary->select_dictionary('prospectingmap', 'prospectingmapregion', '', 'search_state', '', 'rowid', '{{label}}', array(), array('label'=>'ASC'), 0, array(), 0, 0, 'minwidth300 maxwidth300');
$conf->use_javascript_ajax = $save_conf;
$moreforfilter.='</div>';

// Type company
$moreforfilter.='<div class="divsearchfield">';
$moreforfilter.=$langs->trans('Type'). ': ';
$moreforfilter.=multiselect_javascript_code($search_type, 'search_type');
$moreforfilter.='<select class="flat minwidth300 maxwidth300" id="search_type" name="search_type">';
if (empty($conf->global->SOCIETE_DISABLE_CUSTOMERS)) $moreforfilter.= '<option value="1;3">'.$langs->trans('Customer').'</option>';
if (empty($conf->global->SOCIETE_DISABLE_PROSPECTS)) $moreforfilter.= '<option value="2;3">'.$langs->trans('Prospect').'</option>';
$moreforfilter.='<option value="4">'.$langs->trans('Supplier').'</option>';
$moreforfilter.='<option value="0">'.$langs->trans('Others').'</option>';
$moreforfilter.='</select>';
$moreforfilter.='</div>';

// Prospect status
$arraystcomm=array();
foreach($prospectstatic->cacheprospectstatus as $key => $val) {
    $arraystcomm[$val['id']] = ($langs->trans("StatusProspect" . $val['id']) != "StatusProspect" . $val['id'] ? $langs->trans("StatusProspect" . $val['id']) : $val['label']);
}
$moreforfilter.='<div class="divsearchfield">';
$moreforfilter.=$langs->trans('StatusProsp'). ': ';
$moreforfilter.=multiselect_javascript_code($search_stcomm, 'search_stcomm');
$save_conf = $conf->use_javascript_ajax;
$conf->use_javascript_ajax = 0;
$moreforfilter.=$form->selectarray('search_stcomm', $arraystcomm, '', 0, 0, 0, '', 0, 0, 0, '','minwidth300 maxwidth300');
$conf->use_javascript_ajax = $save_conf;
$moreforfilter.='</div>';

$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters);    // Note that $action and $object may have been modified by hook
if (empty($reshook)) $moreforfilter .= $hookmanager->resPrint;
else $moreforfilter = $hookmanager->resPrint;

if (!empty($moreforfilter)) {
    print '<div class="liste_titre liste_titre_bydiv centpercent">';
    print $moreforfilter;
    print '<div class="divsearchfield">';
    print $form->showFilterButtons();
    print '</div>';
    print '</div>';
}

print '</form>';

$coordinates_metrics = empty($conf->global->PROSPECTINGMAP_COORDINATES_METRICS) ? 'EPSG:3857' : $conf->global->PROSPECTINGMAP_COORDINATES_METRICS;
/**
 * Build geoJSON datas.
 */
$icon = dol_buildpath('/prospectingmap/img/dot.png', 1);
$stcomm_list = [];
$features = [];
while ($obj = $db->fetch_object($resql)) {
	$stcomm_id = isset($obj->stcomm_id) ? $obj->stcomm_id : 0;

	$companystatic->id = $obj->rowid;
	$companystatic->name = $obj->name;
	$companystatic->name_alias = $obj->name_alias;
	$companystatic->address = $obj->address;
	$companystatic->town = $obj->town;
	$companystatic->zip = $obj->zip;
	$companystatic->state_name = $obj->state_name;
	$companystatic->fk_pays = $obj->fk_pays;
	$companystatic->phone = $obj->phone;

	// Description of the popup
	//------------------------------
	$address = $companystatic->getFullAddress();
	$description = $companystatic->getNomUrl(1, '', 0, 1) .
		(!empty($address) ? '<br>' . $address : '') .
		(!empty($companystatic->phone) ? '<br>' . dol_print_phone($companystatic->phone) : '') .
		(!empty($companystatic->email) ? '<br>' . dol_print_email($companystatic->email) : '');

	// Get Colors
	//------------------------------
	if (!isset($stcomm_list[$stcomm_id])) {
		$stcomm = $stcomm_dictionary->lines[$stcomm_id];
		$stcomm_list[$stcomm_id] = !empty($stcomm->fields['color']) ? $stcomm->fields['color'] : '#FFFFFF';
	}

	// Coordinates in projection format: "EPSG:3857"
	//------------------------------
	$longitude = !empty($obj->longitude) ? $obj->longitude : 0;
	$latitude = !empty($obj->latitude) ? $obj->latitude : 0;

	// Add geoJSON point
	//------------------------------
	$features[] = [
		'type' => 'Feature',
		'geometry' => [
			'type' => 'Point',
			'coordinates' => [$longitude, $latitude],
		],
		'properties' => [
			'desc' => $description,
			'stcomm' => $stcomm_id,
		],
	];
}

?>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/openlayers/openlayers.github.io@master/en/v6.15.1/css/ol.css" type="text/css">
	<script src="https://cdn.polyfill.io/v2/polyfill.min.js?features=requestAnimationFrame,Element.prototype.classList"></script>
	<script src="https://cdn.jsdelivr.net/gh/openlayers/openlayers.github.io@master/en/v6.15.1/build/ol.js"></script>
    <style>
        .ol-popup {
            position: absolute;
            background-color: white;
            -webkit-filter: drop-shadow(0 1px 4px rgba(0,0,0,0.2));
            filter: drop-shadow(0 1px 4px rgba(0,0,0,0.2));
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #cccccc;
            bottom: 12px;
            left: -50px;
            min-width: 280px;
        }
        .ol-popup:after, .ol-popup:before {
            top: 100%;
            border: solid transparent;
            content: " ";
            height: 0;
            width: 0;
            position: absolute;
            pointer-events: none;
        }
        .ol-popup:after {
            border-top-color: white;
            border-width: 10px;
            left: 48px;
            margin-left: -10px;
        }
        .ol-popup:before {
            border-top-color: #cccccc;
            border-width: 11px;
            left: 48px;
            margin-left: -11px;
        }
        .ol-popup-closer {
            text-decoration: none;
            position: absolute;
            top: 2px;
            right: 8px;
        }
        .ol-popup-closer:after {
            content: "✖";
        }
    </style>

    <div id="map" class="map"></div>
    <div id="popup" class="ol-popup">
        <a href="#" id="popup-closer" class="ol-popup-closer"></a>
        <div id="popup-content"></div>
    </div>

    <script type="text/javascript">
        /**
         * Set map height.
         */
        var _map = $('#map');
        var _map_pos = _map.position();
        var h = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);
        _map.height(h - _map_pos.top - 20);

        /**
         * Prospect markers geoJSON.
         */
        var geojsonProspectMarkers = {
			"type": "FeatureCollection",
			"crs": {
				"type": "name",
				"properties": {
					"name": "<?php print $coordinates_metrics ?>"
				}
			},
			"features": []
		};
        <?php
			$result = inject_map_features($features, 500);
			if ($result < 0) {
				setEventMessage($langs->trans('ProspectingMapErrorMapFeatureEncoding'), 'errors');
			}
		?>
		console.log("Prospecting Map: <?php print $coordinates_metrics ?>");
		console.log("Prospecting Map: " + geojsonProspectMarkers.features.length + " map features loaded.");

        /**
         * Prospect markers styles.
         */
        var markerStyles = {};
        $.map(<?php print json_encode($stcomm_list) ?>, function (value, key) {
            if (!(key in markerStyles)) {
                markerStyles[key] = new ol.style.Style({
                    image: new ol.style.Icon(/** @type {module:ol/style/Icon~Options} */ ({
                        anchor: [0.5, 1],
                        color: value,
                        crossOrigin: 'anonymous',
                        src: '<?php print $icon ?>'
                    }))
                });
            }
        });
        var styleFunction = function(feature) {
            return markerStyles[feature.get('stcomm')];
        };

        /**
         * Prospect markers source.
         */
        var prospectSource = new ol.source.Vector({
            features: (new ol.format.GeoJSON()).readFeatures(geojsonProspectMarkers)
        });

        /**
         * Prospect markers layer.
         */
        var prospectLayer = new ol.layer.Vector({
            source: prospectSource,
            style: styleFunction
        });

        /**
         * Open Street Map layer.
         */
        var osmLayer = new ol.layer.Tile({
            source: new ol.source.OSM()
        });

        /**
         * Elements that make up the popup.
         */
        var popupContainer = document.getElementById('popup');
        var popupContent = document.getElementById('popup-content');
        var popupCloser = document.getElementById('popup-closer');

        /**
         * Create an overlay to anchor the popup to the map.
         */
        var popupOverlay = new ol.Overlay({
            element: popupContainer,
            autoPan: true,
            autoPanAnimation: {
                duration: 250
            }
        });

        /**
         * Add a click handler to hide the popup.
         * @return {boolean} Don't follow the href.
         */
        popupCloser.onclick = function() {
            popupOverlay.setPosition(undefined);
            popupCloser.blur();
            return false;
        };

        /**
         * View of the map.
         */
        var mapView = new ol.View({
			projection: '<?php print $coordinates_metrics ?>'
		});
        if (<?php print $num ?> == 1) {
            var feature = prospectSource.getFeatures()[0];
            var coordinates = feature.getGeometry().getCoordinates();
            mapView.setCenter(coordinates);
            mapView.setZoom(17);
        } else {
            mapView.setCenter([0, 0]);
            mapView.setZoom(1);
        }

        /**
         * Create the map.
         */
        var map = new ol.Map({
            target: 'map',
            layers: [osmLayer, prospectLayer],
            overlays: [popupOverlay],
            view: mapView
        });

        /**
         * Fit map for markers.
         */
        if (<?php print $num ?> > 1) {
            var extent = prospectSource.getExtent();
            mapView.fit(extent, {padding: [50, 50, 50, 50], constrainResolution: false});
        }

        /**
         * Add a click handler to the map to render the popup.
         */
        map.on('singleclick', function(evt) {
            var feature = map.forEachFeatureAtPixel(evt.pixel, function (feature) {
                return feature;
            });

            if (feature) {
                var coordinates = feature.getGeometry().getCoordinates();
                popupContent.innerHTML = feature.get('desc');
                popupOverlay.setPosition(coordinates);
            } else {
                popupCloser.click();
            }
        });
    </script>
<?php

$db->free($resql);


llxFooter();
$db->close();
