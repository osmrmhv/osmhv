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
<?php
	$GUI->foot();