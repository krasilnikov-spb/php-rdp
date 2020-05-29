<?php

/**
 * Based on code: http://www.loughrigg.org/rdp/ by David R. Edgar
 * Developed by Alexander Krasilnikov <alexander@krasilnikov.spb.ru>
 *
 * The author has placed this work in the Public Domain, thereby relinquishing all copyrights.
 * You may use, modify, republish, sell or give away this work without prior consent.
 * This implementation comes with no warranty or guarantee of fitness for any purpose.
 *
 * =========================================================================
 * An implementation of the Ramer-Douglas-Peucker algorithm for reducing
 * the number of points on a polyline
 * see http://en.wikipedia.org/wiki/Ramer%E2%80%93Douglas%E2%80%93Peucker_algorithm
 * =========================================================================
 */


namespace phpRdp;

use Exception;
use DateTime;

/**
 * Class phpRdp
 * @package phpRdp
 */
class phpRdp extends Exception
{
    private $lat_path = "";
    private $lon_path = "";
    private $epsilon = 0;
    private $earthRadius = 0;
    private $datetime_path = "";
    private $seconds_between_dots = false;

    /**
     * phpRdp constructor.
     * @param $lat_path - set path inside each value of object for latitude using dot as separator,
     *                    e.g. "point.lat"
     * @param $lon_path - set path inside each value of object for longitude using dot as separator,
     *                    e.g. "point.lat"
     * @param $epsilon - epsilon value, in km.
     * @param $epsilon_dimension - epsilon dimension "km" - kilometers, "mi" - miles, "m" - meters
     * @throws string -
     */

    function __construct($lat_path = "lat", $lon_path = "lon", $epsilon = 0.001, $epsilon_dimension = "km")
    {
        $this->lat_path = $lat_path;
        $this->lon_path = $lon_path;
        $this->epsilon = $epsilon;
        if ($epsilon_dimension == "km") {
            $this->earthRadius = 6371;
        } elseif ($epsilon_dimension == "mi") {
            $this->earthRadius = 3959;
        } elseif ($epsilon_dimension == "m") {
            $this->earthRadius = 6371000;
        } else {
            throw new Exception('Incorrect epsilon_dimension');
        }

    }

    /**
     * Set Datetime filter parameters
     * @param $path - set path inside each value of object for datetime using dot as separator,
     *                                e.g. "point.datetime"
     * @param $seconds_between_dots - filter value, in seconds.
     */

    function setDatetimeFilter($path, $seconds_between_dots)
    {
        $this->datetime_path = $path;
        $this->seconds_between_dots = $seconds_between_dots;
    }

    /**
     * RamerDouglasPeucker
     * Do initial prepare of data and call doRamerDouglasPeucker for simplification.
     *
     * @param $pointList - array of track. Each value of array represents one geopoint.
     * @return array - simplified track
     * @throws string - in case of absence or incorrect data in path
     */
    function RamerDouglasPeucker($pointList)
    {
        $epsilon = $this->epsilon;
        $pointList_short = [];
        foreach ($pointList as $key => $point) {
            list($lat, $lon) = self::convertLatLonToAbsKm(
                self::getCoordFromArrayValue($point, "lat"),
                self::getCoordFromArrayValue($point, "lon")
            );
            $pointList_short[$key] = ['lat' => $lat, 'lon' => $lon, 'key' => $key];
        }
        $pointList_short = self::doRamerDouglasPeucker($pointList_short, $epsilon);

        // response array
        $pointList_new = [];

        // initial state
        if ($this->seconds_between_dots > 0) {
            $last_datetime = new DateTimeEnhanced(self::getCoordFromArrayValue($pointList[0], "datetime"));
        }
        $last_id = 0;
        $pointList_new[] = $pointList[0];

        foreach ($pointList_short as $foo => $point) {
            if ($this->seconds_between_dots > 0) {
                $this_datetime = new DateTimeEnhanced(self::getCoordFromArrayValue($pointList[$point['key']], "datetime"));

                // if between previous dot and this one in simplified track
                // too many seconds
                // we need to insert some from initial track

                if ($last_datetime->returnModify("+" . $this->seconds_between_dots . ' seconds') < $this_datetime) {
                    for ($i = $last_id + 1; $i < $point['key']; $i++) {
                        $target_datetime = new DateTimeEnhanced(self::getCoordFromArrayValue($pointList[$i], "datetime"));
                        $target_next_datetime = new DateTimeEnhanced(self::getCoordFromArrayValue($pointList[($i + 1)], "datetime"));
                        $border_datetime = $last_datetime->returnModify("+" . $this->seconds_between_dots . ' seconds');

                        if (($target_datetime <= $border_datetime) &&
                            ($border_datetime < $target_next_datetime)) {
                            $last_datetime = clone $target_datetime;
                            $last_id = $i;
                            $pointList_new[] = $pointList[$i];
                        }
                    }
                }
            }

            // insert dot from simplified track
            // if previous dot not the same
            if ($this->seconds_between_dots > 0 &&
                self::getCoordFromArrayValue($pointList[$point['key']], "datetime") != self::getCoordFromArrayValue($pointList_new[count($pointList_new) - 1], "datetime")) {
                $last_id = $point['key'];
                $last_datetime = new DateTimeEnhanced(self::getCoordFromArrayValue($pointList[$point['key']], "datetime"));
                $pointList_new[] = $pointList[$point['key']];

            }elseif($this->seconds_between_dots == 0){
                $pointList_new[] = $pointList[$point['key']];

            }
        }

        return $pointList_new;
    }

