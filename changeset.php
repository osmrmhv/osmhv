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

	if(isset($_GET["id"]) && trim($_GET["id"]) != "")
		$GUI->option("title", sprintf(_("Changeset %s"), $_GET["id"]));

	$GUI->option("importJavaScript", array(
		"http://www.openlayers.org/dev/OpenLayers.js",
		"http://www.openstreetmap.org/openlayers/OpenStreetMap.js",
		"http://opentiles.com/nop/opentiles.js",
		"http://maps.google.com/maps?file=api&v=2&key=ABQIAAAApZR0PIISH23foUX8nxj4LxRVUhLnhg56yNVgGimb4VgzicU35hToYSUJhzK3xPVyB56mgwD0MN2yDg",
		"http://api.maps.yahoo.com/ajaxymap?v=3.0&appid=cdauths-map",
		"http://osm.cdauth.de/map/prototypes.js"
	));

	$GUI->head();

	if(!isset($_GET["id"]))
	{
		header("Location: http://".$_SERVER["HTTP_HOST"].dirname($_SERVER["PHP_SELF"]), true, 307);
		die();
	}

	$sql = SQLite::getConnection();

	$information = $sql->query("SELECT * FROM changeset_information WHERE changeset = ".$sql->quote($_GET["id"]).";")->fetch();
	if(!$information)
		exec("java/osmhv --cache=cache.sqlite3 --changeset=".escapeshellarg($_GET["id"]));
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
<p>
<p><?=sprintf(htmlspecialchars(_("This analysation was created on %s.")), gmdate("Y-m-d\\TH:i:s\\Z", $information["analysed"]))?></p>
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

	var layerMarkers = new OpenLayers.Layer.cdauth.markers.LonLat("Markers", new OpenLayers.Icon('http://osm.cdauth.de/map/marker.png', new OpenLayers.Size(21,25), new OpenLayers.Pixel(-9, -25)));
	map.addLayer(layerMarkers);
	var click = new OpenLayers.Control.cdauth.MarkerClick(layerMarkers);
	map.addControl(click);
	click.activate();

	var projection = new OpenLayers.Projection("EPSG:4326");
	var layerCreated = new OpenLayers.Layer.PointTrack("(Created)", {
		styleMap: styleMapCreated,
		projection: projection
		//displayInLayerSwitcher: false
	});
	var layerRemoved = new OpenLayers.Layer.PointTrack("(Removed)", {
		styleMap: styleMapRemoved,
		projection: projection
		//displayInLayerSwitcher: false
	});
	var layerUnchanged = new OpenLayers.Layer.PointTrack("(Unchanged)", {
		styleMap: styleMapUnchanged,
		projection: projection
		//displayInLayerSwitcher: false
	});
<?php
	$segments = $sql->query("SELECT DISTINCT segment FROM changeset_changes WHERE changeset = ".$sql->quote($_GET["id"]).";");
	$old_segments = array();
	$new_segments = array();
	while($segment = $segments->fetch())
	{
		$old = array();
		$new = array();
		$old_last = null;
		$new_last = null;
		$points = $sql->query("SELECT lat, lon, old FROM changeset_changes WHERE changeset = ".$sql->quote($_GET["id"])." AND segment = ".$sql->quote($segment["segment"])." ORDER BY i ASC;");
		while($point = $points->fetch())
		{
			if($point["old"])
			{
				if($old_last)
					$old[] = makeSegment($old_last, array($point["lon"], $point["lat"]));
				$old_last = array($point["lon"], $point["lat"]);
			}
			else
			{
				if($new_last)
					$new[] = makeSegment($new_last, array($point["lon"], $point["lat"]));
				$new_last = array($point["lon"], $point["lat"]);
			}
		}

		if(count($old) == 0 && $old_last && (count($new) != 0 || $old_last != $new_last))
		{
?>
	layerRemoved.addNodes([new OpenLayers.Feature(layerRemoved, new OpenLayers.LonLat(<?=$old_last[0]?>, <?=$old_last[1]?>).transform(projection, map.getProjectionObject())),new OpenLayers.Feature(layerRemoved, new OpenLayers.LonLat(<?=$old_last[0]?>, <?=$old_last[1]?>).transform(projection, map.getProjectionObject()))]);
<?php
		}
		if(count($new) == 0 && $new_last && (count($old) != 0 || $new_last != $old_last))
		{
?>
	layerCreated.addNodes([new OpenLayers.Feature(layerCreated, new OpenLayers.LonLat(<?=$new_last[0]?>, <?=$new_last[1]?>).transform(projection, map.getProjectionObject())),new OpenLayers.Feature(layerCreated, new OpenLayers.LonLat(<?=$new_last[0]?>, <?=$new_last[1]?>).transform(projection, map.getProjectionObject()))]);
<?php
		}
		if(count($old) == 0 && count($new) == 0 && $old_last && $old_last == $new_last)
		{
?>
	layerUnchanged.addNodes([new OpenLayers.Feature(layerUnchanged, new OpenLayers.LonLat(<?=$old_last[0]?>, <?=$old_last[1]?>).transform(projection, map.getProjectionObject())),new OpenLayers.Feature(layerUnchanged, new OpenLayers.LonLat(<?=$old_last[0]?>, <?=$old_last[1]?>).transform(projection, map.getProjectionObject()))]);
<?php
		}

		if(count($old) > 0)
			$old_segments = array_merge($old_segments, $old);
		if(count($new) > 0)
			$new_segments = array_merge($new_segments, $new);
	}

	foreach(array_keys($old_segments) as $k)
	{
		if(!isset($old_segments[$k]))
			continue;
		$old1 = &$old_segments[$k];
		if(removeSegments($old1, &$new_segments))
			$layer = "layerUnchanged";
		else
			$layer = "layerRemoved";
?>
	<?=$layer?>.addNodes([new OpenLayers.Feature(<?=$layer?>, new OpenLayers.LonLat(<?=$old1[0][0]?>, <?=$old1[0][1]?>).transform(projection, map.getProjectionObject())), new OpenLayers.Feature(<?=$layer?>, new OpenLayers.LonLat(<?=$old1[1][0]?>, <?=$old1[1][1]?>).transform(projection, map.getProjectionObject()))]);
<?php
		removeSegments($old1, &$old_segments);
	}
	foreach(array_keys($new_segments) as $k)
	{
		if(!isset($new_segments[$k]))
			continue;
		$new1 = $new_segments[$k];
?>
	layerCreated.addNodes([new OpenLayers.Feature(layerCreated, new OpenLayers.LonLat(<?=$new1[0][0]?>, <?=$new1[0][1]?>).transform(projection, map.getProjectionObject())), new OpenLayers.Feature(layerCreated, new OpenLayers.LonLat(<?=$new1[1][0]?>, <?=$new1[1][1]?>).transform(projection, map.getProjectionObject()))]);
<?php
		removeSegments($new1, &$new_segments);
	}
?>

	map.addLayer(layerUnchanged);
	map.addLayer(layerRemoved);
	map.addLayer(layerCreated);

	extent = layerCreated.getDataExtent();
	extent.extend(layerRemoved.getDataExtent());
	extent.extend(layerUnchanged.getDataExtent());

	if(extent)
		map.zoomToExtent(extent);
	else
		map.zoomToMaxExtent();
// ]]>
</script>
<?php
	$GUI->foot();