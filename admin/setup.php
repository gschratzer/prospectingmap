<?php
/* Copyright (C) 2007-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2018      Open-DSI             <support@open-dsi.fr>
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
 *	    \file       htdocs/prospectingmap/admin/setup.php
 *		\ingroup    prospectingmap
 *		\brief      Page to setup prospectingmap module
 */

// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include '../../main.inc.php';			// to work if your module directory is into a subdir of root htdocs directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include '../../../main.inc.php';		// to work if your module directory is into a subdir of root htdocs directory
if (! $res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/prospectingmap/lib/prospectingmap.lib.php');

$langs->load("admin");
$langs->load("prospectingmap@prospectingmap");
$langs->load("opendsi@prospectingmap");

if (!$user->admin) accessforbidden();

$action = GETPOST('action','alpha');


/*
 *	Actions
 */

if ($action == 'set_map_settings') {
    $main_layer_map = GETPOST('PROSPECTINGMAP_MAIN_LAYER_MAP', 'alpha');

    $main_layer_map = empty($main_layer_map) ? 'OSM' : $main_layer_map;

    dolibarr_set_const($db, 'PROSPECTINGMAP_MAIN_LAYER_MAP', $main_layer_map, 'chaine', 0, '', $conf->entity);
}
elseif ($action == 'set_search_coordinates_settings') {
    $url_address = GETPOST('PROSPECTINGMAP_SEARCH_COORDINATES_URL_ADDRESS', 'alpha');
    $url_parameters = GETPOST('PROSPECTINGMAP_SEARCH_COORDINATES_URL_PARAMETERS', 'alpha');
    $request_mode = GETPOST('PROSPECTINGMAP_SEARCH_COORDINATES_REQUEST_MODE', 'alpha');
	$coordinates_metrics = GETPOST('PROSPECTINGMAP_COORDINATES_METRICS', 'alpha');

    $url_address = empty($url_address) ? 'https://nominatim.openstreetmap.org/search' : $url_address;
    $url_parameters = empty($url_parameters) ? 'polygon_geojson=1&format=json&q=__ADDRESS__' : $url_parameters;
    $request_mode = empty($request_mode) ? 'GET' : $request_mode;
	$coordinates_metrics = empty($coordinates_metrics) ? 'EPSG:3857' : $coordinates_metrics;

    dolibarr_set_const($db, 'PROSPECTINGMAP_SEARCH_COORDINATES_URL_ADDRESS', $url_address, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'PROSPECTINGMAP_SEARCH_COORDINATES_URL_PARAMETERS', $url_parameters, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'PROSPECTINGMAP_SEARCH_COORDINATES_REQUEST_MODE', $request_mode, 'chaine', 0, '', $conf->entity);
	if (prospectingmap_convert_all_coordinate($db, $conf->global->PROSPECTINGMAP_COORDINATES_METRICS, $coordinates_metrics, $error_msg) > 0) {
		dolibarr_set_const($db, 'PROSPECTINGMAP_COORDINATES_METRICS', $coordinates_metrics, 'chaine', 0, '', $conf->entity);
	} else {
		setEventMessage($error_msg, 'errors');
	}
}
elseif (preg_match('/set_(.*)/',$action,$reg)) {
    $code = $reg[1];
    $value = (GETPOST($code) ? GETPOST($code) : 1);
    if (dolibarr_set_const($db, $code, $value, 'chaine', 0, '', $conf->entity) > 0) {
        Header("Location: " . $_SERVER["PHP_SELF"]);
        exit;
    } else {
        dol_print_error($db);
    }
}
elseif (preg_match('/del_(.*)/',$action,$reg)) {
    $code = $reg[1];
    if (dolibarr_del_const($db, $code, $conf->entity) > 0) {
        Header("Location: " . $_SERVER["PHP_SELF"]);
        exit;
    } else {
        dol_print_error($db);
    }
}


/*
 *	View
 */

$form = new Form($db);

llxHeader();

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("ProspectingMapSetup"),$linkback,'title_setup');
print "<br>\n";

$head=prospectingmap_prepare_head();

dol_fiche_head($head, 'settings', $langs->trans("Module163021Name"), 0, 'opendsi@prospectingmap');

print '<br>';


/**
 * Map settings.
 */
print load_fiche_titre($langs->trans("ProspectingMapSettings"),'','');
/*
print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_map_settings">';
*/
$var=true;
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameters").'</td>'."\n";
print '<td align="right">'.$langs->trans("Value").'</td>'."\n";
print "</tr>\n";

