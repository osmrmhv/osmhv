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

	if(count($_GET) == 0)
		$GUI->option("description", _("OSM History Viewer is a debugging tool for Changesets in OpenStreetMap."));
	$GUI->option("importJavaScript", "sortable.js");
	$GUI->head();
?>
<form action="changeset.php" metdod="get" id="changeset-form">
	<fieldset>
		<legend><?=htmlspecialchars(_("Visualise changeset"))?></legend>
		<dl>
			<dt><label for="i-changeset-id"><?=htmlspecialchars(_("Changeset ID"))?></label></dt>
			<dd><input type="text" name="id" id="i-lookup-id" /></dd>
		</dl>
		<button type="submit"><?=htmlspecialchars(_("Lookup"))?></button>
	</fieldset>
</form>

<fieldset id="search-form">
	<legend><?=htmlspecialchars(_("Relation Blame"))?></legend>
	<form action="blame.php" method="get">
		<dl>
			<dt><label for="i-blame"><?=htmlspecialchars(_("Relation ID"))?></label></dt>
			<dd><input type="text" id="i-blame" name="id" /></dd>
		</dl>
		<div><input type="submit" value="<?=htmlspecialchars(_("Blame"))?>" /></div>
	</form>
	<hr />
<?php
	if(isset($_GET["search-key"]) && isset($_GET["search-value"]) && trim($_GET["search-key"]) != "" && trim($_GET["search-value"]) != "")
	{
		try
		{
			if(isset($_GET["xapi"]))
				$results = OSMXAPI::get("/relation[".$_GET["search-key"]."=".$_GET["search-value"]."]");
			else
				$results = OSMAPI::get("/relations/search?type=".urlencode($_GET["search-key"])."&value=".urlencode($_GET["search-value"]));
?>
	<table class="result sortable" id="resultTable">
		<thead>
			<tr>
				<th><?=htmlspecialchars(_("ID"))?></th>
				<th>type</th>
				<th>route</th>
				<th>network</th>
				<th>ref</th>
				<th>name</th>
				<th class="unsortable"><?=htmlspecialchars(_("Blame"))?></th>
			</tr>
		</thead>
		<tbody>
<?php
			foreach($results as $object)
			{
				if(!($object instanceof OSMRelation))
					continue;
?>
			<tr>
				<td><?=htmlspecialchars($object->getDOM()->getAttribute("id"))?></td>
				<td><?=htmlspecialchars($object->getTag("type"))?></td>
				<td><?=htmlspecialchars($object->getTag("route"))?></td>
				<td><?=htmlspecialchars($object->getTag("network"))?></td>
				<td><?=htmlspecialchars($object->getTag("ref"))?></td>
				<td><?=htmlspecialchars($object->getTag("name"))?></td>
				<td><a href="blame.php?id=<?=htmlspecialchars(urlencode($object->getDOM()->getAttribute("id")))?>"><?=htmlspecialchars(_("Blame"))?></a></td>
			</tr>
<?php
			}
?>
		</tbody>
	</table>
<?php
		}
		catch(Exception $e)
		{
?>
	<p class="error"><?=htmlspecialchars($e->getMessage())?></p>
<?php
		}
	}
?>
	<form action="#search-form" method="get">
		<dl>
			<dt><label for="i-search-key"><?=htmlspecialchars(_("Key"))?></label></dt>
			<dd><select id="i-search-key" name="search-key">
				<option name="name"<?=isset($_GET["search-key"]) && $_GET["search-key"] == "name" ? " selected=\"selected\"" : ""?>>name</option>
				<option name="ref"<?=isset($_GET["search-key"]) && $_GET["search-key"] == "ref" ? " selected=\"selected\"" : ""?>>ref</option>
				<option name="operator"<?=isset($_GET["search-key"]) && $_GET["search-key"] == "operator" ? " selected=\"selected\"" : ""?>>operator</option>
			</select></dd>

			<dt><label for="i-search-value"><?=htmlspecialchars(_("Value"))?></label></dt>
			<dd><input type="text" id="i-search-value" name="search-value" value="<?=isset($_GET["search-value"]) ? $_GET["search-value"] : ""?>" /></dd>
		</dl>
		<input type="submit" value="<?=htmlspecialchars(_("Search using OSM API"))?>" />
		<input type="submit" name="xapi" value="<?=htmlspecialchars(_("Search using XAPI"))?>" />
		<p><?=htmlspecialchars(_("OSM API will probably be more current and a lot faster but won’t let you use wildcards."))?></p>
	</form>
</fieldset>
<?php
	$GUI->foot();