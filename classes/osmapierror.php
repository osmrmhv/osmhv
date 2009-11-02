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

	class OSMApiError extends Exception
	{
		function __construct($code, $call=null)
		{
			$message = _("Error during API request: ");
			switch($code)
			{
				case "400":
					$message .= _("400 Bad Request");
					break;
				case "401":
					$message .= _("401 Unauthorized");
					break;
				case "404":
					$message .= _("404 Not Found");
					break;
				case "405":
					$message .= _("405 Method Not Allowed");
					break;
				case "410":
					$message .= _("410 Gone");
					break;
				case "412":
					$message .= _("412 Precondition Failed");
					break;
				case "500":
					$message .= _("500 Internal Server Error");
					break;
				case "503":
					$message .= _("503 Service Unavailable");
					break;
				default:
					$message .= sprintf(_("Unknown error code %s"), $code);
					break;
			}

			if($call)
				$message .= " ".sprintf(_("API call was %s."), $call);

			parent::__construct($message, $code);
		}
	}