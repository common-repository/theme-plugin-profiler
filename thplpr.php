<?php
/*
* Plugin Name: Theme and Plugin (T&P) Profiler
* Plugin URI: https://thplpr.wordpress.com/
* Description: This is a development tool to help you find out which functions are slowing down your theme or plugin. It will create a page called T&P Profiler in the Tools menu.
* Version: 0.1
* Author: Christian Jongeneel
* Author URI: www.christianjongeneel.nl
* License: GPLv2 or later
*/


// Known issue: plugin will generate an error warning from usort(). This is an unsolved php issue, see here: https://bugs.php.net/bug.php?id=50688



//	01		Let´s go



// First, register the desired options (see under 02)
	add_action ('admin_init','thplpr_settings_init');

// Second, create the actual options page and hook it into the menu (see under 03)
	add_action ('admin_menu','thplpr_create_admin_menu');

// Continue only if user has told the plugin to execute
	if (true == get_option('thplpr_field_execute'))	{
	
	// Third, start counting immediately after all must use plugins have been loaded (this is the first available hook) (see under 04)

		declare(ticks=1); 											// ticks are generated by php every time a new code block is executed, marked (roughly) by a function call or curly {} brackets 

		register_tick_function ('thplpr_tick_counter'); 	// this function is executed every time a tick is generated.

		$thplpr_profile 				= array(); 					// contains an array of functions (key) and the amount of calls and time (args)
		$thplpr_time 					= microtime(true); 		// contains the current server time
		$thplpr_function_stack 		= array(); 					// contains a stack of functions called (not foolproof)

		add_action('muplugins_loaded', 'thplpr_tick_counter',0);

	// Fourth, display the tick count just before the page generation is finished (see under 05)
		add_action ('shutdown','thplpr_show_profile', 9999);
		}




// 02 	Register options

	// Define and register options
	function thplpr_settings_init () {
		add_settings_section ('thplpr_section','','thplpr_section_callback','thplpr');
		add_settings_field ('thplpr_field_execute', 'Execute plugin', 'thplpr_field_execute_callback', 'thplpr', 'thplpr_section');
		add_settings_field ('thplpr_field_scope', 'Scope', 'thplpr_field_scope_callback', 'thplpr', 'thplpr_section');
		add_settings_field ('thplpr_field_order', 'Order', 'thplpr_field_order_callback', 'thplpr', 'thplpr_section');
		register_setting ('thplpr_option_group', 'thplpr_field_execute');
		register_setting ('thplpr_option_group', 'thplpr_field_scope');
		register_setting ('thplpr_option_group', 'thplpr_field_order');		
		}

	// Declare callback functions
	function thplpr_section_callback () {
		echo '<hr>';
		echo '<h2>Options</h2>';
		}
 
	function thplpr_field_execute_callback () {
		$option = get_option('thplpr_field_execute');
		if (isset($option)) {$checked = checked ($option, true, false);} else {$checked = '';}
		echo '<span class="input-field">';
		echo '<label class="chk-box">';				
		echo '<input name="thplpr_field_execute" type="hidden" value="0" />'; // this line is needed to force the browser to return an unchecked checkbox
		echo '<input name="thplpr_field_execute" type="checkbox" value="1"' . $checked . ' />';
		echo '</label>'; 
		echo '</span>';
		echo '<span class="input-description">Checking this will execute the profiler on every page of the site until it is unchecked. Please note <strong>beware 2<strong>.</span>';	
		}

	function thplpr_field_scope_callback () {
		echo '<span class="input-field"><input name="thplpr_field_scope" type="text" value="' . esc_attr(get_option('thplpr_field_scope')) . '" /></span>';	
		echo '<span class="input-description">This field will limit output to functions with a prefix matching the given string.</span>';		
		}

	function thplpr_field_order_callback () {
		$sort_options = array ('Function Name', 'Calls', 'Total Time', 'Time per Call');
		$current_option = get_option('thplpr_field_order');
		echo '<span class="input-field"><select name="thplpr_field_order">';
		foreach ($sort_options as $sort_option) {
			if ($sort_option == $current_option) {$selected = 'selected = "selected"';}
			else {$selected = '';}
			echo '<option value="' . $sort_option . '" ' . $selected . '>' . $sort_option . '</option>';
			}
		echo '</select></span>';	
		echo '<span class="input-description">Field to sort output by.</span>';		
		}




// 03 	Register and build options page

	function thplpr_create_admin_menu () {
		add_management_page ('Theme and Plugin Profiler','T&P Profiler','manage_options','thplpr','thplpr_build_admin_page');
		add_filter('plugin_action_links_' . plugin_basename( __FILE__ ), 'thplpr_settings_link', 2, 2);
		}		
		
	function thplpr_build_admin_page () {
		echo '<style>
		.wrap {max-width:900px;}
		strong {color:tomato; margin-right:0.6em;}
		p {line-height:1.9em;}
		.input-field {width:250px; display:inline-block; vertical-align:top; line-height:1.9em;}
		.input-description {width:400px; display:inline-block; line-height:1.9em;}
		</style>';
		echo '<div class="wrap">';
		echo '<h1>Theme and Plugin Profiler</h1>';
		echo '<p>This plugin uses PHP ticks to count how often functions are called and how much time is spent executing the code. If your theme or plugin is slow you will be able to see, for instance, whether the problem is caused by one bulky function or numerous calls to a certain function.</p>';
		echo '<p><strong>Beware 1</strong>Output is a table attached to every page that is generated. So, do not use this plugin on a production site. It is really only a development tool.</p>';
		echo '<p><strong>Beware 2</strong>Once you execute this plugin, you will get a usort() warning. This is a <a href="https://bugs.php.net/bug.php?id=50688" target="_blank">known PHP bug</a>, that this plugin can do nothing about, because it really needs PHP´s debug_backtrace function that triggers the bug. As a follow up, you will probably also get a Cannot Modify Header Information error once you try to save the options below. No worries. The options are saved and the plugin is doing fine. Just hit backspace.</p>';
		echo '<p><strong>Beware 3</strong>The time spent in any function is accurate, but the calls count is not entirely reliable. Sometimes the stack that keeps track of the function tree gets confused. When a function calls a subfunction the first function is sometimes counted again as execution returns from the subfunction.</p>';
		echo '<form id="thplpr_form" action="options.php" method="post">';
		settings_fields('thplpr_option_group');
		do_settings_sections('thplpr'); 
		submit_button('Save options', 'primary', 'thplpr_options_submit');		
		echo '</form>';
		echo '<hr>';
		echo '<p>If you find this plugin useful, rating it in the Repository would be appreciated.</p>';
		echo '</div>';
		}

	function thplpr_settings_link ($links) {
	   $links[] = '<a href="'. esc_url( get_admin_url(null, 'tools.php?page=thplpr') ) .'">Settings</a>';
   	return $links;
		}




