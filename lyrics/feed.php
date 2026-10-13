<?php header("Content-Type: application/xhtml+xml; charset=UTF-8");  ?>
<?php print('<?xml version="1.0" encoding="UTF-8"?>' . "\n"); ?>
<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	>
	
<?php

  include("config.php");
  include("query.php");
  $connection_test = connection();
  
  $lang = $_GET['lang'] ?? null;
  if ($lang == "e") { $japanese_names = false; } else if ($lang == "j") { $japanese_names = true; }
  
  
  if ($given_name_first) { $name = "GivenName,' ',FamilyName"; } else { $name = "FamilyName,' ',GivenName"; }
  if ($given_name_first) { $note = "Note,' ',Note2"; } else { $note = "Note2,' ',Note"; }
  
  $result = mysqli_query($db, "SELECT song.SongID SongID, credit.ArtistID ArtistID, song.SeriesID SeriesID,
                            SongTitle, KanjiSongTitle, EnglishSongTitle, SongType, Filename, DateAdded,
                            CONCAT($name) Name, KanjiName KanjiName,
                            CONCAT($note) Note, KanjiNote KanjiNote,
                            Title, KanjiTitle
                            FROM song, artist, series, credit
                            WHERE song.SeriesID=series.SeriesID
                            AND credit.SongID=song.SongID
                            AND credit.ArtistID=artist.ArtistID
                            ORDER BY DateAdded DESC
                            LIMIT 20;"); 

?>

<channel>
  <title>Anime &amp; J-Pop Lyrics Translations by DarkMirage<?php if($japanese_names) { ?> (日本語)<?php } ?></title>
  <description>10 most recently added lyrics translation from DarkMirage</description>
  <link>https://lyrics.darkmirage.com/</link>
  
<?php

  $counter = 0;

  while ($result_row = mysqli_fetch_row($result)) {
  
  if ($buffer[0] == $result_row[0])
  {
    $buffer[10] .= "・".$result_row[10];
    $buffer[9] .= ", ".$result_row[9];
    $keepbuffer = true;
  }
  else if ($counter<10 && $buffer)
  {
    $counter++;
    $keepbuffer = false;
    $has_romaji = is_file($txt_folder . "/" . $buffer[7] . "/romaji.txt");
    $has_english = is_file($txt_folder . "/" . $buffer[7] . "/english.txt");
    $has_kanji = is_file($txt_folder . "/" . $buffer[7] . "/kanji.txt");
    $has_cover = is_file($txt_folder . "/" . $buffer[7] . "/cover.jpg");
    
    if ($japanese_names && $has_kanji) {
      $lyrics = array_map('rtrim', file($txt_folder . "/" . $buffer[7] . "/kanji.txt")); }
    else if ($has_romaji) {
      $lyrics = array_map('rtrim', file($txt_folder . "/" . $buffer[7] . "/romaji.txt")); }
    else if ($has_english) {
      $lyrics = array_map('rtrim', file($txt_folder . "/" . $buffer[7] . "/english.txt")); }

?>

    <item>
      <title><?php if ($japanese_names && $buffer[10]) { echo $buffer[10]; } else { echo $buffer[9]; } ?> - <?php if ($japanese_names && $buffer[4]) { echo $buffer[4]; } else { echo $buffer[3]; } ?></title>
      <link>https://lyrics.darkmirage.com/?SongID=<?php echo $buffer[0]; ?></link>
      <dc:creator>DarkMirage</dc:creator>
      <dc:subject><?php if ($japanese_names && $buffer[14]) { echo $buffer[14]; } else { echo $buffer[13]; } ?></dc:subject>
      <guid isPermaLink="false"><?php echo $buffer[0]; ?></guid>
      <pubDate><?php echo $buffer[8]; ?></pubDate>
      
      <description><![CDATA[<p><strong><?php if ($japanese_names && $buffer[14]) { echo $buffer[14]; } else { echo $buffer[13]; } ?></strong></p>
      
      
      <p><?php
      
      for ($x=0; $x<10; $x++)
      {
      echo $lyrics[$x] . "<br />\n";
      }
      
      ?> [......]<br />
      ( <a href="https://lyrics.darkmirage.com/?SongID=<?php echo $buffer[0]; ?>">View the full lyrics</a> | <a href="https://lyrics.darkmirage.com/print.php?SongID=<?php echo $buffer[0]; ?>">Printable version</a> )
      </p>
      
      ]]></description>
		</item>
		
<?php 
  }
    if (!$keepbuffer) { $buffer = $result_row; }
  } ?>
		
</channel>
</rss>
