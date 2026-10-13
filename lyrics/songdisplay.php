<?php

function display_song($songData, $display = NULL)
{
  global $txt_folder, $bg_color, $alternate_bg;

  $has_romaji = is_file($txt_folder . "/" . $songData['Filename'] . "/romaji.txt");
  $has_english = is_file($txt_folder . "/" . $songData['Filename'] . "/english.txt");
  $has_japanese = is_file($txt_folder . "/" . $songData['Filename'] . "/kanji.txt");

  print "\n";
  if (!$display)
  {
    $display = $_GET['display'] ?? null; 
  }
    if (!$display)
  {
    if ($has_romaji) {
    $romaji = array_map('rtrim', file($txt_folder . "/" . $songData['Filename'] . "/romaji.txt")); }
    if ($has_english) {
    $english = array_map('rtrim', file($txt_folder . "/" . $songData['Filename'] . "/english.txt")); }
  }
  else if ($display == "jpn")
  {
    $kanji = array_map('rtrim', file($txt_folder . "/" . $songData["Filename"] . "/kanji.txt"));
  }
  
?>
        <div class="viewselect">
            <a href="index.php"><b>song list</b></a>
          | <?php
              if (!$display)
              {
                if ($has_english && $has_romaji) { print "romaji and english"; }
                else if ($has_romaji) { print "romaji"; } 
                else if ($has_english) {print "english"; }             
              }
              else
              {
                if ($has_english && $has_romaji) { ?><a href="?SongID=<?php print $songData['SongID']; ?>">romaji and english</a><?php }
                else if ($has_romaji) { ?><a href="?SongID=<?php print $songData['SongID']; ?>">romaji</a><?php }
                else if ($has_english) { ?><a href="?SongID=<?php print $songData['SongID']; ?>">english</a><?php }
              }
            ?>
          | <?php if ($display == "jpn" && $has_japanese)
                  {
                    print "japanese | ";
                  }
                  else if ($has_japanese)
                  {
                   ?><a href="?SongID=<?php print $songData['SongID']; ?>&display=jpn">japanese</a> | <?php 
                  } ?>
          <a href="print.php?SongID=<?php print $songData['SongID']; ?>">printable</a>
        </div> 
        
<?php

print "\n\n<table width=\"668\" cellpadding=\"10\" border=\"0\">\n<tr valign=\"top\">";
print "\n\t<td colspan=\"2\">\n";
if (is_file($txt_folder . "/" . $songData['Filename'] . "/cover.jpg"))
{
  print('<img style="float:left; border:1px solid #555; margin:10px;" alt="' . $songData['SongTitle'] . '" src="' . $txt_folder . '/' . $songData['Filename'] . '/cover.jpg' . '" />');
}
if ($kanji) { $japanese_names = true; }
print_song_data($songData, $japanese_names , "fff", true);
print "\n\t</td>\n</tr>\n<tr valign=\"top\">"; 

  if (!$display)
  {
    if ($english && $romaji)
    {
      print "\n\t<td id=\"romaji\">\n";
      print "<table style=\"font-size:1.1em; font-family: Verdana, MS Gothic;\" cellspacing=\"0\" style=\"text-align:left\" border=\"0\"><tr valign=\"top\"><td width=\"50%\">\n";
      
      ////
      $counter_english = 0;
      $counter2 = 1;
      $counter3 = 1;
      for ($counter = 0; $counter <= count($romaji); $counter++)
      {
        if ($romaji[$counter])
        {
           print "\t\t<!--" . sprintf("%02d", $counter2++) . "--> " . $romaji[$counter] . "<br />\n";
        }
        else
        {
          print "\t\t" . $romaji[$counter] . "<br />\n";
        }
        if (!$romaji[$counter])
        {
          print "\n</td></tr><tr><td align=\"left\"";
          print ">\n";
          print "<div";
          if ($alternate_bg)
          {
            print " style=\"color:#06c;\" ";
          }
          print ">\n";
          for ($counter_english; $counter_english <= $counter; $counter_english++)
          { 
            if ($english[$counter_english])
            {
               print "\t\t <!--" . sprintf("%02d", $counter3++) . "--> " . $english[$counter_english] . "<br />\n ";
            }
            else
            {
              print "</div>\t\t" . $english[$counter_english] . "<br />\n";
            }
            if (!$english[$counter_english]) { print "\n</td></tr><tr valign=\"top\"><td>\n"; }
          }
        }

      }
      print "</td></tr></table>";

    }
    ////
    else if ($romaji)
    {
    print "\n\t<td width=\"20\"></td><td id=\"romaji\" width=\"100%\">\n";
    foreach ($romaji as $line) { print "\t\t" . $line . "<br />\n"; }
    print "\n\t</td>\n</tr>";
    }
    else if ($english)
    {
    print "\n\t<td width=\"20\"></td><td id=\"romaji\" width=\"100%\">\n";
    foreach ($english as $line) { print "\t\t" . $line . "<br />\n"; }
    print "\n\t</td>\n</tr>";
    }

  }
  else if ($display == "jpn")
  {
    print "\n\t<td width=\"20\"></td><td width=\"100%\">\n";
    foreach ($kanji as $line) { print "\t\t" . $line . "<br />\n"; }
    print "\n\t</td>\n</tr>";
  }

print "</table>";  

}

?>
