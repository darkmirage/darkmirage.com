<?php

// MySQL configuration information
$hostname = "localhost";
$username = "lyrics";
$password = "spoon";
$database = "lyrics";

    // Connects to the database
    $link = @mysql_connect($hostname, $username, $password);
    if (!link)
    {
      die("Couldn't connect to MySQL:". mysql_error() ."<br />");
    }
    else
    {
      mysql_selectdb($database, $link)
       or die("Couldn't open $database:". mysql_error() ."<br />");
    }
    mysql_query("SET NAMES utf8"); // forces unicode
    
    // Prints song data from an array dumped from one row of the result
    function print_song_data($songData)
    {
      print "<p>";
      print $songData["SongTitle"];
      print "<br /><small>";
      print $songData["Name"]; // prints artist name in romaji and/or English
      if ($songData["KanjiName"]) { print " (" . $songData["KanjiName"] . ")"; } // checks for kanji name and prints
      print "<br />";
      if ($songData["SeriesID"])
      {
        print $songData["Title"]; // checks for series information and prints if applicable
        if ($songData["KanjiTitle"]) { print " (" . $songData["KanjiTitle"] . ")"; } // checks for kanji series title and prints
      }
      print "</p>"; 
    }
    
    // Sends the query and stores it in a resource
    $result = @mysql_query("SELECT SongID, song.ArtistID, song.SeriesID, SongTitle, KanjiSongTitle, Name, KanjiName, Title, KanjiTitle FROM song, artist, series WHERE song.SeriesID=series.SeriesID and song.ArtistID=artist.ArtistID");
    
    // This is a loop to dump one row of the result of the query into an array named $songData
    while ($result_row = mysql_fetch_row($result))
    {
      print "<p>";
      for ($counter=0; $counter < count($result_row); $counter++)
      {
        $songData[mysql_field_name($result, $counter)] = $result_row[$counter];
      }
      // Calls on the function to print out the content of $songData with the proper formatting
      print_song_data($songData);
    }
?>