// 04 	Core functionality


	function thplpr_tick_counter() {
   	global $thplpr_profile, $thplpr_time, $thplpr_function_stack;
		// Get information about the current position in the code
		$bt = debug_backtrace();
		// Get the name of the current function
		$function = $bt[1]['function'];
		// If this is the first time this function is called, generate an array key
		if (!isset($thplpr_profile[$function])) {
			$thplpr_profile[$function]['time'] = 0; // keeps track of the time spent in this function
			$thplpr_profile[$function]['count'] = 0; // keeps track of the amount of calls to this function
			}
		// Increase time spent with the elapsed time since last tick, then set the current time stamp to use next time
		$thplpr_profile[$function]['time'] += (microtime(true) - $thplpr_time);
		$thplpr_time = microtime(true);
		// Do something with the amount of calls only if the current function is a different function from last tick (recursive calls will go unnoticed!)
		if ($thplpr_function_stack == null) {$thplpr_function_stack = array('root');}
		if (end($thplpr_function_stack) != $function) {
			// If execution is back in the previous function, pop the last function from the function stack
			if (prev($thplpr_function_stack) == $function) {
				array_pop($thplpr_function_stack);
				}
			// Else add the current function to the function stack and count one call for the current function
			else {
				$thplpr_function_stack[] = $function;
				$thplpr_profile[$function]['count'] += 1;
				}
			}
		}



// 05 	Display results


	function thplpr_sort_count ($elem1, $elem2) {
		return $elem1['count'] - $elem2['count'];
		}

	function thplpr_sort_time ($elem1, $elem2) {
		// comparison values are rounded to integers in php, so multiplying with large integer to prevent everything becoming zero  
		return 10000000*$elem1['time'] - 10000000*$elem2['time']; 
		}

	function thplpr_sort_percall ($elem1, $elem2) {
		return 10000000*$elem1['time']/$elem1['count'] - 10000000*$elem2['time']/$elem2['count'];
		}

	function thplpr_show_profile() {
		global $thplpr_profile;
		$sort_options 		= array ('Function Name', 'Calls', 'Total Time', 'Time per Call');
		$total_calls 		= 0;
		$total_time 		= 0;
		$limit_scope 		= get_option('thplpr_field_scope');
		$display_order 	= get_option('thplpr_field_order');
		if (!isset($display_order)) {$display_order = $sort_options[0];}
		switch ($display_order) { 
			case $sort_options[0] : ksort ($thplpr_profile); break;
			case $sort_options[1] : uasort($thplpr_profile, 'thplpr_sort_count'); break;		
			case $sort_options[2] : uasort($thplpr_profile, 'thplpr_sort_time'); break;
			case $sort_options[3] : uasort($thplpr_profile, 'thplpr_sort_percall'); break;					
		}
		echo '<style>
		.theme-plugin-profiler {background:white; color:black; padding:2%; font-size:14px;}
		.theme-plugin-profiler.admin {margin-left:200px;}
		.theme-plugin-profiler table {width:auto; border-collapse:collapse;}  	
		.theme-plugin-profiler tr:first-child {font-weight:bold;}
		.theme-plugin-profiler tr:last-child {font-weight:bold;}		
		.theme-plugin-profiler tr:nth-child(even) {background:silver; color:black;}
		.theme-plugin-profiler tr:nth-child(odd) {background:white; color:black;}
		.theme-plugin-profiler td {padding:3px; border:1px solid grey;}
		</style>';
		if (is_admin()) {$class='admin';} else {$class='';}
		echo '<div class="theme-plugin-profiler ' . $class . '"><h1>Results of Theme and Plugin Profiler</h1><table>';
		echo '<tr><td>Function Name</td><td>Execution calls</td><td>Total execution time</td><td>Execution time per call</td></tr>';
		foreach ($thplpr_profile as $key => $args) {
			if ($limit_scope == '' || substr($key,0,strlen($limit_scope)) == $limit_scope) {
				echo '<tr><td>' . $key . '</td><td>' . $args['count'] . '</td><td>' . number_format($args['time'],10) . '</td><td>' . number_format($args['time']/$args['count'],10) . '</td></tr>';
				$total_calls += $args['count'];
				$total_time += $args['time'];
				}
			}
		if ($total_calls != 0) {		
			echo '<tr><td>Total</td><td>' . $total_calls . '</td><td>' . number_format($total_time,10) . '</td><td>' . number_format($total_time/$total_calls,10) . '</td></tr>';
			}
		echo '</table></div>';
		}

?>