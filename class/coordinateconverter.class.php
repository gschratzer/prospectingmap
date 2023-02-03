<?php
/* Copyright (C) 2022      Open-DSI             <support@open-dsi.fr>
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
 *
 * Adapted from code of Armand on https://gis.stackexchange.com/questions/120636/math-formula-for-transforming-from-epsg4326-to-epsg3857
 */

class CoordinateConverter
{
	private static $a = 6378137.0;

	/**
	 * Convert Web Mercator (EPSG:3857) to WGS 84 (EPSG:4326)
	 *
	 * @param	array	$epsg3857Coordinate		Coordinate (X, Y)
	 * @return	array							Converted coordinate (Longitude, Latitude)
	 */
    public static function convertEpsg3857To4326($epsg3857Coordinate)
	{
        // D = -N / a
        // φ = π / 2 – 2 atan(e ^ D)
        // λ = E / a

        $d = -$epsg3857Coordinate[1] / self::$a;
        $phi = pi() / 2 - 2 * atan(exp($d));
        $lambda = $epsg3857Coordinate[0] / self::$a;
        $lat = $phi / pi() * 180;
        $lon = $lambda / pi() * 180;

        return [$lon, $lat];
    }

	/**
	 * Convert WGS 84 (EPSG:4326) to Web Mercator (EPSG:3857)
	 *
	 * @param	array	$epsg4326Coordinate		Coordinate (Longitude, Latitude)
	 * @return	array							Converted coordinate (X, Y)
	 */
	public static function convertEpsg4326To3857($epsg4326Coordinate)
	{
		// E = x = a * λ
		// N = y = a * ln[tan(π/4 + φ/2)]

		$lambda = $epsg4326Coordinate[0] / 180 * pi();
		$phi = $epsg4326Coordinate[1] / 180 * pi();
		$x = self::$a * $lambda;
		$y = self::$a * log(tan(pi() / 4 + $phi / 2));

		return [$x, $y];
	}
}
