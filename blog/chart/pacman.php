<?php
include_once( '../wp-content/plugins/ofc/php-ofc-library/open-flash-chart.php' );
$g = new graph();
$g->bg_colour = '#ffffff';
$g->pie(100,'#000000','{display: none;}');
$data = array('200', '750');
$labels = array('Not Pacman', 'Pacman');
$g->pie_values($data, $labels);
$g->pie_slice_colours( array('#000000','#ffff00') );
$g->set_tool_tip( '#x_label#' );
$g->title( 'Pacman', '{font-size:18px; color: #d01f3c}' );
echo $g->render();
?>
