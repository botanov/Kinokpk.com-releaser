<?php

/*
 Project: Kinokpk.com releaser
 This file is part of Kinokpk.com releaser.
 Kinokpk.com releaser is based on TBDev,
 originally by RedBeard of TorrentBits, extensively modified by
 Gartenzwerg and Yuna Scatari.
 Kinokpk.com releaser is free software;
 you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.
 Kinokpk.com is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 You should have received a copy of the GNU General Public License
 along with Kinokpk.com releaser; if not, write to the
 Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston,
 MA  02111-1307  USA
 Do not remove above lines!
 */

require_once("include/bittorrent.php");
require_once(ROOT_PATH."include/benc.php");

ini_set("upload_max_filesize",$CACHEARRAY['max_torrent_size']);

function bark($msg) {
	genbark($msg, $tracker_lang['error']);
}

dbconn();

loggedinorreturn();
parked();

if (get_user_class() < UC_USER) stderr($tracker_lang['error'],$tracker_lang['access_denied']);

foreach(explode(":","type:name") as $v) {
	if (!isset($_POST[$v]))
	bark("�� ��� ���� ���������");
}



if ($_POST['annonce']) { $_POST['nofile'] = 1; $_POST['nofilesize'] = 0; }

if ($_POST['nofile']) {} else {
	if (!isset($_FILES["tfile"]))
	bark("missing form data");

	if (($_POST['nofile']) && (empty($_POST['nofilesize']))) bark("�� �� ������� ������ �� ������� ������!");

	$f = $_FILES["tfile"];
	$fname = unesc($f["name"]);
	if (empty($fname))
	bark("���� �� ��������. ������ ��� �����!");
}


if (!is_array($_POST["type"]))
bark("������ ��������� ��������� ���������!");
else
foreach ($_POST['type'] as $cat) if (!is_valid_id($cat)) bark($tracker_lang['error'],$tracker_lang['invalid_id']);

$catsstr = implode(',',$_POST['type']);

if ($CACHEARRAY['use_integration']) {
	$catssql= sql_query("SELECT name FROM categories WHERE id IN ($catsstr)");
	while (list($catname) = mysql_fetch_array($catssql)) $forumcats[]=$catname;
	$forumcats = implode(', ',$forumcats);
}

if ($_POST['nofile']) {} else {

	if (!validfilename($fname))
	bark("�������� ��� �����!");
	if (!preg_match('/^(.+)\.torrent$/si', $fname, $matches))
	bark("�������� ��� ����� (�� .torrent).");
	$shortfname = $torrent = $matches[1];
}

if ($_POST['multi']) $multi=1; else $multi=0;

if (!empty($_POST["name"]))
$torrent = unesc($_POST["name"]); else bark("�� �� ����� �������� ������");

if ($_POST['nofile']) {} else {
	$tmpname = $f["tmp_name"];
	if (!is_uploaded_file($tmpname))
	bark("eek");
	if (!filesize($tmpname))
	bark("������ ����!");

	$dict = bdec_file($tmpname, $CACHEARRAY['max_torrent_size']);
	if (!isset($dict))
	bark("��� �� ����� �� ����������? ��� �� �������-����������� ����!");
}

if ($_POST['free'] AND get_user_class() >= UC_MODERATOR) {
	$free = 1;
} else {
	$free = 0;
};

if ($_POST['sticky'] AND get_user_class() >= UC_MODERATOR)
$sticky = 1;
else
$sticky = 0;



$ret = sql_query("SHOW TABLE STATUS LIKE 'torrents'");
$row = mysql_fetch_array($ret);
$next_id = $row['Auto_increment'];

