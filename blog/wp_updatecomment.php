<?php

/* This program is free software: you can redistribute it and / or modify it under the terms of the GNU General Public License as published by the Free Software Foundation version 3 of the License. This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details. For a copy of the GNU General Public License see http://www.gnu.org/licenses/gpl.html */

/* Script to recount number of comments in WordPress install. This script has been tested with WordPress 2.6.x. Note that script may fail to run the update for all posts in case the script runs longer than the timeout period specified in your settings. In case that happens, try increasing the timeout limit by editing the php.ini file on your webhost. If you are unable to modify the php.ini file and / or don't have access to it then just say a quick prayer and hit the 'Reload' button on your web browser. */

include('wp-config.php'); // Needed for login details to WordPress database to make necessary changes

function updateCount()
    {
        $posts = mysql_fetch_row(mysql_query("SELECT ID FROM wp_posts ORDER BY ID DESC LIMIT 1")); // Fetch row in WordPress database containing information about post data
        for ($i = 1; $i < ($posts[0] + 1); $i++)
        {

     $comments = mysql_query("SELECT SQL_CALC_FOUND_ROWS comment_ID FROM wp_comments WHERE comment_post_ID = '$i' AND comment_approved = 1;") or die("Failed to calculate number of approved comments"); // Calculate the number of approved comments for a post and store in a variable. If unsuccessful, end program.

     mysql_query("UPDATE wp_posts SET comment_count = '".mysql_num_rows($comments)."' WHERE id = '$i';") or die("Failed to update the number of comments calculated"); // Update the comment count using the comment number fetched earlier. If unsuccessful, end program

     echo "Updated Post #$i - ".mysql_num_rows($comments)." comments <br />"; // Display message to user for each post comment count successfully updated
        }
    }

updateCount();
?>