<?php
/*
    This file is part of OSM Route Manager.

    OSM Route Manager is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    OSM Route Manager is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with OSM Route Manager.  If not, see <http://www.gnu.org/licenses/>.
*/

	require_once(dirname(__FILE__)."/osmobject.php");
	require_once(dirname(__FILE__)."/osmapierror.php");

	class OSMAPI
	{
		const API_SERVER = "www.openstreetmap.org";
		const API_PORT = 80;
		const API_PREFIX = "/api/0.6";

		/**
		 * @return OSMObject[]
		*/

		static public function get($url, $api_server=null, $api_port=null, $api_prefix=null)
		{
			if(!isset($api_server))
				$api_server = self::API_SERVER;
			if(!isset($api_port))
				$api_port = self::API_PORT;
			if(!isset($api_prefix))
				$api_prefix = self::API_PREFIX;

			$fh = fsockopen($api_server, $api_port);
			if(!$fh)
				throw new IOException(_("Could not connect to the API server."));
			//echo "GET ".static::API_PREFIX.$url."<br />\n";
			$api_call = "GET ".$api_prefix.$url." HTTP/1.0";
			fwrite($fh, $api_call."\r\n");
			fwrite($fh, "Host: ".$api_server."\r\n");
			fwrite($fh, "Connection: close\r\n");
			fwrite($fh, "\r\n");

			list(,$status) = explode(" ", fgets($fh), 3);
			if($status != "200")
				throw new OSMApiError($status, $api_call);

			while(!feof($fh))
			{
				if(fgets($fh) == "\r\n")
					break;
			}

			$xml = "";
			while(!feof($fh))
				$xml .= fread($fh, 8192);

			$return = array();

			$dom = new DOMDocument();
			$dom->loadXML($xml);

			$root = $dom->firstChild;
			if($root)
			{
				for($it = $root->firstChild; isset($it); $it = $it->nextSibling)
				{
					if($it instanceof DOMElement)
						OSMObject::cache($return[] = OSMObject::cast($it));
				}
			}
			else
				throw new IOException(_("The OSM server sent no data."));

			return $return;
		}
	}
