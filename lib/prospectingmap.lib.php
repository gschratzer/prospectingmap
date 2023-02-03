<?php
/* Copyright (C) 2018      Open-DSI             <support@open-dsi.fr>
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
 *	\file       htdocs/prospectingmap/lib/prospectingmap.lib.php
 * 	\ingroup	prospectingmap
 *	\brief      Functions for the module prospectingmap
 */

/**
 * Prepare array with list of tabs
 *
 * @return  array				Array of tabs to show
 */
function prospectingmap_prepare_head()
{
    global $langs, $conf, $user;
    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/prospectingmap/admin/setup.php", 1);
    $head[$h][1] = $langs->trans("Parameters");
    $head[$h][2] = 'settings';
    $h++;

    $head[$h][0] = dol_buildpath("/prospectingmap/admin/dictionaries.php", 1);
    $head[$h][1] = $langs->trans("Dictionary");
    $head[$h][2] = 'dictionaries';
    $h++;

    $head[$h][0] = dol_buildpath("/prospectingmap/admin/about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    $head[$h][0] = dol_buildpath("/prospectingmap/admin/changelog.php", 1);
    $head[$h][1] = $langs->trans("OpenDsiChangeLog");
    $head[$h][2] = 'changelog';
    $h++;

    complete_head_from_modules($conf,$langs,null,$head,$h,'prospectingmap_admin');

    return $head;
}

/**
 *  Get map location for the specified company
 *
 * @param   DoliDb  $db         Database handler
 * @param   int     $socid      Company ID
 * @return  array               Coordinate of the company: array('lon' => 0, 'lat' => 0)
 */
function prospectingmap_get_map_location($db, $socid)
{
	$longitude = null;
	$latitude = null;
	if ($socid > 0) {
		$sql = "SELECT longitude, latitude FROM " . MAIN_DB_PREFIX . "prospectingmap_coordinate WHERE fk_soc = '" . $db->escape($socid) . "'";
		$resql = $db->query($sql);
		if ($resql) {
			if ($obj = $db->fetch_object($resql)) {
				$longitude = $obj->longitude;
				$latitude = $obj->latitude;
			}
		}
	}

	return array('lon' => $longitude, 'lat' => $latitude);
}

/**
 *  Get map location for the specified company
 *
 * @param   DoliDb  $db             Database handler
 * @param   int     $socid          Company ID
 * @param   int     $longitude      Longitude of the map location for the company
 * @param   int     $latitude       Latitude of the map location for the company
 * @param   string  $error_msg      Message of the error
 * @return  int                     >0 if ok, <0 if ko, 0 if none
 */
function prospectingmap_set_map_location($db, $socid, $longitude, $latitude, &$error_msg)
{
	if ($socid > 0) {
		$sql = "INSERT INTO " . MAIN_DB_PREFIX . "prospectingmap_coordinate(fk_soc, longitude, latitude)" .
			" VALUES ('" . $db->escape($socid) . "', '" . $db->escape($longitude) . "', '" . $db->escape($latitude) . "')" .
			" ON DUPLICATE KEY UPDATE longitude = '" . $db->escape($longitude) . "', latitude = '" . $db->escape($latitude) . "'";
		$resql = $db->query($sql);
		if (!$resql) {
			$error_msg = $db->lasterror();
			return -1;
		}

		return 1;
	}

	return 0;
}

/**
 *  Delete map location for the specified company
 *
 * @param   DoliDb  $db             Database handler
 * @param   int     $socid          Company ID
 * @param   string  $error_msg      Message of the error
 * @return  int                     >0 if ok, <0 if ko, 0 if none
 */
function prospectingmap_del_map_location($db, $socid, &$error_msg)
{
	if ($socid > 0) {
		$sql = "DELETE FROM " . MAIN_DB_PREFIX . "prospectingmap_coordinate WHERE fk_soc = '" . $db->escape($socid) . "'";
		$resql = $db->query($sql);
		if (!$resql) {
			$error_msg = $db->lasterror();
			return -1;
		}

		return 1;
	}

	return 0;
}

/**
 *  Delete map location for the specified company
 *
 * @param   DoliDb  $db             Database handler
 * @param   string  $old_metrics    Old metrics
 * @param   string  $new_metrics    New metrics
 * @param   string  $error_msg      Message of the error
 * @return  int                     >0 if ok, <0 if ko, 0 if none
 */
function prospectingmap_convert_all_coordinate($db, $old_metrics, $new_metrics, &$error_msg)
{
	if ($old_metrics != $new_metrics) {
		$error = 0;
		$db->begin();

		$sql = "SELECT fk_soc, longitude, latitude FROM " . MAIN_DB_PREFIX . "prospectingmap_coordinate";
		$resql = $db->query($sql);
		if ($resql) {
			dol_include_once('/prospectingmap/class/coordinateconverter.class.php', 'CoordinateConverter');
			while ($obj = $db->fetch_object($resql)) {
				if ($old_metrics == 'EPSG:3857') {
					$converted_coordinate = CoordinateConverter::convertEpsg3857To4326([$obj->longitude, $obj->latitude]);
				} else {
					$converted_coordinate = CoordinateConverter::convertEpsg4326To3857([$obj->longitude, $obj->latitude]);
				}

				$result = prospectingmap_set_map_location($db, $obj->fk_soc, $converted_coordinate[0], $converted_coordinate[1], $error_msg);
				if ($result < 0) {
					$error++;
					break;
				}
			}
			$db->free($resql);
		} else {
			$error_msg = $db->lasterror();
			$error++;
		}

		if ($error) {
			$db->rollback();
			return -1;
		} else {
			$db->commit();
		}
	}

	return 1;
}


/**
 *	Return multiselect javascript code
 *
 *  @param	array	$selected       Preselected values
 *  @param  string	$htmlname       Field name in form
 *  @param	string	$elemtype		Type of element we show ('category', ...)
 *  @return	string
 */
function multiselect_javascript_code($selected, $htmlname, $elemtype='', $width = '100%')
{
    global $conf;

    $out = '';

    // Add code for jquery to use multiselect
   	if (! empty($conf->global->MAIN_USE_JQUERY_MULTISELECT) || defined('REQUIRE_JQUERY_MULTISELECT'))
   	{
   		$tmpplugin=empty($conf->global->MAIN_USE_JQUERY_MULTISELECT)?constant('REQUIRE_JQUERY_MULTISELECT'):$conf->global->MAIN_USE_JQUERY_MULTISELECT;
  			$out.='<!-- JS CODE TO ENABLE '.$tmpplugin.' for id '.$htmlname.' -->
   			<script type="text/javascript">
    			function formatResult(record) {'."\n";
					if ($elemtype == 'category')
					{
						$out.='	//return \'<span><img src="'.DOL_URL_ROOT.'/theme/eldy/img/object_category.png'.'"> <a href="'.DOL_URL_ROOT.'/categories/viewcat.php?type=0&id=\'+record.id+\'">\'+record.text+\'</a></span>\';
							  	return \'<span><img src="'.DOL_URL_ROOT.'/theme/eldy/img/object_category.png'.'"> \'+record.text+\'</span>\';';
					}
					else
					{
						$out.='return record.text;';
					}
		$out.= '	};
   				function formatSelection(record) {'."\n";
					if ($elemtype == 'category')
					{
						$out.='	//return \'<span><img src="'.DOL_URL_ROOT.'/theme/eldy/img/object_category.png'.'"> <a href="'.DOL_URL_ROOT.'/categories/viewcat.php?type=0&id=\'+record.id+\'">\'+record.text+\'</a></span>\';
							  	return \'<span><img src="'.DOL_URL_ROOT.'/theme/eldy/img/object_category.png'.'"> \'+record.text+\'</span>\';';
					}
					else
					{
						$out.='return record.text;';
					}
		$out.= '	};
    			$(document).ready(function () {
    			    $(\'#'.$htmlname.'\').attr("name", "'.$htmlname.'[]");
    			    $(\'#'.$htmlname.'\').attr("multiple", "multiple");
    			    //$.map('.json_encode($selected).', function(val, i) {
    			        $(\'#'.$htmlname.'\').val('.json_encode($selected).');
    			    //});
    			
   					$(\'#'.$htmlname.'\').'.$tmpplugin.'({
   						dir: \'ltr\',
						// Specify format function for dropdown item
						formatResult: formatResult,
   					 	templateResult: formatResult,		/* For 4.0 */
						// Specify format function for selected item
						formatSelection: formatSelection,
   					 	templateResult: formatSelection,		/* For 4.0 */
   					 	width: \''.$width.'\'
   					});
   				});
   			</script>';
   	}

   	return $out;
}

function inject_map_features($features, $chunk_size, $deep = 0)
{
	$error = 0;

	if (!empty($features)) {
		$bulk_features = array_chunk($features, $chunk_size);
		$idx = 0;
		foreach ($bulk_features as $bulk) {
//			print "console.log('Prospecting Map: Deep $deep - Idx $idx - Chunk size $chunk_size.');\n";
			$encoded_bulk = json_encode($bulk);
			if (!empty($encoded_bulk)) {
//				print "console.log('Prospecting Map: Deep $deep - Idx $idx - " . count($bulk) . " map feature processed.');\n";
				print "geojsonProspectMarkers.features = $.merge(geojsonProspectMarkers.features, $encoded_bulk);\n";
			} else {
				if ($chunk_size > 1) {
					$result = inject_map_features($bulk, floor($chunk_size / 2), $deep + 1);
					if ($result < 0) $error++;
				} else {
					ob_start();
					print_r($bulk);
					$content = ob_get_contents();
					ob_clean();
					print "console.error('Prospecting Map: Error encode json map feature, data:', '" . dol_escape_js($content, 1) ."');\n";
					$error++;
				}
			}
			$idx++;
		}
	}

	return $error ? -1 : 0;
}