<?php

  $number_of_entries = 0;

  function print_artist_data($songData, $japanese_names=false)
    {
      global $lang, $display_notes, $number_of_entries, $alternate_bg, $bg_color, $display_secondary_title;
      
      // Artist
      if ($songData)
      {
        print "\n\n\t\t\t";
        print "<!-- No. " . ++$number_of_entries . "--> ";
        print "<!-- ArtistID " . $songData["ArtistID1"] . "  -->\n";
        print "<div class=\"songentry\"";
        if ($number_of_entries%2 == 1 && $alternate_bg) { print " style=\"background-color: #" . $bg_color . "\""; }
        print "><div class=\"songtitle\">\n";
        print "<a href=\"?lang=$lang&ArtistID=" . $songData["ArtistID1"] . "\">\n";
        if ($japanese_names && $songData["KanjiName1"]) { print $songData["KanjiName1"]; } else { print $songData["Name1"]; }
        print "\n\n</a></div><div class=\"artistinfo\">";
        if ($display_secondary_title)
        {
          /*if ($songData["KanjiName1"] && !$japanese_names)
          {
            print " \n\n <small>";
            print $songData["KanjiName1"];
            print " \n\n</small><br />";
          }
          else*/ if ($songData["KanjiName1"] && $japanese_names)
          {
            print " \n\n <small>";
            print $songData["Name1"];
            print " \n\n</small><br />";
          }
        }
        
        $counter = 1;
        $SongID = $songData["SongID"];
        $SeriesID = $songData["SeriesID"];
        if (!$japanese_names || !$songData["KanjiSongTitle"]) { $SongTitle = $songData["SongTitle"]; } else { $SongTitle = $songData["KanjiSongTitle"]; }
        if (!$japanese_names || !$songData["KanjiTitle"]) { $Title = $songData["Title"]; } else { $Title = $songData["KanjiTitle"]; }
        print "<ul>";
        while (true)
        {
          $counter++;

          print "<li><a href=\"?SongID=" . $SongID . "\">" . $SongTitle . "</a>";
          if ($SeriesID && $SeriesID != 1)
          {
            print " <small>from</small> <a class=\"black\" href=\"?lang=" . $lang . "&SeriesID=" . $SeriesID . "\">" . $Title . "</a>";
            $songData["SongType1"] = $songData["SongType"];
            $temp = $counter - 1;
            if ($songData["SongType" . $temp])
            { print " <small>(<span class=\"songtype\">" . $songData["SongType" . $temp] . "</span>)</small> "; }
          }
          print "</li>";
          $SeriesID = null;
          $Title = null;
          $SongID = $songData["SongID" . $counter];
          $SeriesID = $songData["SeriesID" . $counter];
          if (!$japanese_names || !$songData["KanjiSongTitle" . $counter]) { $SongTitle = $songData["SongTitle" . $counter]; } else { $SongTitle = $songData["KanjiSongTitle" . $counter]; }
          if (!$japanese_names || !$songData["KanjiTitle" . $counter]) { $Title = $songData["Title" . $counter]; } else { $Title = $songData["KanjiTitle" . $counter]; }
          if (!$SongID) { break; }
        } 
        print "</ul>";
        
        print "\n\n</div></div>"; 
      }
      else
      {
        print "No results.";
      }
    }
?>