    /**
     * Convert coordinate to coordinate system relative to gps point 0,0.
     * @param $lat - geographic coordinate, latitude
     * @param $lon - geographic coordinate, longitude
     * @return array - two variables with relative distances lat, lon in km
     */

    private function convertLatLonToAbsKm($lat, $lon)
    {
        $new_lat_in_km = self::haversineGreatCircleDistance(0, 0, $lat, 0);
        $new_lon_in_km = self::haversineGreatCircleDistance(0, 0, 0, $lon);
        return [$new_lat_in_km, $new_lon_in_km];
    }

    /**
     * Calculates the great-circle distance between two points, with
     * the Haversine formula.
     * @param float $latitudeFrom Latitude of start point in [deg decimal]
     * @param float $longitudeFrom Longitude of start point in [deg decimal]
     * @param float $latitudeTo Latitude of target point in [deg decimal]
     * @param float $longitudeTo Longitude of target point in [deg decimal]
     * @return float Distance between points in (same as earthRadius)
     */
    private function haversineGreatCircleDistance(
        $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo)
    {
        $earthRadius = $this->earthRadius;
        // convert from degrees to radians
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * $earthRadius;
    }

    /**
     * Extract coordinate value
     * @param $point - array, containing all data related to one geopoint
     * @param $coord - coordinate part: lat | lon
     * @return float - actual value
     * @throws string - in case of absence or incorrect data in path
     */
    private function getCoordFromArrayValue($point, $coord)
    {
        $path = $this->lon_path;
        if ($coord == "lat") {
            $path = $this->lat_path;
        } elseif ($coord == "datetime") {
            $path = $this->datetime_path;
        }
        $path_splitted = explode(".", $path);

        for ($i = 0; $i < count($path_splitted) - 1; $i++) {
            if (is_array($point[$path_splitted[$i]])) {
                $point = $point[$path_splitted[$i]];
            } else {
                throw new Exception('No ' . $coord . ' path in geopoint');
            }
        }
        if (array_key_exists($path_splitted[count($path_splitted) - 1], $point)) {
            return $point[$path_splitted[count($path_splitted) - 1]];
        } else {
            throw new Exception('Incorrect ' . $coord . ' path in geopoint');
        }
    }

    /**
     * doRamerDouglasPeucker
     * Reduces the number of points on a polyline by removing those that are closer to the line
     * than the distance $epsilon.
     * It is assumed that the coordinates and distance $epsilon are given in the same units.
     * The result is returned as an array in a similar format.
     * Each point returned in the result array will retain all its original data, including its E and N
     * values along with any others.
     *
     * @param $pointList - array of track. Each value of array represents one geopoint.
     * @param $epsilon - epsilon value in km.
     * @return array - simplified track
     * @throws string - in case of absence or incorrect data in path
     */
    private function doRamerDouglasPeucker($pointList, $epsilon)
    {
        // Find the point with the maximum distance
        $dmax = 0;
        $index = 0;
        $totalPoints = count($pointList);
        for ($i = 1; $i < ($totalPoints - 1); $i++) {

            $d = self::perpendicularDistance($pointList[$i]["lat"], $pointList[$i]["lon"],
                $pointList[0]["lat"], $pointList[0]["lon"],
                $pointList[$totalPoints - 1]["lat"], $pointList[$totalPoints - 1]["lon"]);

            if ($d > $dmax) {
                $index = $i;
                $dmax = $d;
            }
        }

        // If max distance is greater than epsilon, recursively simplify
        if ($dmax >= $epsilon) {
            // Recursive call
            $recResults1 = self::doRamerDouglasPeucker(array_slice($pointList, 0, $index + 1), $epsilon);
            $recResults2 = self::doRamerDouglasPeucker(array_slice($pointList, $index, $totalPoints - $index), $epsilon);

            // Build the result list
            $resultList = array_merge(array_slice($recResults1, 0, count($recResults1) - 1),
                array_slice($recResults2, 0, count($recResults2)));
        } else {
            $resultList = array($pointList[0], $pointList[$totalPoints - 1]);
        }

        // Return the result
        return $resultList;
    }

    /**
     * Calculates perpendicular distance from a point to a straight line.
     * All coordinates MUST be in the same dimensions, e.g. km.
     * @param $ptX - x coordinate for testing point
     * @param $ptY - y coordinate for testing point
     * @param $l1x - x coordinate for left point
     * @param $l1y - y coordinate for left point
     * @param $l2x - x coordinate for right point
     * @param $l2y - y coordinate for right point
     * @return float
     */
    private function perpendicularDistance($ptX, $ptY, $l1x, $l1y, $l2x, $l2y)
    {
        $result = 0;
        if ($l2x == $l1x) {
            //vertical lines - treat this case specially to avoid divide by zero
            $result = abs($ptX - $l2x);
        } else {
            $slope = (($l2y - $l1y) / ($l2x - $l1x));
            $passThroughY = (0 - $l1x) * $slope + $l1y;
            $result = (abs(($slope * $ptX) - $ptY + $passThroughY)) / (sqrt($slope * $slope + 1));
        }
        return $result;
    }
}



class DateTimeEnhanced extends DateTime
{

    /**
     * Create a copy of DateTimeEnhanced class instance and modify the datetime
     * according to passed interval
     * @param $interval -
     * @return DateTimeEnhanced
     */
    public function returnModify($interval)
    {
        $dt = clone $this;
        $dt->modify($interval);
        return $dt;
    }
}
