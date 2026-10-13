<?php

require_once('admin.php');

if ($user_level < 1)
{ die('No rights for this - go away...'); }

require_once('admin-header.php');

if (isset($_GET['del_id']))
{
	print "<div class=\"updated\">";
	Randomize_DelRand($_GET['del_id']);
        print "</div>";
}

if (isset($_GET['add']))
{
print "<div class=\"updated\">";
if ($_POST['category'] == "[New Category]")
{
	if ($_POST['NewCat'] == "" || $_POST['NewCat'] == " " || $_POST['NewCat'] == "New Category (if selected)")
	{ _e('<b><font color="red">ERROR:</font></b> Not appeded - please remember to write a categoryname in the New Category field...'); }
	else
	{ Randomize_AddRand($_POST['NewCat'], $_POST['string']); }
}
else
	{ Randomize_AddRand($_POST['category'], $_POST['string']); }
print "</div>";
}

if (isset($_GET['sort']))
{
	if ($_GET['sort'] == "cat")
		{
		$sortstring = " - sorted by category";
		$sqlsort = " ORDER BY category_id";
		}
	else if ($_GET['sort'] == "val")
		{
		$sortstring = " - sorted by value";
		$sqlsort = " ORDER BY random_string";
		}
	else
		{
		$sortstring = " - sorted by ID";
		$sqlsort = " ORDER BY id";
		}

	if ($_GET['order'] == "asc")
		{
		$sortstring .= " in ascending order...";
		$sqlsort .= " ASC";
		}
	else
		{
                $sortstring .= " in descending order...";
                $sqlsort .= " DESC";
		}
}
else
		$sortstring = " - sorted by ID...";
	
?>
<div class="wrap">
	<h2>
	<?php
	_e('Randomize entries');
	if (isset($sortstring))
		echo "<small>" . $sortstring . "</small>";?>
	</h2>
	<?php
		$entries = Randomize_GetEntries($sqlsort);
		$num=count($entries);
		if ($num > 0)
		{
?><table width="100%" cellpadding="3" cellspacing="3">
	<tr class="alternate"> <td scope="col" width="10%"><b>ID</b> &nbsp; <small><a href="?sort=id&order=asc">+</a> / <a href="?sort=id&order=desc">--</a></small></td>
	     <td scope="col" width="15%"><b>Category</b> &nbsp; <small><a href="?sort=cat&order=asc">+</a> / <a href="?sort=cat&order=desc">--</a></small></td>
	     <td scope="col" width="65%"><b>Value</b> &nbsp; <small><a href="?sort=val&order=asc">+</a> / <a href="?sort=val&order=desc">--</a></small></td>
	     <td scope="col" width="10%"><b>Delete?</b> </td>
	</tr><?php
		 for ($i = 0; $num > $i; $i++)
			{
			$temp = Randomize_GetRand($entries[$i]);
			?>
	<tr <?php if($i%2) { print "class=\"alternate\""; } ?>>
		<td width="10%"><?php echo $temp['id']; ?></td>
		<td width="15%"><?php echo $temp['category_id']; ?> </td>
		<td width="65%"><?php echo $temp['random_string']; ?> </td>
		<td width="10%"><a href='rand.php?del_id=<?php echo $entries[$i]; ?>'>Delete</a></td>
<?			}		
		?></tr></table><?php
		}
		else
		{ _e('No Randomize entries found... - enter some data using the form below...'); }
	?>
</div>

<div class="wrap">
	<h2><?php _e('Insert new Randomize entry');?></h2>

   <form name="addrand" id="addrand" action="rand.php?add=1" method="post">
<table width="100%" cellpadding="3" cellspacing="3">
	<tr class="alternate">
		<td scope="col" width="25%"><b>Category</b></td>
	     	<td scope="col" width="60%"><b>Value</b></td>
	     	<td scope="col" width="15%"><b>&nbsp;</b></td>
	</tr>
	<tr>
		<td scope="col" width="25%">
        <select name="category" class="postform">
		<?php
		$cats = Randomize_GetCategories();
		$num_cats = count($cats);
			for ($i = 0; $num_cats > $i; $i++)
				echo "<option value=\"" . $cats[$i]['category_id'] . "\">" . $cats[$i]['category_id'] . "</option>";
		?>
        <option value='[New Category]'><?php _e('[New Category]') ?></option>
</select>
<input type="text" size="25" value="New Category (if selected)" name="NewCat" onfocus="this.value=''">
	</td>
	     	<td scope="col" width="60%" valign="top">
		<textarea cols="60" onfocus="this.value=''" rows="3" name="string">Enter Randomize text...</textarea></td>
		<td scope="col" width="15%" valign="bottom">
		<input type="submit" name="submit" value="Add Entry &raquo;" />
</td>
	</tr></table>
	</form>
</div>

<?  require_once('admin-footer.php'); ?>
