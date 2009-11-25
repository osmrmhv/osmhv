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

	require_once("include.php");

	if(!isset($_GET["id"]))
	{
		header("Location: http://".$_SERVER["HTTP_HOST"].dirname($_SERVER["PHP_SELF"]), true, 307);
		die();
	}

	$_GET["id"] = trim($_GET["id"]);

	$GUI->option("importJavaScript", array(
		"http://www.openlayers.org/api/OpenLayers.js",
		"http://maps.google.com/maps?file=api&v=2&key=ABQIAAAApZR0PIISH23foUX8nxj4LxT_x5xGo0Rzkn1YRNpahJvSZYku9hTJeTmkeyXv4TuaU5kM077xJUUM7w",
		"http://api.maps.yahoo.com/ajaxymap?v=3.0&appid=cdauths-map",
		"http://osm.cdauth.de/map/prototypes.js",
		"http://osm.cdauth.de/map/openstreetbugs.js"
	));

	$GUI->head();

	$sql = SQLite::getConnection();

	$changesets = array();
	$changesets_by_user = array();
	$user_colours = array();
	$user_changes = array();

	if(!$sql->query("SELECT * FROM relation_blame WHERE relation = ".$sql->quote($_GET["id"])." LIMIT 1;")->fetch())
		exec("java/osmhv --cache=cache.sqlite3 --blame=".escapeshellarg($_GET["id"]));

	$changesets_sql = $sql->query("SELECT DISTINCT changeset FROM relation_blame WHERE relation = ".$sql->quote($_GET["id"])." ORDER BY changeset DESC;");
	while($changeset = $changesets_sql->fetch())
	{
		$changesets[$changeset["changeset"]] = $sql->query("SELECT * FROM changeset_cache WHERE changeset = ".$sql->quote($changeset["changeset"])." LIMIT 1;")->fetch();
		$user = $changesets[$changeset["changeset"]]["user"];
		if(!isset($changesets_by_user[$user]))
			$changesets_by_user[$user] = array();
		$changesets_by_user[$user][] = $changeset["changeset"];
		if(!isset($user_changes[$user]))
			$user_changes[$user] = 0;
		$user_changes[$user] += $sql->query("SELECT COUNT(*) FROM relation_blame WHERE relation = ".$sql->quote($_GET["id"])." AND changeset = ".$sql->quote($changeset["changeset"]).";")->fetchColumn();
	}

	arsort($user_changes, SORT_NUMERIC);

	$predefined_colours = array(
		"#000",
		"#f00",
		"#0f0",
		"#00f",
		"#ff0",
		"#f0f",
		"#0ff"
	);

	foreach(array_keys($user_changes) as $i=>$user)
	{
		if(isset($predefined_colours[$i]))
			$user_colours[$user] = $predefined_colours[$i];
		else
		{
			$n1 = rand(6, 12);
			$n2 = rand(12-($n1-6), 12);
			$n3 = 30-$n1-$n2;
			$user_colours[$user] = "#".dechex($n1).dechex($n2).dechex($n3);
		}
	}
?>
<noscript><p><strong><?=htmlspecialchars(_("Note that many features of this page will not work without JavaScript."))?></strong></p></noscript>
<div id="map" class="blame-map"></div>
<div class="blame-changesets">
	<h2><?=htmlspecialchars(_("Affecting changesets by user"))?> (<a href="javascript:setGlobalVisibility(false)"><?=htmlspecialchars(_("Hide all"))?></a>) (<a href="javascript:setGlobalVisibility(true)"><?=htmlspecialchars(_("Show all"))?></a>) (<a href="javascript:map.zoomToExtent(extent)"><?=htmlspecialchars(_("Zoom all"))?></a>)</h2>
	<ul>
