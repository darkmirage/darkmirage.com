<?php

  function send_query($order_by="SongTitle", $query=false, $query2=false, $return=false)
  {
    global $japanese_names, $given_name_first, $display_default_series, $db;
    if ($query)
    {
      $query = "AND " . $query;
      // $query2 comes straight from the URL; every caller filters on a numeric ID.
      $query = $query . "=" . (int) $query2;
    }
    
    // Checks for name option, sends the query and stores output in resource $result
    if ($given_name_first) { $name = "GivenName,' ',FamilyName"; } else { $name = "FamilyName,' ',GivenName"; }
    if ($given_name_first) { $note = "Note,' ',Note2"; } else { $note = "Note2,' ',Note"; }
                            
    // Sends query             
    $result = mysqli_query($db, "SELECT song.SongID SongID, credit.ArtistID ArtistID1, song.SeriesID SeriesID,
                            SongTitle, KanjiSongTitle, EnglishSongTitle, SongType, Filename, DateAdded,
                            CONCAT($name) Name1, KanjiName KanjiName1,
                            CONCAT($note) Note1, KanjiNote KanjiNote1,
                            Title, KanjiTitle
                            FROM song, artist, series, credit
                            WHERE song.SeriesID=series.SeriesID
                            AND credit.SongID=song.SongID
                            AND credit.ArtistID=artist.ArtistID
                            $query
                            ORDER BY $order_by, SongTitle, ArtistID1"); // $query is an optional argument. The program will print a song list by default
    
    // This is a loop to dump one row of the result of the query into an array named $songData
    $field_names = array_column(mysqli_fetch_fields($result), "name");
    $songData = null;
    $name_counter = 1;
    $song_counter = 1;
    $series_counter = 1;
    while ($result_row = mysqli_fetch_row($result))
    {
      $songDataBuffer = null;
      //
      for ($counter=0; $counter < count($result_row); $counter++)
      {
        if (!$display_default_series && ($field_names[$counter] == "Title" || $field_names[$counter] == "KanjiTitle") && $result_row[2] == 1) { continue; } // checks $display_default_series
        else { $songDataBuffer[$field_names[$counter]] = $result_row[$counter]; }
      }
      
      if ($order_by == "SongTitle")
      {
        if (($songData["SongID"] ?? null) == $songDataBuffer["SongID"])
        {
          $songData["ArtistID" . ++$name_counter] = $songDataBuffer["ArtistID1"];
          $songData["Name" . $name_counter] = $songDataBuffer["Name1"];
          $songData["KanjiName" . $name_counter] = $songDataBuffer["KanjiName1"];
          if ($songDataBuffer["Note1"]) { $songData["Note" . $name_counter] = $songDataBuffer["Note1"]; }
          if ($songDataBuffer["KanjiNote1"]) { $songData["KanjiNote" . $name_counter] = $songDataBuffer["KanjiNote1"]; }
        }
        else
        {
          $name_counter = 1;
          if (!empty($songData["SongID"]) && !$query) { print_song_data($songData, $japanese_names); } // Calls on the function to print out the content of $songData with the proper formatting
          $songData = $songDataBuffer;
        }
      }
      else if ($order_by == "Name1")
      {
        if (($songData["ArtistID1"] ?? null) == $songDataBuffer["ArtistID1"])
        {
          $songData["SongID" . ++$song_counter] = $songDataBuffer["SongID"];
          $songData["SongTitle" . $song_counter] = $songDataBuffer["SongTitle"];
          $songData["KanjiSongTitle" . $song_counter] = $songDataBuffer["KanjiSongTitle"];
          $songData["Title" . $song_counter] = $songDataBuffer["Title"];
          $songData["SeriesID" . $song_counter] = $songDataBuffer["SeriesID"];
          $songData["SongType" . $song_counter] = $songDataBuffer["SongType"];
          $songData["KanjiTitle" . $song_counter] = $songDataBuffer["KanjiTitle"];  
        }
        else
        {
          $song_counter = 1;
          if (!empty($songData["ArtistID1"]) && !$query) { print_artist_data($songData, $japanese_names); } // Calls on the function to print out the content of $songData with the proper formatting
          $songData = $songDataBuffer;
        }      
      }
      else if ($order_by == "Title")
      {
        if (($songData["SeriesID"] ?? null) == $songDataBuffer["SeriesID"])
        {
          $songData["SongID" . ++$series_counter] = $songDataBuffer["SongID"];
          $songData["ArtistID" . $series_counter] = $songDataBuffer["ArtistID1"];
          $songData["SongTitle" . $series_counter] = $songDataBuffer["SongTitle"];
          $songData["KanjiName" . $series_counter] = $songDataBuffer["KanjiName1"];
          $songData["Name" . $series_counter] = $songDataBuffer["Name1"];
          $songData["SongType" . $series_counter] = $songDataBuffer["SongType"];
          $songData["KanjiSongTitle" . $series_counter] = $songDataBuffer["KanjiSongTitle"];
        }
        else
        {
          $series_counter = 1;
          if (!empty($songData["SeriesID"]) && !$query) { print_series_data($songData, $japanese_names); } // Calls on the function to print out the content of $songData with the proper formatting
          $songData = $songDataBuffer;
        }      
      }
    }
    
    if ($return)
    {
      return $songData;
      print "hit";
    }
    else
    {
      if ($order_by == "SongTitle") { print_song_data($songData, $japanese_names); }
      else if ($order_by == "Name1") { print_artist_data($songData, $japanese_names); }
      else if ($order_by == "Title") { print_series_data($songData, $japanese_names); }
    } // returns $songData if requested. Custom queries must return only one row else only the last row will be returned.
  }
?>
