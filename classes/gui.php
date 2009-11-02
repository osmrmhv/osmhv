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

	class GUI
	{
		private $options = array();

		function option($name, $value=null)
		{
			if(is_null($value))
				return isset($this->options[$name]) ? $this->options[$name] : null;
			else
				$this->options[$name] = $value;
		}

		function head()
		{
			$title = _("OSM History Viewer");
			if($this->option("title"))
				$title = sprintf(_("OSM History Viewer: %s"), $this->option("title"));
?>
<?="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<!--
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

	Obtain the source code from http://svn.cdauth.de/viewvc.cgi/Tools/osm/history-viewer/
	or svn://svn.cdauth.de/tools/osm/history-viewer/.
-->
	<head>
		<title><?=htmlspecialchars($title)?></title>
<?php
			if($this->option("description"))
			{
?>
		<meta name="description" content="<?=htmlspecialchars($this->option("description"))?>" />
		<meta name="robots" content="index,nofollow" />
<?php
			}
			else
			{
?>
		<meta name="robots" content="noindex,nofollow" />
<?php
			}
?>
		<link rel="stylesheet" href="style.css" type="text/css" />
<?php
			$javascripts = $this->option("importJavaScript");
			if($javascripts)
			{
				if(!is_array($javascripts))
					$javascripts = array($javascripts);
				foreach($javascripts as $javascript)
				{
?>
		<script type="text/javascript" src="<?=htmlspecialchars($javascript)?>"></script>
<?php
				}
			}
?>
	</head>
	<body<?=$this->option("bodyClass") ? " class=\"".htmlspecialchars($this->option("bodyClass"))."\"" : ""?>>
		<h1><?=htmlspecialchars($title)?></h1>
<?php
			$languages = array("en_GB" => "English", "de_DE" => "Deutsch");
			$url_prefix = "?";
			if(strlen($_SERVER["QUERY_STRING"]) > 0)
				$url_prefix .= $_SERVER["QUERY_STRING"]."&";
?>
		<ul id="switch-lang">
<?php
			foreach($languages as $lang=>$name)
			{
				if(isset($_ENV["LANG"]) && $_ENV["LANG"] == $lang)
					continue;
?>
			<li><a href="<?=htmlspecialchars($url_prefix."lang=".urlencode($lang))?>"><?=htmlspecialchars($name)?></a></li>
<?php
			}
?>
		</ul>
<?php
		}

		function foot()
		{
?>
		<hr />
		<p>All geographic data by <a href="http://www.openstreetmap.org/">OpenStreetMap</a>, available under <a href="http://creativecommons.org/licenses/by-sa/2.0/">cc-by-sa-2.0</a>.</p>
		<p>OSM History Viewer is free software: you can redistribute it and/or modify it under the terms of the <a href="http://www.gnu.org/licenses/agpl.html">GNU Affero General Public License</a> as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version. Get the source code via <a href="svn://cdauth.de/tools/osm/history-viewer/">SVN</a>/<a href="http://svn.cdauth.de/viewvc.cgi/Tools/osm/history-viewer/">WebSVN</a>.</p>
	</body>
</html>
<?php
		}
	}