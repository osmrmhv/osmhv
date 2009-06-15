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
		"http://www.openlayers.org/dev/OpenLayers.js",
		"http://www.openstreetmap.org/openlayers/OpenStreetMap.js",
		"http://opentiles.com/nop/opentiles.js",
		"http://maps.google.com/maps?file=api&v=2&key=ABQIAAAApZR0PIISH23foUX8nxj4LxT_x5xGo0Rzkn1YRNpahJvSZYku9hTJeTmkeyXv4TuaU5kM077xJUUM7w",
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

	var osbLayer = new OpenLayers.Layer.cdauth.markers.OpenStreetBugs("OpenStreetBugs", "../map/openstreetbugs.php", { visibility: false });
	map.addLayer(osbLayer);
	osbLayer.setZIndex(500);

	var layerMarkers = new OpenLayers.Layer.cdauth.markers.LonLat("Markers");
	map.addLayer(layerMarkers);
	layerMarkers.addClickControl();

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
