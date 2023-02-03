<?php
/*  Copyright (C) 2018      Open-DSI             <support@open-dsi.fr>
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
 *	\file       htdocs/prospectingmap/core/triggers/interface_99_modProspectingMap_ProspectingMap.class.php
 *  \ingroup    prospectingmap
 *	\brief      File of class of triggers for prospectingmap module
 */


require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';


/**
 *  Class of triggers for prospectingmap module
 */
class InterfaceProspectingMap extends DolibarrTriggers
{
	public $family = 'prospectingmap';
	public $description = "Triggers of this module catch triggers event for the ProspectingMap module.";
	public $version = self::VERSION_DOLIBARR;
	public $picto = 'prospectingmap@prospectingmap';

	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "runTrigger" are triggered if file is inside directory htdocs/core/triggers or htdocs/module/code/triggers (and declared)
	 *
	 * @param string		$action		Event action code
	 * @param Object		$object     Object
	 * @param User		    $user       Object user
	 * @param Translate 	$langs      Object langs
	 * @param conf		    $conf       Object conf
	 * @return int         				<0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        switch ($action) {
            case 'COMPANY_CREATE':
            case 'COMPANY_MODIFY':
                if ((isset($_GET['map_longitude']) || isset($_POST['map_longitude'])) ||
                    (isset($_GET['map_latitude']) || isset($_POST['map_latitude']))) {
                    $longitude = GETPOST('map_longitude', 'int');
                    $latitude = GETPOST('map_latitude', 'int');

                    dol_include_once('/prospectingmap/lib/prospectingmap.lib.php');
                    if ($longitude === '' && $latitude === '') {
                        $res = prospectingmap_del_map_location($this->db, $object->id, $error_msg);
                    } else {
                        $res = prospectingmap_set_map_location($this->db, $object->id, $longitude, $latitude, $error_msg);
                    }
                    if ($res < 0) {
                        $this->errors[] = $langs->trans('Module163021Name') . ' : ' . $error_msg;
                        return -1;
                    }
                }
                break;

            case 'COMPANY_DELETE':
                dol_include_once('/prospectingmap/lib/prospectingmap.lib.php');
                $res = prospectingmap_del_map_location($this->db, $object->id, $error_msg);
                if ($res < 0) {
                    $this->errors[] = $langs->trans('Module163021Name') . ' : ' . $error_msg;
                    return -1;
                }
                break;
        }

        return 0;
    }
}