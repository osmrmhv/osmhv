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
	require_once(dirname(__FILE__)."/sqlite.php");

	class OSMRelation extends OSMObject
	{
		private static $sortSegmentsCallbackReference = null;

		function getMembers($create_objects=false)
		{
			if($create_objects)
				OSMObject::downloadFull("relation", $this->getDOM()->getAttribute("id"));

			$return = array();
			$members = $this->getDOM()->getElementsByTagName("member");
			for($i=0; $i<$members->length; $i++)
			{
				$member = $members->item($i);
				$return[] = array(
					"type" => $member->getAttribute("type"),
					"ref" => $member->getAttribute("ref"),
					"role" => $member->getAttribute("role"),
					"object" => $create_objects ? OSMObject::fetch($member->getAttribute("type"), $member->getAttribute("ref")) : null
				);
			}
			return $return;
		}

		function getRecursiveWays(&$ignore_relations = null)
		{
			$members = array();
			if(!isset($ignore_relations))
				$ignore_relations = array();
			$ignore_relations[] = $this->getDOM()->getAttribute("id");

			foreach($this->getMembers(true) as $member)
			{
				if($member["type"] == "relation")
				{
					if(!in_array($member["ref"], $ignore_relations))
						$members += $member["object"]->getRecursiveWays(&$ignore_relations);
				}
				elseif($member["type"] == "way")
					$members[$member["ref"]] = $member["object"];
			}
			return $members;
		}

		static function downloadRecursive($id)
		{
			OSMObject::downloadFull("relation", $id);
			foreach(OSMObject::fetch("relation", $id)->getMembers() as $member)
			{
				if($member["type"] == "relation")
					self::downloadRecursive($member["ref"]);
			}
		}

		static function segmentate($this_id, $force_update=false)
		{
			$sql = SQLite::getConnection();
			/*$sql->query("CREATE TABLE IF NOT EXISTS relation_updated ( relation INT, updated INT, timestamp TEXT );");
			$sql->query("CREATE TABLE IF NOT EXISTS relation_segments ( relation INT, segment INT, i INT, lat REAL, lon REAL );");*/

			if(!$force_update)
			{
				try
				{
					$force_update = true;
					$update = $sql->query("SELECT updated,timestamp FROM relation_updated WHERE relation = ".$sql->quote($this_id)." LIMIT 1;")->fetch(PDO::FETCH_ASSOC);
					if($update && time()-$update["updated"] < 86400)
					{
						$relation = OSMObject::fetch("relation", $this_id);
						if($relation->getDOM()->getAttribute("timestamp") == $update["timestamp"])
							$force_update = false;
					}
				}
				catch(PDOException $e)
				{
				}
			}


			if($force_update)
				exec("java/osmrm --relation=".escapeshellarg($this_id)." --cache=cache.sqlite3");

			try
			{
				$update = $sql->query("SELECT updated,timestamp FROM relation_updated WHERE relation = ".$sql->quote($this_id)." LIMIT 1;")->fetch(PDO::FETCH_ASSOC);
				if(!$update)
					return null;
				$return = array();
				$query = $sql->query("SELECT segment, i, lat, lon FROM relation_segments WHERE relation = ".$sql->quote($this_id)." ORDER BY segment ASC, i ASC;");
				while($row = $query->fetch(PDO::FETCH_ASSOC))
				{
					if(!isset($return[$row["segment"]]))
						$return[$row["segment"]] = array();
					$return[$row["segment"]][$row["i"]] = array($row["lat"], $row["lon"]);
				}
				return array($update["updated"], $return);
			}
			catch(PDOException $e)
			{
				return null;
			}

			/*$last_update = time();

			self::downloadRecursive($this_id);
			$relation = OSMObject::fetch("relation", $this_id);

			$ways = $relation->getRecursiveWays();

			// Make roundabout resolution table
			$roundabout_points = array();
			foreach($ways as $i=>$way)
			{
				$members = $way->getMembers(true);
				$roundabout_centre = $way->getRoundaboutCentre();
				if($roundabout_centre)
				{
					foreach($members as $member)
						$roundabout_points[$member["object"]->getDOM()->getAttribute("lat")."|".$member["object"]->getDOM()->getAttribute("lon")] = $roundabout_centre;
					unset($ways[$i]);
				}
			}

			// Get ways with first and last node
			$ways_ends = array();
			foreach($ways as $way)
			{
				$members = $way->getMembers(true);
				if(count($members) < 2)
					continue;

				$first_node = $members[0]["object"];
				$last_node = $members[count($members)-1]["object"];
				$ways_ends_new = array(
					$way,
					array($first_node->getDOM()->getAttribute("lat"), $first_node->getDOM()->getAttribute("lon")),
					array($last_node->getDOM()->getAttribute("lat"), $last_node->getDOM()->getAttribute("lon")),
					array(),
					array()
				);
				if(isset($roundabout_points[implode("|", $ways_ends_new[1])]))
					$ways_ends_new[1] = $roundabout_points[implode("|", $ways_ends_new[1])];
				if(isset($roundabout_points[implode("|", $ways_ends_new[2])]))
					$ways_ends_new[2] = $roundabout_points[implode("|", $ways_ends_new[2])];
				$ways_ends[] = $ways_ends_new;
			}

			unset($ways);

			// Look which ways are connected
			$ends = array();
			$ways_ends_count = count($ways_ends);
			for($k1=0; $k1<$ways_ends_count; $k1++)
			{
				$way1 = &$ways_ends[$k1];
				for($k2=$k1+1; $k2<$ways_ends_count; $k2++)
				{
					$way2 = &$ways_ends[$k2];

					if($way1[1] == $way2[1])
					{
						$way1[3][] = $k2;
						$way2[3][] = $k1;
					}
					elseif($way1[2] == $way2[1])
					{
						$way1[4][] = $k2;
						$way2[3][] = $k1;
					}
					elseif($way1[1] == $way2[2])
					{
						$way1[3][] = $k2;
						$way2[4][] = $k1;
					}
					elseif($way1[2] == $way2[2])
					{
						$way1[4][] = $k2;
						$way2[4][] = $k1;
					}
				}
				if(count($way1[3]) != 1 || count($way1[4]) != 1)
					$ends[] = $k1;
			}

			// Connect the ways and create segments
			$segments = array();
			$ends_count = count($ends);
			for($i=0; $i<$ends_count; $i++)
			{
				if(!isset($ends[$i]))
					continue;
				$segment = &$segments[];
				$segment = array();

				$segment[] = $way = $ends[$i];
				$index = count($ways_ends[$way][3]) == 1 ? 3 : 4;
				$ways_ends[$way][5] = ($index == 3); // Reverse way?
				while(count($ways_ends[$way][$index]) == 1)
				{
					$old_way = $way;
					$way = $ways_ends[$way][$index][0];
					$segment[] = $way;
					$index = ((isset($ways_ends[$way][3][0]) && $ways_ends[$way][3][0] == $old_way) ? 4 : 3);
					$ways_ends[$way][5] = ($index == 3); // Reverse way?
				}

				$unset_index = array_search($way, $ends);
				if($unset_index !== false)
					unset($ends[$unset_index]);
			}
			unset($ends);

			// Resolve segments into nodes, calculate distance
			$segments_nodes = array();
			foreach($segments as $k=>$v)
			{
				$segments_nodes[$k] = array();
				foreach($v as $k2=>$way)
				{
					$way_members = $ways_ends[$way][0]->getMembers(true);
					if($ways_ends[$way][5])
						$way_members = array_reverse($way_members);
					$first_node = $way_members[0]["object"]->getDOM()->getAttribute("lat")."|".$way_members[0]["object"]->getDOM()->getAttribute("lon");
					$last_node = $way_members[count($way_members)-1]["object"]->getDOM()->getAttribute("lat")."|".$way_members[count($way_members)-1]["object"]->getDOM()->getAttribute("lon");

					if($k2 == 0 && isset($roundabout_points[$first_node]))
						$segments_nodes[$k][] = $roundabout_points[$first_node];
					for($i=($k2 == 0 || isset($roundabout_points[$first_node]) ? 0 : 1); $i<count($way_members); $i++)
						$segments_nodes[$k][] = array($way_members[$i]["object"]->getDOM()->getAttribute("lat"), $way_members[$i]["object"]->getDOM()->getAttribute("lon"));
					if(isset($roundabout_points[$last_node]))
						$segments_nodes[$k][] = $roundabout_points[$last_node];
				}
			}
			unset($segments);
			unset($roundabout_points);

			// Sort segments
			// First, find the two ends with the greatest distance
			$ends = array();
			for($i=0; $i<count($segments_nodes); $i++)
			{
				$end1 = true;
				$end2 = true;
				for($j=0; $j<count($segments_nodes); $j++)
				{
					if($i == $j) continue;

					if($segments_nodes[$i][0] == $segments_nodes[$j][0] || $segments_nodes[$i][0] == $segments_nodes[$j][count($segments_nodes[$j])-1])
						$end1 = false;
					if($segments_nodes[$i][count($segments_nodes[$i])-1] == $segments_nodes[$j][0] || $segments_nodes[$i][count($segments_nodes[$i])-1] == $segments_nodes[$j][count($segments_nodes[$j])-1])
						$end2 = false;
					if(!$end1 && !$end2)
						break;
				}
				if($end1)
					$ends[] = array($i, true, $segments_nodes[$i][0]);
				if($end2)
					$ends[] = array($i, false, $segments_nodes[$i][count($segments_nodes[$i])-1]);
			}

			$greatest = array(null, 0);
			for($i=0; $i<count($ends); $i++)
			{
				for($j=$i+1; $j<count($ends); $j++)
				{
					$distance = getDistance($ends[$i][2], $ends[$j][2]);
					if($distance > $greatest[1])
						$greatest = array(array($ends[$i], $ends[$j]), $distance);
				}
			}

			if($greatest[0])
			{
				$distance_x = abs($greatest[0][0][0]-$greatest[0][1][2][0]);
				$distance_y = abs($greatest[0][0][1]-$greatest[0][1][2][1]);
				if($distance_y > $distance_x)
					$first = ($greatest[0][0][1] > $greatest[0][1][2][1] ? 0 : 1);
				else
					$first = ($greatest[0][0][0] > $greatest[0][1][2][0] ? 0 : 1);

				self::$sortSegmentsCallbackReference = $greatest[0][$first][2];
				usort($segments_nodes, array("OSMRelation", "sortSegmentsCallback"));
			}

			// Save calculated data to the database

			$this_id_quote = $sql->quote($this_id);
			$sql->beginTransaction();
			$sql->query("DELETE FROM relation_updated WHERE relation = ".$this_id_quote.";");
			$sql->query("INSERT INTO relation_updated ( relation, updated, timestamp ) VALUES ( ".$this_id_quote.", ".$sql->quote($last_update).", ".$sql->quote($relation->getDOM()->getAttribute("timestamp"))." );");
			$sql->query("DELETE FROM relation_segments WHERE relation = ".$this_id_quote.";");
			foreach($segments_nodes as $i=>$segment)
			{
				$segment_quote = $sql->quote($i);
				foreach($segment as $j=>$node)
					$sql->query("INSERT INTO relation_segments ( relation, segment, i, lat, lon ) VALUES ( ".$this_id_quote.", ".$segment_quote.", ".$sql->quote($j).", ".$sql->quote($node[0]).", ".$sql->quote($node[1])." );");
			}
			$sql->commit();

			return array($last_update, $segments_nodes);*/
		}

		private static function sortSegmentsCallback($a, $b)
		{
			$distance1 = min(getDistance(self::$sortSegmentsCallbackReference, $a[0]), getDistance(self::$sortSegmentsCallbackReference, $a[count($a)-1]));
			$distance2 = min(getDistance(self::$sortSegmentsCallbackReference, $b[0]), getDistance(self::$sortSegmentsCallbackReference, $b[count($b)-1]));
			if($distance1 < $distance2) return -1;
			elseif($distance1 > $distance2) return 1;
			else return 0;
		}
	}