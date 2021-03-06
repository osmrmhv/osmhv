<?php
/*
    This file is part of OSM History Viewer.

    OSM History Viewer is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    OSM History Viewer is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with OSM History Viewer.  If not, see <http://www.gnu.org/licenses/>.
*/
	function removeSegments($needle, &$haystack)
	{
		$ret = false;
		foreach(array_keys($haystack) as $k)
		{
			if($haystack[$k] == $needle)
			{
				unset($haystack[$k]);
				$ret = true;
			}
		}
		return $ret;
	}

	function makeSegment($a, $b)
	{
		if($a[0] < $b[0] || ($a[0] == $b[0] && $a[1] < $b[1]))
			return array($a, $b);
		else
			return array($b, $a);
	}

	require_once("include.php");

	if(isset($_GET["id"]) && ($_GET["id"] = preg_replace("/^\\s*#?(.*)\\s*\$/", "\\1", $_GET["id"])) != "")
		$GUI->option("title", sprintf(_("Changeset %s"), $_GET["id"]));

	$GUI->option("importJavaScript", array(
		"http://www.openlayers.org/api/OpenLayers.js",
		"http://maps.google.com/maps?file=api&v=2&key=ABQIAAAApZR0PIISH23foUX8nxj4LxT_x5xGo0Rzkn1YRNpahJvSZYku9hTJeTmkeyXv4TuaU5kM077xJUUM7w",
		"http://api.maps.yahoo.com/ajaxymap?v=3.0&appid=cdauths-map",
		"http://osm.cdauth.de/map/prototypes.js",
		"http://osm.cdauth.de/map/openstreetbugs.js"
	));

	if(!isset($_GET["id"]))
	{
		header("Location: http://".$_SERVER["HTTP_HOST"].dirname($_SERVER["PHP_SELF"]), true, 307);
		die();
	}

	$GUI->head();

	$sql = SQLite::getConnection();

	$information = $sql->query("SELECT * FROM changeset_information WHERE changeset = ".$sql->quote($_GET["id"])." LIMIT 1;")->fetch();
	if(!$information)
		exec("java/osmhv.sh --cache=cache.sqlite3 --changeset=".escapeshellarg($_GET["id"]));
	$information = $sql->query("SELECT * FROM changeset_information WHERE changeset = ".$sql->quote($_GET["id"]).";")->fetch();
	if(!$information || !$information["closed"])
	{
?>
<p class="error"><?=htmlspecialchars(_("Changeset could not be analysed."))?></p>
<?php
	}
?>
<ul>
	<li><a href="./"><?=htmlspecialchars(_("Back to home page"))?></a></li>
	<li><a href="http://www.openstreetmap.org/browse/changeset/<?=htmlspecialchars(urlencode($_GET["id"]))?>"><?=htmlspecialchars(_("Browse on OpenStreetMap"))?></a></li>
</ul>
<?php
	if(!$information)
	{
		$GUI->foot();
		die();
	}
?>
<noscript><p><strong><?=htmlspecialchars(_("Note that many features of this page will not work without JavaScript."))?></strong></p></noscript>
<p><?=sprintf(htmlspecialchars(_("This analysation was created on %s.")), gmdate("Y-m-d\\TH:i:s\\Z", $information["analysed"]))?></p>
<p class="introduction"><strong><?=htmlspecialchars(_("Everything green on this page will show the status after the changeset was committed, red will be the status before, and things displayed in blue haven’t changed."))?></strong></p>
<h2><?=htmlspecialchars(_("Tags"))?></h2>
<dl>
<?php
	$tags = $sql->query("SELECT * FROM changeset_tags WHERE changeset = ".$sql->quote($_GET["id"]).";");
	while($line = $tags->fetch())
	{
?>
	<dt><?=htmlspecialchars($line["k"])?></dt>
<?php
		if(preg_match("/^url(:|\$)/i", $line["k"]))
		{
			$v = explode(";", $line["v"]);
			foreach($v as $k=>$v1)
				$v[$k] = "<a href=\"".htmlspecialchars(trim($v1))."\">".htmlspecialchars($v1)."</a>";
?>
	<dd><?=implode(";", $v)?></dd>
<?php
		}
		else
		{
?>
	<dd><?=htmlspecialchars($line["v"])?></dd>
<?php
		}
	}
