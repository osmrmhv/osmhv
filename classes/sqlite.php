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

	class SQLite
	{
		static private $connection;

		static function init()
		{
			self::$connection = new PDO("sqlite:".dirname(dirname(__FILE__))."/cache.sqlite3");
			self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}

		static function getConnection()
		{
			if(!self::$connection)
				self::init();
			return self::$connection;
		}
	}