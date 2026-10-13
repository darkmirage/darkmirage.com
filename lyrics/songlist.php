<?php
    $number_of_entries = 0;

    // Prints song data from an array dumped from one row of the result
    function print_song_data($songData, $japanese_names=false, $bg_color = "f3f3f3", $display_translation=false)
    {
      global $lang, $display_notes, $number_of_entries, $alternate_bg, $display_secondary_title;
      
      // Song
      if ($songData)
      {
        print "\n\n\t\t\t";
        print "<!-- No. " . ++$number_of_entries . "--> ";
        print "<!-- SongID " . $songData["SongID"] . "  -->\n";
        print "<div class=\"songentry\"";
        if ($number_of_entries%2 == 1 && $alternate_bg) { print " style=\"background-color: #" . $bg_color . "\""; }
        print "><div class=\"songtitle\">\n";
        print "<a href=\"?lang=$lang&SongID=" . $songData["SongID"] . "\">\n";
        if ($japanese_names && $songData["KanjiSongTitle"]) { print $songData["KanjiSongTitle"]; } else { print $songData["SongTitle"]; }
        print "\n\n</a></div><div class=\"songinfo\">";
        if ($display_secondary_title)
        {
          if ($songData["KanjiSongTitle"] && !$japanese_names)
          {
            print "<small>jpn</small> \n\n";
            print $songData["KanjiSongTitle"];
            print " \n\n<br />";
          }
          else if ($songData["KanjiSongTitle"] && $japanese_names)
          {
            print "<small>romaji</small> \n\n";
            print $songData["SongTitle"];
            print " \n\n<br />";
          }
        }
        if ($display_translation && $songData["EnglishSongTitle"])
        {
            print "<small>eng</small> \n\n";
            print $songData["EnglishSongTitle"];
            print " \n\n<br />";
        }
        
        // Artist
        print "<small>by</small> \n\n";
        $counter_artist = 1;
        $x = $counter_artist + 2;
        while ($songData["Name" . $counter_artist] || $songData["Name" . $x] )
        {
          if ($counter_artist>1 && $songData["Name" . $counter_artist]) { print " <small>&#47;</small> \n"; }
          print "<a href=\"?lang=$lang&ArtistID=" . $songData["ArtistID" . $counter_artist] . "\">\n";
          if ($japanese_names && $songData["KanjiName" . $counter_artist]) { print $songData["KanjiName" . $counter_artist]; }
          else { print $songData["Name" . $counter_artist]; }
          print "\t\t\t</a>";
          $counter_artist++;
          $x++;
        }
        
        if ($display_notes)
        {
          // Character Notes
          if ($songData["Note1"])
          {
            print "<br /><small>as</small> \n\n";
            $counter_artist = 1;
             while ($songData["Note" . $counter_artist])
            {
              if ($counter_artist>1) { print " \t\t\t<small>&#47;</small> \n"; }
                    
              if ($japanese_names && $songData["KanjiNote" . $counter_artist]) { print $songData["KanjiNote" . $counter_artist]; }
              else { print $songData["Note" . $counter_artist]; }
              $counter_artist++;
            }
          }
        }
        
        // Series
        if ($songData["Title"])
        {
          print "\t\t\t<br /><small>from</small> \n\n";
          print "<a href=\"?lang=$lang&SeriesID=" . $songData["SeriesID"] . "\">\n";
          if ($japanese_names and $songData["KanjiTitle"]) { print $songData["KanjiTitle"]; }
          else { print $songData["Title"]; }
          print "\t\t\t</a>";
          if ($songData["SongType"])
          { print " (<span class=\"songtype\">" . $songData["SongType"] . "</span>) "; }
        }
        print "\n\n</div></div>"; 
      }
      else
      {
        print "No results.";
      }
    }
    
?>
