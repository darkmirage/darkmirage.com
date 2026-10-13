<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<?php

  include("config.php");
  include("query.php");
  $connection_test = connection();
  $view = in_array($_GET['view'] ?? null, array("title", "artist", "series"), true) ? $_GET['view'] : null;
  $SongID = (int) ($_GET['SongID'] ?? 0);
  $ArtistID = (int) ($_GET['ArtistID'] ?? 0);
  $SeriesID = (int) ($_GET['SeriesID'] ?? 0);
  if (!$view && !$SongID && !$ArtistID && !$SeriesID) { $view = "series"; }
  if ($view != "title" && $view )
  {
    $current_parameter = "&view=" . $view;
  }
  else
  {
    if ($ArtistID)
    {
      $current_parameter = "&ArtistID=" . $ArtistID;
    }
    else if ($SeriesID)
    {
      $current_parameter = "&SeriesID=" . $SeriesID;
    }
  }
  $lang = in_array($_GET['lang'] ?? null, array("e", "j"), true) ? $_GET['lang'] : "";
  if ($lang == "e") { $japanese_names = false; } else if ($lang == "j") { $japanese_names = true; }
  
  if ($SongID)
  {
    $songData = send_query("SongTitle","credit.SongID",$SongID, true);
  }
    
?>

<head profile="http://gmpg.org/xfn/11"><meta charset="utf-8">


<title><?php if ($songData) :
  print $songData["SongTitle"] . " ";
  if ($songData["KanjiSongTitle"]) { print "(" . $songData["KanjiSongTitle"] . ") "; }
  print "by " . $songData["Name1"] . " ";
  if ($songData["KanjiName1"]) { print "(" . $songData["KanjiName1"] . ") "; }
  print "- ";
?>Anime & J-Pop Lyrics Translations by DarkMirage</title>

<meta name="description" content="Series: <?php

if ($japanese_names and $songData["KanjiTitle"]) { print $songData["KanjiTitle"]; }
else { print $songData["Title"]; }

if ($songData["SongType"]) { print " | Note: " . $songData["SongType"]; }
          
?> | Added: <?php echo date("F j, Y", mktime(0, 0, 0, substr($songData["DateAdded"], 5, 2), substr($songData["DateAdded"], 8, 2), substr($songData["DateAdded"], 0, 4))); ?>" />
<?php else : ?>Anime & J-Pop Lyrics Translations by DarkMirage</title>
<meta name="description" content="Translated Japanese song lyrics from anime, games and others. Please credit if you want to use them." />
<?php endif ?>

<link rel="stylesheet" href="<?php get_root() ?>wp-content/themes/default2/style.css" type="text/css" media="screen" />
<link rel="stylesheet" href="song.css" type="text/css" media="screen" />
<link rel="icon" href="https://www.darkmirage.com/favicon.ico" type="image/x-icon" />
<link rel="shortcut icon" href="https://www.darkmirage.com/favicon.ico" type="image/x-icon" /> 
<link rel="alternate" type="application/rss+xml" title="RSS 2.0" href="http://feeds.feedburner.com/animelyrics" />
<link rel="alternate" type="application/rss+xml" title="RSS 2.0 (日本語)" href="http://feeds.feedburner.com/animelyrics2" />