?>
</dl>
<h2><?=htmlspecialchars(_("Details"))?></h2>
<dl>
	<dt><?=htmlspecialchars(_("Creation time"))?></dt>
	<dd><?=htmlspecialchars($information["created"])?></dd>

	<dt><?=htmlspecialchars(_("Closing time"))?></dt>
	<dd><?=htmlspecialchars($information["closed"])?></dd>

	<dt><?=htmlspecialchars(_("User"))?></dt>
	<dd><a href="http://www.openstreetmap.org/user/<?=htmlspecialchars(rawurlencode($information["user"]))?>"><?=htmlspecialchars($information["user"])?></a></dd>
</dl>
<h2><?=htmlspecialchars(_("Changed object tags"))?></h2>
<?php
	if(!$sql->query("SELECT COUNT(*) FROM changeset_tags_objects WHERE changeset = ".$sql->quote($_GET["id"]).";")->fetchColumn())
	{
?>
<p class="nothing-to-do"><?=htmlspecialchars(_("No tags have been changed."))?></p>
<?php
	}
	else
	{
?>
<p class="changed-object-tags-note"><?=htmlspecialchars(_("Hover the elements to view the changed tags."))?></p>
<ul class="changed-object-tags">
<?php
		$old_type = null;
		$old_id = null;
		$tags = $sql->query("SELECT * FROM changeset_tags_objects WHERE changeset = ".$sql->quote($_GET["id"]).";");
		while($line = $tags->fetch())
		{
			if($line["type"] != $old_type || $line["id"] != $old_id)
			{
				if($old_type !== null)
				{
?>
			</tbody>
		</table>
	</li>
<?php
				}

				switch($line["type"])
				{
					case 1:
						$type = _("Node");
						$browse = "node";
						break;
					case 2:
						$type = _("Way");
						$browse = "way";
						break;
					case 3:
						$type = _("Relation");
						$browse = "relation";
						break;
				}
?>
	<li><?=htmlspecialchars($type." ".$line["id"])?> (<a href="http://www.openstreetmap.org/browse/<?=htmlspecialchars($browse."/".$line["id"])?>"><?=htmlspecialchars(_("browse"))?></a>)
		<table>
			<tbody>
<?php
				$old_type = $line["type"];
				$old_id = $line["id"];
			}

			if($line["value1"] == $line["value2"])
			{
				$class1 = "unchanged";
				$class2 = "unchanged";
			}
			else
			{
				$class1 = "old";
				$class2 = "new";
			}

			$values = array();
			foreach(array($line["value1"], $line["value2"]) as $i=>$v)
			{
				if(trim($v) != "")
				{
					if(preg_match("/^url(:|\$)/i", $line["tagname"]))
					{
						$v = explode(";", $v);
						foreach($v as $k=>$v1)
							$v[$k] = "<a href=\"".htmlspecialchars(trim($v1))."\">".htmlspecialchars($v1)."</a>";
						$v = implode(";", $v);
					}
					elseif(preg_match("/^wiki(:.*)?\$/i", $line["tagname"], $m))
					{
						$m[1] = strtolower($m[1]);
						$v = explode(";", $v);
						foreach($v as $k=>$v1)
							$v[$k] = "<a href=\"http://wiki.openstreetmap.org/wiki/".htmlspecialchars(rawurlencode(($m[1] == ":symbol" ? "Image:" : "").$v1))."\">".htmlspecialchars($v1)."</a>";
						$v = implode(";", $v);
					}
					else
						$v = htmlspecialchars($v);
				}
				$values[$i] = $v;
			}
?>
				<tr>
					<th><?=htmlspecialchars($line["tagname"])?></th>
					<td class="<?=htmlspecialchars($class1)?>"><?=$values[0]?></td>
					<td class="<?=htmlspecialchars($class2)?>"><?=$values[1]?></td>
				</tr>
<?php
		}

		if($old_type !== null)
		{
?>
			</tbody>
		</table>
	</li>
<?php
		}
?>
</ul>
<?php
	}
