<?php
require_once 'autoload.php';
use GeoIp2\Database\Reader;

class getGeoIpAddress{
	public function reader(){
		return  new Reader('GeoLite2-City.mmdb');
	}
}