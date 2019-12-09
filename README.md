# php-rdp
PHP implementation of the Ramer-Douglas-Peucker algorithm for reducing the number of points on a polyline

## Install

~~~
composer install 
~~~

## Usage

~~~
$track = [
           [
             "point" => ["lat" => 11.11, "lon" => 12.12],
             "other_data" => [...]
           ],
           ....
         ];

$rdp = new \phpRdp\phpRdp("point.lat", "point.lon", 0.001, "km");

$simplified_track = $rdp->RamerDouglasPeucker($track);

~~~


## Authorship

Based on code: http://www.loughrigg.org/rdp/ by David R. Edgar
Developed by Alexander Krasilnikov <alexander@krasilnikov.spb.ru>


