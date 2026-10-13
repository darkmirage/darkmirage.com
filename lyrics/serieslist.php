<?php

  $number_of_entries = 0;

  function print_series_data($songData, $japanese_names=false)
    {
      global $lang, $display_notes, $number_of_entries, $alternate_bg, $bg_color, $display_secondary_title, $display_default_series;
      
      // Series
      if ($songData && $songData["SeriesID"]!=1 && !$display_default_series || $songData && $display_default_series)
      {
        print "\n\n\t\t\t";
        print "<!-- No. " . ++$number_of_entries . "--> ";
        print "<!-- SeriesID " . $songData["SeriesID"] . "  -->\n";
        print "<div class=\"songentry\"";
        if ($number_of_entries%2 == 1 && $alternate_bg) { print " style=\"background-color: #" . $bg_color . "\""; }
        print "><div class=\"songtitle\">\n";
        print "<a href=\"?lang=$lang&SeriesID=" . $songData["SeriesID"] . "\">\n";
        if ($japanese_names && $songData["KanjiTitle"]) { print $songData["KanjiTitle"]; } else { print $songData["Title"]; }
        print "\n\n</a></div><div class=\"artistinfo\">";
        if ($display_secondary_title)
        {
          /*if ($songData["KanjiTitle"] && !$japanese_names)
          {
            print " \n\n <small>";
            print $songData["KanjiTitle"];
            print " \n\n</small><br />";
          }
          else*/ if ($songData["KanjiTitle"] && $japanese_names)
          {
            print " \n\n <small>";
            print $songData["Title"];
            print " \n\n</small><br />";
          }
        }
        
        $counter = 1;
        $SongID = $songData["SongID"];
        $ArtistID = $songData["ArtistID1"];
        if (!$japanese_names || !$songData["KanjiSongTitle"]) { $SongTitle = $songData["SongTitle"]; } else { $SongTitle = $songData["KanjiSongTitle"]; }
        if (!$japanese_names || !$songData["KanjiName1"]) { $Name = $songData["Name1"]; } else { $Name = $songData["KanjiName1"]; }
        print "<ul>";
        while (true)
        {
          $counter++;
          
          if ($SongID != $BufferSongID)
          {
            print "<li><a href=\"?SongID=" . $SongID . "\">" . $SongTitle . "</a>";
            $songData["SongType1"] = $songData["SongType"];
            $temp = $counter - 1;
            if ($songData["SongType" . $temp])
            { print " <small>(<span class=\"songtype\">" . $songData["SongType" . $temp] . "</span>)</small> "; }
            print " <small>by</small> <a class=\"black\" href=\"?lang=" . $lang . "&ArtistID=" . $ArtistID . "\">" . $Name . "</a>";
          }
          else
          {
            print " <small>|</small> <a class=\"black\" href=\"?lang=" . $lang . "&ArtistID=" . $ArtistID . "\">" . $Name . "</a>";
          }

          $ArtistID = null;
          $Title = null;
          $BufferSongID = $SongID;
          $SongID = $songData["SongID" . $counter];
          
          if ($SongID != $BufferSongID) { print "</li>"; }
          
          $ArtistID = $songData["ArtistID" . $counter];
          if (!$japanese_names || !$songData["KanjiSongTitle" . $counter]) { $SongTitle = $songData["SongTitle" . $counter]; } else { $SongTitle = $songData["KanjiSongTitle" . $counter]; }
          if (!$japanese_names || !$songData["KanjiName" . $counter]) { $Name = $songData["Name" . $counter]; } else { $Name = $songData["KanjiName" . $counter]; }
          if (!$SongID) { break; }
        } 
        print "</ul>";
        
        print "\n\n</div></div>"; 
      }
      else if ($songData["SeriesID"]!=1)
      {
        print "No results.";
      }
    }
?>