// PROSPECTINGMAP_SHOW_MAP_INTO_COMPANY_CARD
$var = !$var;
print '<tr ' . $bc[$var] . '>' . "\n";
print '<td>' . $langs->trans("ProspectingMapShowMapIntoCompanyCard") . '</td>' . "\n";
print '<td align="right">' . "\n";
if (!empty($conf->use_javascript_ajax)) {
    print ajax_constantonoff('PROSPECTINGMAP_SHOW_MAP_INTO_COMPANY_CARD');
} else {
    if (empty($conf->global->PROSPECTINGMAP_SHOW_MAP_INTO_COMPANY_CARD)) {
        print '<a href="' . $_SERVER['PHP_SELF'] . '?action=set_PROSPECTINGMAP_SHOW_MAP_INTO_COMPANY_CARD">' . img_picto($langs->trans("Disabled"), 'switch_off') . '</a>';
    } else {
        print '<a href="' . $_SERVER['PHP_SELF'] . '?action=del_PROSPECTINGMAP_SHOW_MAP_INTO_COMPANY_CARD">' . img_picto($langs->trans("Enabled"), 'switch_on') . '</a>';
    }
}
print '</td></tr>' . "\n";
/*
// PROSPECTINGMAP_MAIN_LAYER_MAP
print '<tr class="oddeven">'."\n";
print '<td>'.$langs->trans("ProspectingMapMainLayerMap").'</td>'."\n";
print '<td class="nowrap">'."\n";
$map_choices = array(
    'OSM' => 'OpenStreetMap',
);
print $form->selectarray("PROSPECTINGMAP_MAIN_LAYER_MAP", $map_choices, $conf->global->PROSPECTINGMAP_MAIN_LAYER_MAP);
print '</td></tr>'."\n";
*/
print '</table>';
/*
print '<br>';
print '<div align="center">';
print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';
*/

/**
 * Search coordinates settings.
 */
print load_fiche_titre($langs->trans("ProspectingMapSearchCoordinatesSettings"),'','');

print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_search_coordinates_settings">';

$var=true;
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameters").'</td>'."\n";
print '<td width="30%">'.$langs->trans("Value").'</td>'."\n";
print "</tr>\n";

// PROSPECTINGMAP_SEARCH_COORDINATES_URL_ADDRESS
print '<tr class="oddeven">'."\n";
print '<td>'.$langs->trans("ProspectingMapSearchCoordinatesUrlAddress").'</td>'."\n";
print '<td class="nowrap">'."\n";
print '<input type="text" name="PROSPECTINGMAP_SEARCH_COORDINATES_URL_ADDRESS" size="50" value="'.dol_escape_htmltag($conf->global->PROSPECTINGMAP_SEARCH_COORDINATES_URL_ADDRESS).'" />'."\n";
print '</td></tr>'."\n";

// PROSPECTINGMAP_SEARCH_COORDINATES_URL_PARAMETERS
print '<tr class="oddeven">'."\n";
print '<td>'.$langs->trans("ProspectingMapSearchCoordinatesUrlParameters").'</td>'."\n";
print '<td class="nowrap">'."\n";
print '<input type="text" name="PROSPECTINGMAP_SEARCH_COORDINATES_URL_PARAMETERS" size="50" value="'.dol_escape_htmltag($conf->global->PROSPECTINGMAP_SEARCH_COORDINATES_URL_PARAMETERS).'" />'."\n";
print '</td></tr>'."\n";

// PROSPECTINGMAP_SEARCH_COORDINATES_REQUEST_MODE
print '<tr class="oddeven">'."\n";
print '<td>'.$langs->trans("ProspectingMapSearchCoordinatesRequestMode").'</td>'."\n";
print '<td class="nowrap">'."\n";
print $form->selectarray("PROSPECTINGMAP_SEARCH_COORDINATES_REQUEST_MODE", array('GET' => 'GET', 'POST' => 'POST'), $conf->global->PROSPECTINGMAP_SEARCH_COORDINATES_REQUEST_MODE);
print '</td></tr>'."\n";

// PROSPECTINGMAP_COORDINATES_METRICS
print '<tr class="oddeven">'."\n";
print '<td>'.$langs->trans("ProspectingMapCoordinatesMetrics").'</td>'."\n";
print '<td class="nowrap">'."\n";
print $form->selectarray("PROSPECTINGMAP_COORDINATES_METRICS", array('EPSG:3857' => 'Web Mercator (EPSG:3857)', 'EPSG:4326' => 'WGS 84 (EPSG:4326)'), $conf->global->PROSPECTINGMAP_COORDINATES_METRICS);
print '</td></tr>'."\n";

print '</table>';

print '<br>';
print '<div align="center">';
print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

dol_fiche_end();

llxFooter();

$db->close();
