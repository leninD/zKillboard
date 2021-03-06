<?php
/* zKillboard
 * Copyright (C) 2012-2013 EVE-KILL Team and EVSCO.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class cli_fightFinder implements cliCommand
{
	public function getDescription()
	{
		return "Finds fights that have happened all around EVE, and posts them to IRC. |w|Beware, this script requires an IRC bot.|n| |g|Usage: fightFinder";
	}

	public function getAvailMethods()
	{
		return ""; // Space seperated list
	}

	public function execute($parameters)
	{
		Db::execute("delete from zz_social where insertTime < date_sub(now(), interval 23 hour)");
		$minPilots = 100;
		$minWrecks = 100;
		$result = Db::query("select * from (select solarSystemID, count(distinct characterID) count, count(distinct killID) kills from zz_participants where characterID != 0 and killID > 0 and dttm > date_sub(now(), interval 1 hour) group by 1 order by 2 desc) f where count >= $minPilots and kills > $minWrecks");
		foreach($result as $row) {
			$systemID = $row["solarSystemID"];
			$key = ($row["solarSystemID"] * 100) + date("H");
			$key2 = ($row["solarSystemID"] * 100) + date("H", time() + 3600);

			$count = Db::queryField("select count(*) count from zz_social where killID = :killID", "count", array(":killID" => $key), 0);
			if ($count != 0) continue;
			Db::execute("insert ignore into zz_social (killID) values (:k1), (:k2)", array(":k1" => $key, ":k2" => $key2));

			Info::addInfo($row);
			$wrecks = number_format($row['kills'], 0);
			$involved = number_format($row['count'], 0);
			$system = $row["solarSystemName"];
			$date = date("YmdH00");
			$link = "https://zkillboard.com/related/$systemID/$date/";

			$message = "Battle detected in |g|$system|n| with |g|$involved|n| involved and |g|$wrecks|n| wrecks.";
			Log::irc($message . "|g|$link");
			$isgd = Twit::shortenURL($link);
			$message = Log::stripIRCColors($message . " $isgd #tweetfleet #eveonline");
			$tweet = Twit::sendMessage($message);
			$twitID = $tweet->id;
			Log::irc("Message was also tweeted: $twitID");
		}
	}
}