<?php
	foreach(array_keys($user_changes) as $user)
	{
		$user_changesets = $changesets_by_user[$user];
?>
		<li><strong class="user-colour" style="color:<?=htmlspecialchars($user_colours[$user])?>;"><a href="http://www.openstreetmap.org/user/<?=htmlspecialchars(rawurlencode($user))?>"><?=htmlspecialchars($user)?></a></strong><ul>
<?php
		foreach($user_changesets as $user_changeset)
		{
?>
			<li><input type="checkbox" id="checkbox-<?=htmlspecialchars($user_changeset)?>" onchange="layers[<?=htmlspecialchars($user_changeset)?>].setVisibility(this.checked);" /><?=htmlspecialchars($user_changeset)?>: <?=$changesets[$user_changeset]["message"] ? "„".htmlspecialchars($changesets[$user_changeset]["message"])."“" : "<span class=\"nocomment\">".htmlspecialchars(_("No comment"))."</span>"?> (<a href="javascript:map.zoomToExtent(layers['<?=htmlspecialchars($user_changeset)?>'].getDataExtent())"><?=htmlspecialchars(_("Zoom"))?></a>) (<a href="http://www.openstreetmap.org/browse/changeset/<?=htmlspecialchars(rawurlencode($user_changeset))?>"><?=htmlspecialchars(_("browse"))?></a>) (<a href="changeset.php?id=<?=htmlspecialchars(rawurlencode($user_changeset))?>"><?=htmlspecialchars(_("view"))?></a>)</li>
<?php
		}
?>
		</ul></li>
<?php
	}
?>
	</ul>
</div>
<script type="text/javascript">
// <![CDATA[
	var map = new OpenLayers.Map.cdauth("map");
	map.addAllAvailableLayers();

	window.onresize = function(){ document.getElementById("map").style.height = Math.round(window.innerHeight*.9)+"px"; map.updateSize(); }
	window.onresize();

	var osbLayer = new OpenLayers.Layer.OpenStreetBugs("OpenStreetBugs", { visibility: false, shortName: "osb" });
	map.addLayer(osbLayer);
	osbLayer.setZIndex(500);

	var layerMarkers = new OpenLayers.Layer.cdauth.Markers.LonLat("Markers", { shortName: "m" });
	map.addLayer(layerMarkers);
	var clickControl = new OpenLayers.Control.cdauth.CreateMarker(layerMarkers);
	map.addControl(clickControl);
	clickControl.activate();

	var projection = new OpenLayers.Projection("EPSG:4326");

	var extent = null;

	var layers = { };
<?php
	foreach($changesets as $changeset=>$info)
	{
?>
	layers['<?=$changeset?>'] = new OpenLayers.Layer.PointTrack("Changeset <?=$changeset?>", { styleMap : new OpenLayers.StyleMap({strokeColor: "<?=$user_colours[$info["user"]]?>", strokeWidth: 5, strokeOpacity: 0.6, shortName: "<?=$changeset?>"}), projection : projection, zoomableInLayerSwitcher : true });
<?php
		$segments = $sql->query("SELECT * FROM relation_blame WHERE relation = ".$sql->quote($_GET["id"])." AND changeset = ".$sql->quote($changeset).";");
		while($segment = $segments->fetch())
		{
?>
	layers['<?=$changeset?>'].addNodes([new OpenLayers.Feature(layers['<?=$changeset?>'], new OpenLayers.LonLat(<?=$segment["lon1"]?>, <?=$segment["lat1"]?>).transform(projection, map.getProjectionObject())),new OpenLayers.Feature(layers['<?=$changeset?>'], new OpenLayers.LonLat(<?=$segment["lon2"]?>, <?=$segment["lat2"]?>).transform(projection, map.getProjectionObject()))]);
<?php
		}
?>
	map.addLayer(layers['<?=$changeset?>']);
	if(extent)
		extent.extend(layers['<?=$changeset?>'].getDataExtent());
	else
		extent = layers['<?=$changeset?>'].getDataExtent();

<?php
	}
?>
	if(extent)
		map.zoomToExtent(extent);

	var hashHandler = new OpenLayers.Control.cdauth.URLHashHandler();
	map.addControl(hashHandler);
	hashHandler.activate();

	function setGlobalVisibility(visibility)
	{
		for(var i in layers)
			layers[i].setVisibility(visibility);
	}

	function checkLayerVisibility()
	{
		for(var i in layers)
			document.getElementById("checkbox-"+i).checked = layers[i].getVisibility();
	}

	map.events.register("changelayer", null, checkLayerVisibility);
	checkLayerVisibility();
// ]]>
</script>
<?php
	$GUI->foot();