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

	require_once(dirname(__FILE__)."/osmnode.php");
	require_once(dirname(__FILE__)."/osmway.php");
	require_once(dirname(__FILE__)."/osmrelation.php");
	require_once(dirname(__FILE__)."/osmapi.php");

	class OSMObject
	{
		private static $cache = array(
			"relation" => array(),
			"way" => array(),
			"node" => array()
		);

		protected $domelement;

		function __construct(DOMElement $el)
		{
			$this->domelement = $el;
		}

		function getDOM()
		{
			return $this->domelement;
		}

		function getTag($key)
		{
			$return = array();

			$tags = $this->domelement->getElementsByTagName("tag");
			for($i=0; $i<$tags->length; $i++)
			{
				$it = $tags->item($i);
				if($it->getAttribute("k") == $key)
					$return[] = $it->getAttribute("v");
			}

			return implode(",", $return);
		}

		static function fetch($type, $a_id)
		{
			$ids = is_array($a_id) ? $a_id : array($a_id);
			$return = array();
			foreach($ids as $k=>$id)
			{
				if(isset(self::$cache[$type]) && isset(self::$cache[$type][$id]))
				{
					$return[$id] = self::cast(self::$cache[$type][$id]);
					unset($ids[$k]);
				}
			}

			if(count($ids) > 0)
			{
				$elements = OSMAPI::get("/".$type."s/?".$type."s=".urlencode(implode(",",$ids)));
				foreach($elements as $element)
					$return[$element->getDOM()->getAttribute("id")] = $element;
			}

			if(is_array($a_id))
				return $return;
			elseif(!isset($return[$a_id]))
				throw new IOException(_("This element could not be found."));
			else
				return $return[$a_id];
		}

		static function downloadFull($type, $id, $pretend=false)
		{
			$in_cache = false;
			if(isset(self::$cache[$type]) && isset(self::$cache[$type][$id]))
			{
				$in_cache = true;
				foreach(self::cast(self::$cache[$type][$id])->getMembers() as $member)
				{
					if(!isset(self::$cache[$member["type"]]) || !isset(self::$cache[$member["type"]][$member["ref"]]) || ($member["type"] != "node" && !self::downloadFull($member["type"], $member["ref"], true)))
					{
						$in_cache = false;
						break;
					}
				}
			}

			if($pretend)
				return $in_cache;
			elseif(!$in_cache)
				OSMAPI::get("/".$type."/".$id."/full");
		}

		static function cast(DOMElement $el)
		{
			switch($el->tagName)
			{
				case "relation":
					return new OSMRelation($el);
				case "way":
					return new OSMWay($el);
				case "node":
					return new OSMNode($el);
				default:
					return new OSMObject($el);
			}
		}

		static function cache(OSMObject $obj)
		{
			if(!isset(self::$cache[$obj->getDOM()->tagName]))
				self::$cache[$obj->getDOM()->tagName] = array();
			self::$cache[$obj->getDOM()->tagName][$obj->getDOM()->getAttribute("id")] = $obj->getDOM();
		}
	}