if ($_POST['nofile']) {} else {

	unset($dict['value']['nodes']); // remove cached peers (Bitcomet & Azareus)
	unset($dict['value']['azureus_properties']); // remove azureus properties
	unset($dict['value']['comment']);
	unset($dict['value']['created by']);
	unset($dict['value']['publisher']);
	unset($dict['value']['publisher.windows-1251']);
	unset($dict['value']['publisher-url']);
	unset($dict['value']['publisher-url.windows-1251']);

	if (!$multi) {
		//  $dict['value']['info']['value']['private']=bdec('i1e');  // add private tracker flag
		unset($dict['value']['announce-list']);
		unset($dict['value']['announce']);

	} else $anarray = get_announce_urls($dict);

	if ($multi && !$anarray) stderr($tracker_lang['error'],'���� �������-���� �� �������� ���������������. <a href="javascript:history.go(-1);">�����</a>');

	$dict=bdec(benc($dict)); // double up on the becoding solves the occassional misgenerated infohash

	list($info) = dict_check($dict, "info");

	list($dname, $plen, $pieces) = dict_check($info, "name(string):piece length(integer):pieces(string)");

	/*if (!in_array($ann, $announce_urls, 1))
	 bark("�������� Announce URL! ������ ���� ".$announce_urls[0]);*/

	if (strlen($pieces) % 20 != 0)
	bark("invalid pieces");

	$filelist = array();
	$totallen = dict_get($info, "length", "integer");
	if (isset($totallen)) {
		$filelist[] = array($dname, $totallen);
		$type = 0;
	} else {
		$flist = dict_get($info, "files", "list");
		if (!isset($flist))
		bark("missing both length and files");
		if (!count($flist))
		bark("no files");
		$totallen = 0;
		foreach ($flist as $fn) {
			list($ll, $ff) = dict_check($fn, "length(integer):path(list)");
			$totallen += $ll;
			$ffa = array();
			foreach ($ff as $ffe) {
				if ($ffe["type"] != "string")
				bark("filename error");
				$ffa[] = $ffe["value"];
			}
			if (!count($ffa))
			bark("filename error");
			$ffe = implode("/", $ffa);
			$filelist[] = array($ffe, $ll);
			/*	if ($ffe == 'Thumbs.db')
			 {
			 stderr("������", "� ��������� ��������� ������� ����� Thumbs.db!");
			 die;
			 }*/
		}
		$type = 1;
	}

	$infohash = sha1($info["string"]);

}

//////////////////////////////////////////////
//////////////Take Image Uploads//////////////

$maxfilesize = 512000; // 500kb

for ($x=0; $x < $CACHEARRAY['max_images']; $x++) {
	$y=$x+1;
	if (!empty($_POST['img'.$x])) {
		$img=trim((string)$_POST['img'.$x]);
		if (strpos($img,',') || strpos($img,'?')) stderr($tracker_lang['error'],'������������ ����������� ���������');

		$check = @getimagesize($img);
		if (!$check) stderr($tracker_lang['error'],'����������� �������� '.$y.' - �� ��������');
		$check = remote_fsize($img);
		if (!$check) stderr($tracker_lang['error'],'�� ������� ���������� ������ �������� '.$y);
		if ($check>$maxfilesize) stderr($tracker_lang['error'],'������������ ������ �������� 512kb. ������ ��� �������� �������� '.$y);
		$inames[]=$img;
	}
}


$image = $inames;

$images = @implode(',',$inames);

$image = @array_shift($image);

// FORUMDESC will be used in email notifs
if (!$image) $forumdesc = "<div align=\"center\"><img src=\"{$CACHEARRAY['defaultbaseurl']}/pic/noimage.gif\" border=\"0\" class=\"linked-image\" /></div><br />";
if ($image) $forumdesc = "<div align=\"center\"><a href=\"$image\" target=\"_blank\"><img alt=\"������ ��� ������ (�������� ��� ��������� ������� �����������)\" src=\"$image\" border=\"0\" class=\"linked-image\" /></a></div><br />";

$forumdesc .= "<table width=\"100%\" border=\"1\"><tr><td valign=\"top\"><b>��� (����):</b></td><td>".$forumcats."</td></tr><tr><td><b>��������:</b></td><td>" . sqlforum($torrent) ."</td></tr>";

if ($CACHEARRAY['use_integration']) {
	// IPB TOPIC TRANSFER
	$topicname = $torrent;
	// END, CONTINUE BELOW
}

