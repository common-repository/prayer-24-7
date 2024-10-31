/*****************************************************
** Name:        p247-admin.js
** Author:      David Thompson
** Version:     1.0
** Plugin:      Prayer 24-7
** Description: Handles scripts for admin screen
*****************************************************/

jQuery(document).ready(function($){
    $('.p247-color-field').wpColorPicker();
});

function RestoreColors() {
    jQuery('#colorempty').wpColorPicker('color', '#71ff8d');
    jQuery('#colorchosen').wpColorPicker('color', '#ffa103');
    jQuery('#colorselected').wpColorPicker('color', '#a2d8e8');
    jQuery('#colorexpanded').wpColorPicker('color', '#fff9c9');
}