<style type="text/css" media="screen">
<!--
	body { background: url("<?php get_root() ?>wp-content/themes/default2/images/kubrickbgcolor.jpg"); }	
	#page { background: url("<?php get_root() ?>wp-content/themes/default2/images/kubrickbg_white.jpg") repeat-y top; border: none; }
	#header { background: url("<?php get_root() ?>wp-content/themes/default2/images/custom/lyrics.jpg") no-repeat bottom center; }
	#footer { background: url("<?php get_root() ?>wp-content/themes/default2/images/kubrickfooter.jpg") no-repeat bottom; border: none;}
	#header 	{ margin: 0 !important; margin: 0 0 0 1px; padding: 1px; height: 198px; width: 758px; }
	#headerimg 	{ margin: 7px 9px 0; height: 192px; width: 740px; } 
	#sidebar a:hover { text-decoration: none; }
	.description a { color: #fff; }
	.description, h1 { text-align: left; margin: 0 30px; }
-->
</style>

	<script src="http://www.google-analytics.com/urchin.js" type="text/javascript">
  </script>
  <script type="text/javascript">
  _uacct = "UA-655761-1";
  _udn="darkmirage.com";
  urchinTracker();
  </script>

</head>
<body>

<div id="page">

<div id="header">
	<div id="headerimg">
		<h1><a href="https://lyrics.darkmirage.com/">Lyrics Translations</a></h1>
		<div class="description">by <a href="https://www.darkmirage.com/">DarkMirage</a></div>

	</div>
	
</div>

<div style="margin:10px; position:relative; left:6px;">
<script type="text/javascript"><!--
google_ad_client = "pub-8980773669361185";
/* Lyrics Top */
google_ad_slot = "5204221429";
google_ad_width = 728;
google_ad_height = 90;
//-->
</script>
<script type="text/javascript"
src="http://pagead2.googlesyndication.com/pagead/show_ads.js">
</script>
</div>

	<div id="content" class="narrowcolumn">
		<div class="post">
		
		<?php
    
    print "<br />";
    include("songlist.php");
    
    if ($SongID)
    {
      include("songdisplay.php");
      display_song($songData);
    }
    else
    { ?>
            <div class="viewselect">
         
<!-- SiteSearch Google -->
<form method="get" action="https://www.google.com/custom" target="_top">
<table border="0" bgcolor="#ffffff">
<tr><td nowrap="nowrap" valign="top" align="left" height="32">
<a href="https://www.google.com/">
<img src="https://www.google.com/logos/Logo_25wht.gif" border="0" alt="Google"></img></a>
</td>
<td nowrap="nowrap">
<input type="hidden" name="domains" value="lyrics.darkmirage.com"></input>
<input type="text" name="q" size="20" maxlength="255" value="" id="sbi"></input>
<input type="hidden" name="q" value="                                                                               -print.php -display=jpn -lang=j -seriesid -artistid -view="></input>
<input type="submit" name="sa" value="Search" id="sbb"></input>
</td></tr>
<tr>
<td>&nbsp;</td>
<td nowrap="nowrap">
<input type="hidden" name="sitesearch" value="lyrics.darkmirage.com"></input>
<input type="hidden" name="client" value="pub-8980773669361185"></input>
<input type="hidden" name="forid" value="1"></input>
<input type="hidden" name="ie" value="UTF-8"></input>
<input type="hidden" name="oe" value="UTF-8"></input>
<input type="hidden" name="cof" value="GALT:#008000;GL:1;DIV:#336699;VLC:663399;AH:center;BGC:FFFFFF;LBGC:336699;ALC:0000FF;LC:0000FF;T:000000;GFNT:0000FF;GIMP:0000FF;FORID:1"></input>
<input type="hidden" name="hl" value="en"></input>
</td></tr></table>
</form>
<!-- SiteSearch Google -->

            <a href="index.php"><b>main</b></a>
          | <?php if (!$japanese_names) { print "english"; } else { ?><a href="?lang=e<?php print $current_parameter;?>">english</a><?php } ?>
          | <?php if ($japanese_names) { print "japanese"; } else { ?><a href="?lang=j<?php print $current_parameter;?>">japanese</a><?php } ?>
          | <?php if ($view == "title") { print "title"; } else { ?><a href="?lang=<?php echo $lang; ?>&view=title">title</a><?php } ?>
          | <?php if ($view == "artist") { print "artist"; } else { ?><a href="?lang=<?php echo $lang; ?>&view=artist">artist</a><?php } ?>
          | <?php if ($view == "series") { print "series"; } else { ?><a href="?lang=<?php echo $lang; ?>&view=series">series</a><?php } ?>
        </div> 
      <?php
      
      if ($view == "artist" || $ArtistID) { $order_by = "Name1"; include("artistlist.php"); }
      else if ($view == "series" || $SeriesID) { $order_by = "Title"; include("serieslist.php"); }
      $query = $query2 = false;
      if ($ArtistID) { $query="credit.ArtistID"; $query2=$ArtistID; }
      else if ($SeriesID) { $query="series.SeriesID"; $query2=$SeriesID; $order_by = "Title"; }
      send_query($order_by, $query, $query2);
    } 
    
    ?>
  <!-- End of Query -->
    </p>
	</div>
						
			
	
	</div>

<?php if (!$SongID) { ?>
<div id="sidebar">
		<ul>
		
		  <li><h2>RSS Feeds</h2>
      <p><a href="http://feeds.feedburner.com/animelyrics" title="RSS feed for the 10 latest songs added"><img src="./rss.gif" alt="RSS 2.0" /> <span style="position:relative; bottom: 5px;">English</span></a> 
      <a href="http://feeds.feedburner.com/animelyrics2" title="RSS feed for the 10 latest songs added (Japanese ver.)"><img src="./rss.gif" alt="RSS 2.0 (日本語)" /> <span style="position:relative; bottom: 5px;">Japanese</span></a></p>
      </li>
		  

			<li><h2>Menu</h2>
			<ul>
			<li><a href="https://www.darkmirage.com/">Blog</a></li>
			<li><a href="https://www.darkmirage.com/beginners-japanese-lessons-by-darkmirage/">Japanese Lessons</a></li>
			<li><a href="http://www.last.fm/user/DarkMirage/">Last.fm</a></li>
			<li><a href="http://anidb.info/perl-bin/animedb.pl?show=myvotes&amp;uid=32918&amp;orderby=vote&amp;orderdir=1">AniDB Votes</a></li>
			<li><a href="https://www.darkmirage.com/contact/">Contact Me</a></li>
			</ul>
			</li>	

<?php if(false):?>
<li><h2>Advertisement</h2>

<p class="center"><script type="text/javascript"><!--
google_ad_client = "pub-8980773669361185";
google_ad_width = 160;
google_ad_height = 600;
google_ad_format = "160x600_as";
google_ad_type = "text_image";
//2006-12-04: lyrics.darkmirage.com
google_ad_channel = "0254015482";
google_color_border = "FFFFFF";
google_color_bg = "FFFFFF";
google_color_link = "0066CC";
google_color_text = "000000";
google_color_url = "0066CC";
//--></script>
<script type="text/javascript"
  src="http://pagead2.googlesyndication.com/pagead/show_ads.js">
</script></p>

</li><?php endif; ?>
						
		</ul>
	</div>
<?php } ?>


<div id="footer">

<?php if(false) :?>
<p>
<script type="text/javascript"><!--
google_ad_client = "pub-8980773669361185";
google_alternate_color = "FFFFFF";
google_ad_width = 468;
google_ad_height = 60;
google_ad_format = "468x60_as";
google_ad_type = "text_image";
//2006-12-04: lyrics.darkmirage.com
google_ad_channel = "0254015482";
google_color_border = "FFFFFF";
google_color_bg = "FFFFFF";
google_color_link = "0066CC";
google_color_text = "000000";
google_color_url = "0066CC";
//--></script>
<script type="text/javascript"
  src="http://pagead2.googlesyndication.com/pagead/show_ads.js">
</script>
</p>
<?php endif; ?>

	<p>
	   All lyrics are transliterated and translated by <a href="https://www.darkmirage.com/">DarkMirage</a>.<br />
	   You may use them if you credit the source.
	</p>

</div>
</div>

<!-- Gorgeous design by Michael Heilemann - http://binarybonsai.com/kubrick/ -->
	
</body>
</html>