// DEFINE size FOR forum & email notifs
if ($_POST['nofile']) {
	$forumsize = mksize($_POST['nofilesize']); } else { $forumsize = mksize($totallen/1024/1024);    }


	$descr = (string) $_POST['descr'];

	if (!$descr) stderr($tracker_lang['error'],'�� �� ����� ��������');

	$descr = ($descr);
	//////////////////////////////////////////////

	// Replace punctuation characters with spaces
	if ($_POST['nofile']) {
		$nofilesize = (float)$_POST['nofilesize'];
		$fname = 'nofile';
		$infohash = md5($torrent);
		$torrent = htmlspecialchars(str_replace("_", " ", $torrent));
		if ($_POST['annonce'])
		$torrent .= " | �����"; else $torrent .= " - ����� ��� ��������";

		$totallen = (float)($nofilesize*1024*1024);

		$ret = sql_query("INSERT INTO torrents (filename, owner, visible, sticky, info_hash, name, descr, size, free, images, category, added, last_action".((get_user_class() >= UC_UPLOADER)?', moderatedby':'').") VALUES (" . implode(",", array_map("sqlesc", array($fname, $CURUSER["id"], 1, $sticky, $infohash, $torrent, $descr, $totallen, $free, $images, $catsstr))) . ", '" . time() . "', '" . time() . "'".((get_user_class() >= UC_UPLOADER)?', '.$CURUSER['id']:'').")");
	} else {

		$torrent = htmlspecialchars(str_replace("_", " ", $torrent));

		$ret = sql_query("INSERT INTO torrents (filename, owner, visible, sticky, info_hash, announce_urls, name, descr, size, numfiles, ismulti, free, images, category, added, last_action".((get_user_class() >= UC_UPLOADER)?', moderatedby':'').") VALUES (" . implode(",", array_map("sqlesc", array($fname, $CURUSER["id"], 1, $sticky, $infohash, @implode(",",$anarray), $torrent, $descr, $totallen, count($filelist), $type, $free, $images, $catsstr))) . ", '" . time() . "', '" . time() . "'".((get_user_class() >= UC_UPLOADER)?', '.$CURUSER['id']:'').")");
	}
	if (!$ret) {
		if (mysql_errno() == 1062)
		bark("$id torrent already uploaded!");
		bark("mysql puked: ".mysql_error());
	}
	$id = mysql_insert_id();

	sql_query("UPDATE users SET bonus=bonus+25 WHERE id =".$CURUSER['id']);

	//DEFINE category for EMAIL notifs & forum
	$forumcat['relid'] = array_shift($_POST['type']);
	$forumcatsql = sql_query("SELECT name,forum_id,disable_export FROM categories WHERE id={$forumcat['relid']}");
	list($forumcat['name'],$forumcat['forumid'],$export_disabled) = mysql_fetch_array($forumcatsql);
	// cats end

	// making forum desc
	$forumdesc .= "<tr><td valign=\"top\"><b>".$tracker_lang['description'].":</b></td><td>".format_comment($descr)."</td></tr>";

	$forumdesc .= "<tr><td valign=\"top\"><b>������ �����:</b></td><td>".$forumsize."</td></tr>";

	$topicfooter .= "<tr><td valign=\"top\"><b>".((!$_POST['nofile'])?"������� {$CACHEARRAY['defaultbaseurl']}:":"����� {$CACHEARRAY['defaultbaseurl']}:")."</b></td><td><div align=\"center\">[<span style=\"color:#FF0000\"><a href=\"{$CACHEARRAY['defaultbaseurl']}/details.php?id=".$id."\">���������� ���� ����� �� {$CACHEARRAY['defaultbaseurl']}</a></span>]</div></td></tr></table>";

	$forumdesc .=$topicfooter;
	// end

	if ($CACHEARRAY['use_integration'] && !$export_disabled) {
		// IPB TOPIC TRANSFER

		$ipbuser = $CURUSER['username'];

		// connecting to IPB DB
		forumconn();
		//connection opened
		if (!empty($_POST['topic'])) {
			if (!is_valid_id($_POST['topic'])) die("�������� ID ����");
			$topicid =  (int) $_POST['topic'];

			$topic = sql_query("UPDATE ".$fprefix."topics SET title = ".sqlesc($topicname)." WHERE tid =".$topicid) or die(mysql_error());
			$postid = sql_query("SELECT topic_firstpost FROM ".$fprefix."topics WHERE tid =".$topicid)  or die(mysql_error());
			$postid = mysql_result($postid,0);
			if ($CACHEARRAY['exporttype'] == "wiki")
			$post = sql_query("UPDATE ".$fprefix."posts SET post = '---', wiki = ".sqlesc($forumdesc)." WHERE pid = ".$postid);
			else $post = sql_query("UPDATE ".$fprefix."posts SET post = ".sqlesc($forumdesc)." WHERE pid = ".$postid);
		} else {

			$check = sql_query("SELECT id FROM ".$fprefix."members WHERE name=".sqlesc($ipbuser))  or die(mysql_error());

			if(!@mysql_result($check,0)) $ipbid = 66958; else $ipbid=mysql_result($check,0);

			if (!$forumcat['forumid']) { $forumid = sql_query ("SELECT id FROM ".$fprefix."forums WHERE name=".sqlesc($forumcat['name']));
			$forumid = @mysql_result ($forumid,0);
			} else $forumid = $forumcat['forumid'];
			if (!$forumid) $forumid = $CACHEARRAY['not_found_export_id'];

			$topic = sql_query("INSERT INTO ".$fprefix."topics (title, state, posts, starter_id, start_date, last_poster_id, last_post, icon_id, starter_name, last_poster_name, poll_state, last_vote, views, forum_id, approved, author_mode, pinned, moved_to, total_votes, topic_hasattach, topic_firstpost,	topic_queuedposts, topic_open_time,	topic_close_time,	topic_rating_total,	topic_rating_hits) VALUES (".sqlesc($topicname).", 'open', 0, ".$ipbid.", ".time().", ".$ipbid.", ".time().", 0, ".sqlesc($ipbuser).", ".sqlesc($ipbuser).", 0, 0, 0, ".$forumid.", 1, 1, 0, NULL, 0, 0, 0, 0, 0, 0, 0, 0)")  or die(mysql_error());
			$topicid = mysql_insert_id();

			if ($CACHEARRAY['exporttype'] == "wiki")
			$post = sql_query("INSERT INTO ".$fprefix."posts (append_edit, edit_time, author_id, author_name, use_sig, use_emo, ip_address, post_date, icon_id, wiki, post, queued, topic_id, post_title, new_topic, edit_name, post_key, post_parent, post_htmlstate) VALUES
  (0, NULL, ".$ipbid.", ".sqlesc($ipbuser).", 1, 1, ".sqlesc(getip()).", ".time().", 0, ".sqlesc($forumdesc).", '---', 0, ".$topicid.", NULL, 1, NULL, '".md5(microtime())."', 0, 0)") or die(mysql_error());
			else
			$post = sql_query("INSERT INTO ".$fprefix."posts (append_edit, edit_time, author_id, author_name, use_sig, use_emo, ip_address, post_date, icon_id, post, queued, topic_id, post_title, new_topic, edit_name, post_key, post_parent, post_htmlstate) VALUES
  (0, NULL, ".$ipbid.", ".sqlesc($ipbuser).", 1, 1, ".sqlesc(getip()).", ".time().", 0, ".sqlesc($forumdesc).", 0, ".$topicid.", NULL, 1, NULL, '".md5(microtime())."', 0, 0)") or die(mysql_error());

			$postid = mysql_insert_id();

			$updtopic = sql_query ("UPDATE ".$fprefix."topics SET topic_firstpost =".$postid." WHERE tid =".$topicid)  or die(mysql_error());
			$updateforum = sql_query("UPDATE ".$fprefix."forums SET topics =topics+1, posts =posts+1, last_post =".time().", last_poster_id =".$ipbid.", last_poster_name =".sqlesc($ipbuser).", last_title=".sqlesc($topicname).", last_id =".$topicid." WHERE id =".$forumid)  or die(mysql_error());
			$updateuser = sql_query("UPDATE ".$fprefix."members SET posts =posts+1, last_post =".time().", last_activity =".time()." WHERE id=".$ipbid)  or die(mysql_error());
			// updating forum caches
			$statcache = sql_query("SELECT cs_value FROM ".$fprefix."cache_store WHERE cs_key = 'stats'");
			$statcache = mysql_result($statcache,0);
			$statcache = unserialize($statcache);
			$statcache['total_topics']++;
			$statcache = serialize($statcache);
			sql_query("UPDATE ".$fprefix."cache_store SET cs_value=".sqlesc($statcache)." WHERE cs_key='stats'");

			$forumcache = sql_query("SELECT cs_value FROM ".$fprefix."cache_store WHERE cs_key = 'forum_cache'");
			$forumcache = mysql_result($forumcache,0);
			$forumcache = unserialize($forumcache);
			$forumcache[$forumid]['topics']++;
			$forumcache[$forumid]['last_post'] = time();
			$forumcache[$forumid]['last_poster_id'] = $ipbid;
			$forumcache[$forumid]['last_poster_name'] = $ipbuser;
			$forumcache[$forumid]['last_title'] = $topicname;
			$forumcache[$forumid]['last_id'] = $topicid;
			//$forumcache[$forumid]['newest_title'] = $topicname;
			//$forumcache[$forumid]['newest_id'] = $topicid;
			$forumcache = serialize($forumcache);
			sql_query("UPDATE ".$fprefix."cache_store SET cs_value=".sqlesc($forumcache)." WHERE cs_key='forum_cache'") or die(mysql_error());

			// updating caches end

		}
		// closing IPB DB connection
		relconn();
		// connection closed


		sql_query("UPDATE torrents SET topic_id = ".$topicid." WHERE id=".$id)  or die(mysql_error());

		// IPB TOPIC TRANSFER END
	}

	$clearcache = array('block-indextorrents','browse-normal','browse-cat');

	foreach ($clearcache as $cachevalue)
	$CACHE->clearGroupCache($cachevalue);
	$CACHE->clearCache('system','cat_tags');

	sql_query("INSERT INTO checkcomm (checkid, userid, torrent) VALUES ($id, $CURUSER[id], 1)") or sqlerr(__FILE__,__LINE__);
	@sql_query("DELETE FROM files WHERE torrent = $id");

	if ($_POST['nofile']) {
	} else   {
		foreach ($filelist as $file) {
			@sql_query("INSERT INTO files (torrent, filename, size) VALUES ($id, ".sqlesc($file[0]).",".$file[1].")");
		}
	}
	if ($_POST['nofile']) {} else {
		move_uploaded_file($tmpname, "torrents/$id.torrent");

		$fp = fopen("torrents/$id.torrent", "w");
		if ($fp)
		{
			@fwrite($fp, benc($dict['value']['info']), strlen(benc($dict['value']['info'])));
			fclose($fp);
			@chmod($fp, 0644);
		}
	}

	write_log("������� ����� $id ($torrent) ��� ����� ������������� " . $CURUSER["username"],"5DDB6E","torrent");

	/* Email notifs */

	$res = sql_query("SELECT email FROM users WHERE enabled=1 AND notifs LIKE '%[cat{$forumcat['id']}]%' AND notifs LIKE '%[email]%'") or sqlerr(__FILE__, __LINE__);


	$body = <<<EOD
<html>
����� ����� �� {$CACHEARRAY['sitename']}!

��������: $torrent
������ �����: $forumsize
���������: {$forumcat['name']}
�����: {$CURUSER['username']}

���������� � ������:
-------------------------------------------------------------------------------
	$forumdesc
-------------------------------------------------------------------------------

����� ���������� �����, ��������� �� ���� ������:

	{$CACHEARRAY['defaultbaseurl']}/details.php?id=$id

-- 
	{$CACHEARRAY['sitename']}
</html>
EOD;
	$to = "";
	$nmax = 100; // Max recipients per message
	$nthis = 0;
	$ntotal = 0;
	$total = mysql_num_rows($res);
	while ($arr = mysql_fetch_row($res))
	{
		if ($nthis == 0)
		$to = $arr[0];
		else
		$to .= "," . $arr[0];
		++$nthis;
		++$ntotal;
		if ($nthis == $nmax || $ntotal == $total)
		{
			sent_mail($to, $CACHEARRAY['sitename'], $CACHEARRAY['siteemail'],  "����� ����� - $torrent", $body);
			$nthis = 0;
		}
	}


	// header("Location: $CACHEARRAY['defaultbaseurl']/details.php?id=$id");

	stdhead("���� ��������");

	$downlink = "<a title=\"�������\" href=\"download.php?id=$id\"><span style=\"color: red; cursor: help;\" title=\"������� �������-����.\">������� ����</span></a>";

	print ("<div style='width: 100%; border: 1px dashed #008000; padding: 10px; background-color: #D6F3CC'>
<b><font size=2px>�������, ���� ������� ����� ������. �������-���� �������� �� �������.<hr />
������ ����� $downlink � ������ ������� � �������, � ��� �������.</font></b></div>");
	print ("<br />");


	$detalistorr = "torrent_info.php?id=$id";
	$url = "edit.php?id=$id";
	$gettorrent = "details.php?id=$id";

	$editlink = "<center><table class=my_table width=\"100%\" border='0' cellspacing='0' cellpadding='0'>
             <tr>
             <td class=bottom><center><form method=post action=\"$url\"><input type=submit value=\"������������� �������\" style='height: 20px; width: 160px;'></center></form></td>
             <td class=bottom><center><form method=post action=\"$gettorrent\"><input type=submit value=\"������� � �������\" style='height: 20px; width: 160px;'></center></form></td>
             <td class=bottom><center><form method=post action=\"$detalistorr\"><input type=submit value=\"������ ��������\" style='height: 20px; width: 160px;'></center></form></td>
             </tr>
             </table></center>";

	print ("<div style='width: 100%; border: 1px dashed #008000; padding: 10px; background-color: #D6F3CC'>
<b><font size=2px>�������������� ��������:</font></b><hr />
	$editlink</div>");

	stdfoot();

	?>
