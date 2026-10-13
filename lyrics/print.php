<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head profile="http://gmpg.org/xfn/11"><meta charset="utf-8">

<?php
  include("config.php");
  $connection_test = connection();
  include("query.php");
  $SongID = (int) ($_GET['SongID'] ?? 0);
  $songData = send_query($order_by, "credit.SongID", $SongID, true);
  $has_romaji = is_file($txt_folder . "/" . $songData['Filename'] . "/romaji.txt");
  $has_english = is_file($txt_folder . "/" . $songData['Filename'] . "/english.txt");
  $has_japanese = is_file($txt_folder . "/" . $songData['Filename'] . "/kanji.txt");
?>
<title><?php if ($songData) 
{
  print $songData["SongTitle"] . " ";
  if ($songData["KanjiSongTitle"]) { print "(" . $songData["KanjiSongTitle"] . ") "; }
  print "by " . $songData["Name1"] . " ";
  if ($songData["KanjiName1"]) { print "(" . $songData["KanjiName1"] . ") "; }
  print "- ";
} ?>Anime & J-Pop Lyrics Translations by DarkMirage</title>
</head>

<body>

<font style="font-family: courier, tahoma, arial; font-size: 12px;">
<?php

  if ($songData)
  {
    if ($has_romaji) { $romaji = array_map('rtrim', file($txt_folder . "/" . $songData['Filename'] . "/romaji.txt")); }
    if ($has_english) { $english = array_map('rtrim', file($txt_folder . "/" . $songData['Filename'] . "/english.txt")); }
    if ($has_japanese) { $kanji = array_map('rtrim', file($txt_folder . "/" . $songData["Filename"] . "/kanji.txt")); }

    print "Source: https://lyrics.darkmirage.com/?SongID=" . $SongID;

    print "<p>";
    print "Title: " . $songData['SongTitle'];
    if ($songData['KanjiSongTitle']) { print " 「" . $songData["KanjiSongTitle"] . "」"; }
    if ($songData["EnglishSongTitle"]) { print "<br />English: " . $songData["EnglishSongTitle"]; }
    print "<br />";
    
            // Artist
        print "By: \n\n";
        $counter = 1;
        while ($songData["Name" . $counter])
        {
          if ($counter>1) { print " &#47; \n"; }
          print $songData["Name" . $counter];
          if ($songData["KanjiName" . $counter]) { print " (" . $songData["KanjiName" . $counter] . ") "; }
          print "\t\t\t";
          $counter++;
        }
        
        if ($display_notes)
        {
          // Character Notes
          if ($songData["Note1"])
          {
            print "<br />As: \n\n";
            $counter = 1;
             while ($songData["Note" . $counter])
            {
              if ($counter>1) { print " \t\t\t&#47; \n"; }
              print $songData["Note" . $counter];      
              if ($songData["KanjiNote" . $counter]) { print " (" . $songData["KanjiNote" . $counter] . ") "; }
              $counter++;
            }
          }
        }
        print "<br />";
    if ($songData['Title'])
    {
      print "From: " . $songData['Title']; 
      if ($songData['KanjiTitle']) { print " 「" . $songData["KanjiTitle"] . "」"; }
      if ($songData["SongType"]) { print " (" . $songData["SongType"] . ") "; }
      print "<br />";
    }
    print "</p>";
    
    if ($has_romaji)
    {
    print "<p>Romaji</p>";
    print "<p style=\"margin-left:20px;\">";
    foreach ($romaji as $line) { print $line . "<br />\n"; }
    print "</p>";
    }
    
    if ($has_english)
    {
      print "<p>English</p>";
      print "<p style=\"margin-left:20px;\">";
      foreach ($english as $line) { print $line . "<br />\n"; }
      print "</p>";
    
    }
    
    if ($has_japanese)
    {
    print "<p>Kanji</p>";
    print "<p style=\"margin-left:20px;\">";
    foreach ($kanji as $line) { print $line . "<br />\n"; }
    print "</p>";
    }
  }

?>
</font>

</body>

</html>
