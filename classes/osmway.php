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

	class OSMWay extends OSMObject
	{
		function getMembers($create_objects=false)
		{
			if($create_objects)
				OSMObject::downloadFull("way", $this->getDOM()->getAttribute("id"));

			$return = array();
			$members = $this->getDOM()->getElementsByTagName("nd");
			for($i=0; $i<$members->length; $i++)
			{
				$member = $members->item($i);
				$return[] = array(
					"type" => "node",
					"ref" => $member->getAttribute("ref"),
					"object" => $create_objects ? OSMObject::fetch("node", $member->getAttribute("ref")) : null
				);
			}
			return $return;
		}

		function getRoundaboutCentre()
		{
			$members = $this->getMembers(true);

			$first_member = $members[0]["object"]->getDOM();
			$last_member = $members[count($members)-1]["object"]->getDOM();

			if($first_member->getAttribute("lat") != $last_member->getAttribute("lat") || $first_member->getAttribute("lon") != $last_member->getAttribute("lon"))
				return false;

			array_pop($members);

			$lat_sum = 0;
			$lon_sum = 0;
			foreach($members as $member)
			{
				$lat_sum += $member["object"]->getDOM()->getAttribute("lat");
				$lon_sum += $member["object"]->getDOM()->getAttribute("lon");
			}
			return array($lat_sum/count($members), $lon_sum/count($members));
		}
	}