?>
<h2><?=htmlspecialchars(_("Map"))?></h2>
<?php
	if(!$sql->query("SELECT COUNT(*) FROM changeset_changes WHERE changeset = ".$sql->quote($_GET["id"]).";")->fetchColumn())
	{
?>
<p class="nothing-to-do"><?=htmlspecialchars(_("No objects were changed in the changeset."))?></p>
<?php
	}
	else
	{
?>
<div id="map"></div>
<script type="text/javascript">
// <![CDATA[
	var map = new OpenLayers.Map.cdauth("map");
	map.addAllAvailableLayers();

	window.onresize = function(){ document.getElementById("map").style.height = Math.round(window.innerHeight*.9)+"px"; map.updateSize(); }
	window.onresize();

	var styleMapUnchanged = new OpenLayers.StyleMap({strokeColor: "#0000ff", strokeWidth: 3, strokeOpacity: 0.3});
	var styleMapCreated = new OpenLayers.StyleMap({strokeColor: "#44ff44", strokeWidth: 3, strokeOpacity: 0.5});
	var styleMapRemoved = new OpenLayers.StyleMap({strokeColor: "#ff0000", strokeWidth: 3, strokeOpacity: 0.5});

	var osbLayer = new OpenLayers.Layer.OpenStreetBugs("OpenStreetBugs", { shortName: "osb", visibility: false });
	map.addLayer(osbLayer);
	osbLayer.setZIndex(500);

	var layerMarkers = new OpenLayers.Layer.cdauth.Markers.LonLat("Markers", { shortName: "m" });
	map.addLayer(layerMarkers);
	var clickControl = new OpenLayers.Control.cdauth.CreateMarker(layerMarkers);
	map.addControl(clickControl);
	clickControl.activate();

	var projection = new OpenLayers.Projection("EPSG:4326");
	var layerCreated = new OpenLayers.Layer.PointTrack("(Created)", {
		styleMap: styleMapCreated,
		projection: projection,
		zoomableInLayerSwitcher: true,
		shortName: "created"
	});
	var layerRemoved = new OpenLayers.Layer.PointTrack("(Removed)", {
		styleMap: styleMapRemoved,
		projection: projection,
		zoomableInLayerSwitcher: true,
		shortName: "removed"
	});
	var layerUnchanged = new OpenLayers.Layer.PointTrack("(Unchanged)", {
		styleMap: styleMapUnchanged,
		projection: projection,
		zoomableInLayerSwitcher: true,
		shortName: "unchanged"
	});
<?php
		$segments = $sql->query("SELECT * FROM changeset_changes WHERE changeset = ".$sql->quote($_GET["id"])." AND action = 1;");
		while($segment = $segments->fetch())
		{
?>
	layerRemoved.addNodes([new OpenLayers.Feature(layerRemoved, new OpenLayers.LonLat(<?=$segment["lon1"]?>, <?=$segment["lat1"]?>).transform(projection, map.getProjectionObject())),new OpenLayers.Feature(layerRemoved, new OpenLayers.LonLat(<?=$segment["lon2"]?>, <?=$segment["lat2"]?>).transform(projection, map.getProjectionObject()))]);
<?php
		}

		$segments = $sql->query("SELECT * FROM changeset_changes WHERE changeset = ".$sql->quote($_GET["id"])." AND action = 2;");
		while($segment = $segments->fetch())
		{
?>
	layerCreated.addNodes([new OpenLayers.Feature(layerCreated, new OpenLayers.LonLat(<?=$segment["lon1"]?>, <?=$segment["lat1"]?>).transform(projection, map.getProjectionObject())),new OpenLayers.Feature(layerCreated, new OpenLayers.LonLat(<?=$segment["lon2"]?>, <?=$segment["lat2"]?>).transform(projection, map.getProjectionObject()))]);
<?php
		}

		$segments = $sql->query("SELECT * FROM changeset_changes WHERE changeset = ".$sql->quote($_GET["id"])." AND action = 3;");
		while($segment = $segments->fetch())
		{
?>
	layerUnchanged.addNodes([new OpenLayers.Feature(layerUnchanged, new OpenLayers.LonLat(<?=$segment["lon1"]?>, <?=$segment["lat1"]?>).transform(projection, map.getProjectionObject())),new OpenLayers.Feature(layerUnchanged, new OpenLayers.LonLat(<?=$segment["lon2"]?>, <?=$segment["lat2"]?>).transform(projection, map.getProjectionObject()))]);
<?php
		}
?>

	map.addLayer(layerUnchanged);
	map.addLayer(layerRemoved);
	map.addLayer(layerCreated);

	var extent1 = layerCreated.getDataExtent();
	var extent2 = layerRemoved.getDataExtent();
	var extent3 = layerUnchanged.getDataExtent();

	var extent = extent1;
	if(extent)
	{
		extent.extend(extent2);
		extent.extend(extent3);
	}
	else
	{
		extent = extent2;
		if(extent)
			extent.extend(extent3);
		else
			extent = extent3;
	}

	if(extent)
		map.zoomToExtent(extent);
	else
		map.zoomToMaxExtent();

	var hashHandler = new OpenLayers.Control.cdauth.URLHashHandler();
	map.addControl(hashHandler);
	hashHandler.activate();
// ]]>
</script>
<?php
	}

	$GUI->foot();
