<?php
/**
 * Mass-PM to users
 * @license GNU GPLv3 http://opensource.org/licenses/gpl-3.0.html
 * @package Kinokpk.com releaser
 * @author ZonD80 <admin@kinokpk.com>
 * @copyright (C) 2008-now, ZonD80, Germany, TorrentsBook.com
 * @link http://dev.kinokpk.com
 */


require_once "include/bittorrent.php";
dbconn();
loggedinorreturn();
httpauth();

if (get_user_class() < UC_ADMINISTRATOR)
stderr($tracker_lang['error'], $tracker_lang['access_denied']);

stdhead("����� ���������", false);
?>

		if ($_GET["returnto"] || $_SERVER["HTTP_REFERER"])
		{
			?> <input type=hidden name=returnto
		}
		?>
					for ($i=0;;$i++) {
						$class++;
						if ($s = get_user_class_name($i))
						print("<td style=\"border: 0\" width=\"20\"><input type=\"checkbox\" name=\"clases[]\" value=\"$i\"></td><td style=\"border: 0\">$s</td>");
						else
						break;
						if ($class % 4 == 0)
						print("</tr><tr>");
					}
					?>
					stdfoot();
					?>