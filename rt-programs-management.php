<?php
	/**
	* Plugin Name: Programs Section Management
	* Plugin URI: http://gr8-code.com
	* Description: This plugin allows custom management of the Programs pages.
	* Version: 0.2.4.9
	* Author: Russell Thompson
	* Author URI: http://gr8-code.com
	* License: GPL2
	*/
	
	 /*
	 | version record:
	 | 0.2 - add program roster functionality 12/16/2017
	 | 0.2.3 - functionality added - custom carat items for carat section 11/06/2018
	 | 0.2.4 - change roster filter functionality, filter by postmeta keys "display_start_month", "display_start_day", "display_start_year"
	 | 0.2.4.1 - 02/13/2019 add check to make sure programs listed are all of "program_type" = "our"
	 | 0.2.4.2 - 02/22/2019 add 'export to csv' functionality
	 | 0.2.4.3 - 02/27/2019 add 'display start date' to the programs dropdown lists
     	 | 0.2.4.4 - 03/11/2019 fix the display of programs and related woocommerce order items
     	 | 0.2.4.5 - 03/31/2019 change the way the CE functionality works - make CE choice a dropdown of all CE products, change CE hours as well
     	 | 0.2.4.6 - 03/31/2019 roster - subtract discounts from each buyers total for order
     	 | 0.2.4.7 - 03/31/2019 roster - change the CEs so that they are only those associated with the program of the roster
     	 | 0.2.4.8 - 04/09/2019 roster - change the way the 'promo30' discount stuff works. add code to make sure the mat'l item for the given program is included. also, change the way 'total' works with the promo30 discount
     	 | 0.2.4.9 - 04/17/2019 roster - add new functionality to process coupons and use only when their criteria is meet for current roster
	 */

    use SimpleExcel\SimpleExcel; // for use with export to excel / CSV file see 'function generate_csv()'

	if (!defined("ABSPATH"))
	{
		die();
	}
	
	//define("RT_PROGRAMS_MANAGEMENT_PLUGIN_PATH", plugins_url("", "rt-programs-management"));
	
	function programs_management_script_enqueue()
	{
		wp_register_script("rt_programs_management_script", plugins_url("/js/rt-programs-management.js", __FILE__), array("jquery"), "11-2018");
		wp_localize_script("rt_programs_management_script", "programsManagementAjax", array("ajaxurl" => admin_url("admin-ajax.php")));
		
		wp_enqueue_script("jquery");
		wp_enqueue_script("rt_programs_management_script");
	} // end programs_management_script_enqueue function
	
	add_action("init", "programs_management_script_enqueue");
	
	function create_program_page()
	{
		register_post_type("program_pages", array(	"labels" => array(	"name" => "Program Pages",
															"singular_name" => "Program Page",
															"add_new" => "Add New",
															"add_new_item" => "Add New Program Page",
															"edit" => "Edit",
															"edit_item" => "Edit Program Page",
															"new_item" => "New Program Page",
															"view" => "View",
															"view_item" => "View Program Page",
															"search_items" => "Search Program Pages",
															"not_found" => "No Program Pages Found",
															"not_found_in_trash" => "No Program Pages Found in Trash",
															"parent" => "Parent Program Page"),
											"public" => true,
											"menu_position" => 5, // puts it below posts, to position below pages use 20
											"supports" => array(	"title",
																"comments",
																"thumbnail",),
											"taxonomies" => array(""),
											"has_archive" => true)
						);
	} // end create_program_page function
	
	add_action("init", "create_program_page");

	/* * this code adds the css to the page header when needed         * 
	   * call this function each time needed to display on page        * */
	
	function fits_programs_custom_css()
	{
		?>
			<style type="text/css">
				.defaultCarat_sortable, .customCarat_sortable {
					list-style-type: none;
					margin: 0;
					padding: 0;
					width: 90%;
					cursor: move;
				}

				.defaultCarat_sortable li, .customCarat_sortable li {
					margin: 0 3px 3px 3px;
					padding: 0.4em;
					padding-left: 1.5em;
					font-size: 1.1em;
					height: 18px;
				}

				.defaultCarat_sortable li span, .customCarat_sortable li span {
					position: absolute;
					margin-left: -1.3em;
				}
				
				.customCarat_sortable li span.carat-edit {
					position: relative;
					float: right;
					right: 40px;
				}

				.customCarat_sortable li span.carat-delete {
					position: relative;
					float: right;
					right: 20px;
				}
			</style>
		<?php
	}
	
	add_action("admin_head", "fits_programs_custom_css");

	function program_pages_admin()
	{
		add_meta_box("program_page_meta_box", "Program Details", "display_program_page_meta_box", "program_pages", "normal", "high");
		add_meta_box("program_material_discount", "Add Material Discount", "display_program_material_discount", "program_pages", "normal", "default"); // added 08-09-2017
	} // end program_pages_admin function
	
	add_action("admin_init", "program_pages_admin");
	
	
	/* * add submenu items * */
	function program_pages_submenu()
	{
		// originally, the $capabilities arg (5th arg) was "manage_options." being changed to "level_7"
		add_submenu_page("edit.php?post_type=program_pages", "Program Roster", "Program Roster", "level_7", "program_generate_roster", "generate_program_rosters");
		add_submenu_page("edit.php?post_type=program_pages", "Carat Management", "Carat Management", "level_7", "program_carat_management", "generate_carat_management_pg");
	} // end program_pages_submenu function
	
	add_action("admin_menu", "program_pages_submenu");
	
	
	/* * code for program roster page. added for v0.2 12/16/2017 * */
	/* * code for program roster date range added for v0.2.1 12/27/2017 * */
	// display for pages that generate program rosters
	function generate_program_rosters()
	{
		global $wpdb;
		$program_select_options = ""; // stores program select options - ID and title
		
		// options for the date selects
		$currentYear = date("Y");
		$select_year = "<option value=\"choose\">Year</option>
					<option value=\"".($currentYear - 2)."\">".($currentYear - 2)."</option>
					<option value=\"".($currentYear - 1)."\">".($currentYear - 1)."</option>
					<option value=\"".$currentYear."\">".$currentYear."</option>
					<option value=\"".($currentYear + 1)."\">".($currentYear + 1)."</option>
					<option value=\"".($currentYear + 2)."\">".($currentYear + 2)."</option>";
		
		$select_month =	"<option value=\"choose\">Month</option>
						<option value=\"1\">1 - January</option>
						<option value=\"2\">2 - February</option>
						<option value=\"3\">3 - March</option>
						<option value=\"4\">4 - April</option>
						<option value=\"5\">5 - May</option>
						<option value=\"6\">6 - June</option>
						<option value=\"7\">7 - July</option>
						<option value=\"8\">8 - August</option>
						<option value=\"9\">9 - September</option>
						<option value=\"10\">10 - October</option>
						<option value=\"11\">11 - November</option>
						<option value=\"12\">12 - December</option>";
		
		$select_day =	"<option value=\"choose\">Day</option>
					<option value=\"1\">1</option>
					<option value=\"2\">2</option>
					<option value=\"3\">3</option>
					<option value=\"4\">4</option>
					<option value=\"5\">5</option>
					<option value=\"6\">6</option>
					<option value=\"7\">7</option>
					<option value=\"8\">8</option>
					<option value=\"9\">9</option>
					<option value=\"10\">10</option>
					<option value=\"11\">11</option>
					<option value=\"12\">12</option>
					<option value=\"13\">13</option>
					<option value=\"14\">14</option>
					<option value=\"15\">15</option>
					<option value=\"16\">16</option>
					<option value=\"17\">17</option>
					<option value=\"18\">18</option>
					<option value=\"19\">19</option>
					<option value=\"20\">20</option>
					<option value=\"21\">21</option>
					<option value=\"22\">22</option>
					<option value=\"23\">23</option>
					<option value=\"24\">24</option>
					<option value=\"25\">25</option>
					<option value=\"26\">26</option>
					<option value=\"27\">27</option>
					<option value=\"28\">28</option>
					<option value=\"29\">29</option>
					<option value=\"30\">30</option>
					<option value=\"31\">31</option>";
		
		// $sql = "SELECT ID, post_title FROM wp_posts WHERE post_type = 'program_pages' AND post_status = 'publish' ORDER BY post_date DESC";
		// 02/13/2019 change the sql statement to include code to get only "our" programs
		// 02/27/2019 change the program listings in the dropdown - add display start date
		$sql = "SELECT P.ID, P.post_title FROM wp_posts AS P LEFT JOIN wp_postmeta AS PM on PM.post_id = P.ID WHERE P.post_type = 'program_pages' AND P.post_status = 'publish' AND PM.meta_value = 'our' ORDER BY P.post_date DESC";
		$getPrograms = $wpdb->get_results($sql);
		
		if ($getPrograms != FALSE)
		{
			foreach($getPrograms AS $theProgram)
			{
				// get the program's date form wp_postmeta
				$startMonth_sql = "SELECT meta_value FROM wp_postmeta WHERE post_id = ".$theProgram->ID." AND meta_key = 'display_start_month'";
				$getStartMonth = $wpdb->get_results($startMonth_sql);

				if ($getStartMonth != FALSE)
				{
					foreach($getStartMonth AS $theStartMonth)
					{
						$numericStartMonth = $theStartMonth->meta_value;
					}

					$startMonthBool = TRUE;
				}

				$startDay_sql = "SELECT meta_value FROM wp_postmeta WHERE post_id = ".$theProgram->ID." AND meta_key = 'display_start_day'";
				$getStartDay = $wpdb->get_results($startDay_sql);

				if ($getStartDay != FALSE)
				{
					foreach($getStartDay AS $theStartDay)
					{
						$numericStartDay = $theStartDay->meta_value;
					}

					$startDayBool = TRUE;
				}

				$startYear_sql = "SELECT meta_value FROM wp_postmeta WHERE post_id = ".$theProgram->ID." AND meta_key = 'display_start_year'";
				$getStartYear = $wpdb->get_results($startYear_sql);

				if ($getStartYear != FALSE)
				{
					foreach($getStartYear AS $theStartYear)
					{
						$numericStartYear = $theStartYear->meta_value;
					}

					$startYearBool = TRUE;
				}

				if($startMonthBool && $startDayBool && $startYearBool)
				{
					$useStartDate = date("F d, Y", mktime(0,0,0,$numericStartMonth,$numericStartDay,$numericStartYear));
					// $useStartDate = $numericStartMonth." - ".$numericStartDay." - ".$numericStartYear;
					$program_select_options .= "<option value=\"".$theProgram->ID."\">".$theProgram->post_title." (".$useStartDate.")</option>";
				}
				else
				{
					$program_select_options = "<option value=\"error\">error with date</option>";
				}
			}
			
			$nonce = wp_create_nonce("get_roster_nonce");
			$link_ajaxGetRoster = admin_url("admin-ajax.php?action=generate_roster_list&nonce=".$nonce);
            $link_ajaxNarrowProgramList = admin_url("admin-ajax.php?action=narrow_program_list&nonce=".$nonce);
            $link_ajaxGenCSV = admin_url("admin-ajax.php?action=generate_csv&nonce=".$nonce);
			?>
				<div class="wrap">
					<div id="search_form_container">
						<form id="narrowProgramList" name="narrowProgramList" action="" method="post">
							<h2>Program Rosters</h2>
							<div style="background-color: #ffffff; padding: 1px 5px 1px 5px; margin-bottom: 10px;">
								<p>By default, the drop-down menu below lists all published programs in the system. Program titles are listed in order of their ad creation date, with the most recent program created at the top.</p>
                                				<p>To Narrow the listing field, enter a date range in the section below and then click on the "Narrow Program List" button.</p>
							</div>
							<label for="startMonth">Start Date:</label>&nbsp;&nbsp;<select id="startMonth" name="startMonth"><?php echo $select_month; ?></select>&nbsp;&nbsp;<select id="startDay" name="startDay"><?php echo $select_day; ?></select>&nbsp;&nbsp;<select id="startYear" name="startYear"><?php echo $select_year; ?></select>&nbsp;&nbsp;To&nbsp;&nbsp;<label for="endMonth">End Date:</label>&nbsp;&nbsp;<select id="endMonth" name="endMonth"><?php echo $select_month; ?></select>&nbsp;&nbsp;<select id="endDay" name="endDay"><?php echo $select_day; ?></select>&nbsp;&nbsp;<select id="endYear" name="endYear"><?php echo $select_year; ?></select>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="<?php echo $link_ajaxNarrowProgramList; ?>" id="narrow_programs_list" name="narrow_programs_list" class="button button-primary button-large" data-nonce="<?php echo $nonce; ?>" type="submit">Narrow Program List</a><br />
							<br />
						</form>
						<form id="getProgram" name="getProgram" action="" method="post">
							<label for="program_select">To view a program roster, select the program title from the drop-down menu and click on the "Show Roster" button.</label><br />
							<span id="program_list_message"></span>
							<select id="program_select" name="program_select">
								<option value="">Program list...</option>
								<?php echo $program_select_options; ?>
							</select>
							<a href="<?php echo $link_ajaxGetRoster; ?>" id="show_roster" name="show_roster" class="button button-primary button-large" data-nonce="<?php echo $nonce; ?>" type="submit">Show Roster</a>
						</form>
					</div>
					<hr />
					<div id="data_list"></div>
                    <!-- 04/10/2019 - add "export to csv" functionality -->
                    <hr />
                    <div id="csvForm_container">
                        <div id="csv_result_message"></div>
                        <form id="csvForm" action="">
                            <input type="hidden" id="csv_post_id" name="csv_post_id" value="" />
                            <input type="hidden" id="csv_product_id" name="csv_product_id" value="" />
                            <a href="<?php echo $link_ajaxGenCSV; ?>" id="submit_csv" name="submit_csv" class="button button-primary button-large" data-nonce="<?php echo $nonce; ?>" type="submit" disabled="disabled">Export as CSV</a>
                            <a href="<?php echo $link_ajaxGenCSV; ?>" id="submit_xls" name="submit_xls" class="button button-primary button-large" data-nonce="<?php echo $nonce; ?>" type="submit" disabled="disabled">Export as XML</a>
                        </form>
                    </div><!-- end csvForm_container -->
				</div>
			<?php
		}
		else
		{
			?>
				<div class="wrap">
					<strong>Database query returned nothing - return message 01a.</strong>
				</div>
			<?php
		}
	} // end generate_program_rosters function
	
	add_action("wp_ajax_generate_roster_list", "generate_roster_list"); // for listing participants in program
	add_action("wp_ajax_nopriv_generate_roster_list", "roster_user_must_login"); // for listing participants in program
	add_action("wp_ajax_narrow_program_list", "narrow_program_list"); // for narrowing list of programs for select
	add_action("wp_ajax_nopriv_narrow_program_list", "roster_user_must_login"); // for narrowing list of programs for select
	
	function generate_roster_list()
	{
		global $wpdb;
		$nonce = wp_create_nonce("get_roster_nonce");
		$returnThis = array(); // return the results to AJAX
		//$rosterArray = array(); // this array holds the info for participants that will be displayed as the roster. it will be inside the $returnThis array
		$content = ""; // this will be inserted as the value of each rosterArray key
		
		if (!wp_verify_nonce($_REQUEST['nonce'], "get_roster_nonce"))
		{
			// exit("Access denied.");
			// for testing ->
			header("Location: ".$_SERVER['HTTP_REFERER']); // if nonce is not being verified, this should refresh the page... visual feedback.
		}
		
		$requestedProgramId = $_REQUEST['post_id'];
		
		// the product id is stored in wp_postmeta with the program post's id
		$first_sql = "SELECT * FROM wp_postmeta WHERE post_id = ".$requestedProgramId." AND meta_key = 'shopping_product_id'";
		$getProductInfo = $wpdb->get_results($first_sql);
		
		if($getProductInfo != FALSE)
		{
			foreach($getProductInfo AS $theProductInfo)
			{
				$product_id = $theProductInfo->meta_value;
				$returnThis['product_id'] = $product_id;
			}
			
			$orders = rt_get_orders_by_product_id($product_id);
			
			/* for testing ->
			$returnThis['type'] = "success";
			$content .= "Object content:<br />
						".var_dump($orders)."<br />";
			end testing */
			
			// loop through orders
			// original test -> if (!empty($orders))
			
			// test to see if there is anything in the $orders object
			//$testArray = (array)$orders; // cast object to array
			//if(!$testArray) // test if anything in array
			if(!empty($orders))
			{
				$returnThis['type'] = "success";
				
				$content .= "	Product ID: ".$returnThis['product_id']."<br />
							<table style=\"width: 100%;\">
								<thead>
									<tr>
										<th>Order ID</th>
										<th>OrderDate</th>
										<th>Customer Name</th>
										<th>Phone</th>
										<th>Email</th>
										<th>City</th>
										<th>Profession</th>
										<th><center>Program Qty</center></th>
										<th><center>Program $</center></th>
										<th><center>Manual Qty</center></th>
										<th><center>Manual $</center></th>
										<th><center><center>DVD Qty</center></th>
										<th><center>DVD $</center></th>
										<th><center>CEU $</center></th>
										<th><center>Promo Code(s)</center></th>
										<th><center>Total $</center></th>
									</tr>
								</thead>
								<tbody>";
				
				if ($orders == "query_fail")
				{
					$content .= "	<tr>
									<td colspan=\"7\">
										Query failed in 'rt_get_orders_by_product_id'
									</td>
								</tr>";
				}
				else
				{
					$countOrderLoop = 0;
					
					// test to see if there is anything in the $orders object
					/*$testArray = (array)$orders; // cast object to array
					if(!$testArray) // test if anything in array
					{*/
						foreach($orders->posts AS $order_id)
						{
							/* for testing
							$content .= "	<tr>
											<td colspan=\"7\">
												The foreach loop is entered.
											</td>
										</tr>";
							end test code */
							
							$order = new WC_Order($order_id);
							
							// get custom checkout fields
							$profession = get_post_meta($order_id, "checkout_profession", true);
							// get metafields associated with the PROGRAM
                            $ceu_with_order = get_post_meta($requestedProgramId, "ceu_variant", true);
                            $ceu_product_id = get_post_meta($requestedProgramId, "ceu_product_id", true);
							$material_with_order = get_post_meta($requestedProgramId, "material_product_ids", true); // this is a comma seperated string
							$program_discount = get_post_meta($requestedProgramId, "include_discount", true); // this will be 'yes' if discount assoc with program
							$program_discount_amount = get_post_meta($requestedProgramId, "discount_percent", true);

                            $materialArray = explode(",", $material_with_order);
                            
                            // take care of orders before the 'ceu_product_id' existed
                            if($ceu_with_order && !$ceu_product_id)
                            {
                                $ceu_product_id = 8928;
                            }
							
							$countOrderLoop++;
							
							if($order)
							{
								// format addresses
								$address = array(	"first_name"	=> $order->billing_first_name,
												"last_name"	=> $order->billing_last_name,
												"company"		=> $order->billing_company,
												"address_1"	=> $order->billing_address_1,
												"address_2"	=> $order->billing_address_2,
												"city"		=> $order->billing_city,
												"state"		=> $order->billing_state,
												"postcode"	=> $order->billing_postcode
												);
								
								$formatted_billing_address = WC()->countries->get_formatted_address($address);
								
								// get order items
								$itemTypes = array("line_item", "fee", "coupon");
								$items = $order->get_items($itemTypes);
								// for test -> $itemResult = $items ? "items works" : "fail";
								// for test ->
								$numOfItems = count($items);

								if($items)
								{   
									$countItemsLoop = 0;
									$countItemLoop = 0;
									$lineItemCount = 0;
									$couponItemCount = 0;
									$feeItemCount = 0;
									$manualItemCount = 0;
									$dvdItemCount = 0;
									$programItemCount = 0;
                                    $ceuItemCount = 0;
                                    $programTotalDollars = 0;
									$programArray = array(); // this will hold the program qty and total $
									$manualArray = array(); // this will hold manual product qty and total $
									$dvdArray = array(); // this will hold dvd product qty and total $
                                    $ceuArray = array(); // this will hold the ceu info
                                    $feeArray = array(); // this holds fee or 'discount' info
									$couponArray = array(); // this will hold the coupon info - this is the special 'fee' discount + coupons
                                    $itemDump = array(); // for test
                                    $programTotalItemDollars = array(); // this holds the total for program and related items per line
                                    $feeArrayDollars = array(); // this holds the dollar amount of each discout, sum for total
                                    $feeDollarsArray = array(); // this holds the calc amount for the discount -> mat'l_qty * mat'l_dollars * 0.3
                                    $couponCopy = "";  // when all logic on $couponArray is complete, values will be joined and stored here
                                    $discountItemIncluded = FALSE; // if the promo30 code is to be included, this will be changed to true
                                    $forCoupon_products = array(); // this is to send a list of products to the 'roster_process_coupon' function
                                    $itemQuantity = array(); // this is to send the quantity of each item in cart to 'roster_process_coupon' function
									
									foreach($items AS $item)
									{
										// for test ->
										$countItemsLoop++;
										switch ($item['type'])
										{
											case "line_item": // this is the program or material
												// three possibilities here
												// first test for contains 'dvd', next test for 'manual', if neither of these, default as program
												$dvdPosition = stripos($item['name'], "dvd");
												$manualPosition = stripos($item['name'], "manual");
												if($dvdPosition > 0)
												{
													// first check to make sure this is associated with the program
													if(in_array($item['product_id'], $materialArray))
													{
                                                        // fill dvd array
                                                        $forCoupon_products[] = $item['product_id']; // this gets used in the new coupon processing functionality 04-17-2019
														$dvdArray[$dvdItemCount]['qty'] = $item['qty'];
                                                        $dvdArray[$dvdItemCount]['total'] = $item['line_total'];
                                                        $programTotalItemDollars[$item['product_id']] = $item['line_total'];
                                                        $itemQuantity[$item['product_id']] = $item['qty']; // this will be passed to 'roster_process_coupon()'
                                                        $feeDollarsArray[] = $item['line_total'] * 0.3;
														$dvdItemCount++;
                                                        $lineItemCount++;
                                                        if($discountItemIncluded == FALSE)
                                                        {
                                                            $discountItemIncluded = TRUE;
                                                        }
													}
												}
												elseif($manualPosition > 0)
												{
													// first check to make sure this is associated with the program
													if(in_array($item['product_id'], $materialArray))
													{
                                                        // fill manual array
                                                        $forCoupon_products[] = $item['product_id']; // this gets used in the new coupon processing functionality 04-17-2019
														$manualArray[$manualItemCount]['qty'] = $item['qty'];
                                                        $manualArray[$manualItemCount]['total'] = $item['line_total'];
                                                        $programTotalItemDollars[$item['product_id']] = $item['line_total'];
                                                        $itemQuantity[$item['product_id']] = $item['qty']; // this will be passed to 'roster_process_coupon()'
                                                        $feeDollarsArray[] = $item['line_total'] * 0.3;
														$manualItemCount++;
                                                        $lineItemCount++;
                                                        if($discountItemIncluded == FALSE)
                                                        {
                                                            $discountItemIncluded = TRUE;
                                                        }
													}
												}
												elseif($item['product_id'] == $ceu_product_id)
												{
													// ceu $item['name'] = "Program CEs" or $item['product_id'] = 8928
													// must make sure the CEs are associated with the program
													if($item['variation_id'] == $ceu_with_order)
													{
                                                        // only need total $ for ceu product
                                                        $forCoupon_products[] = $item['product_id']; // this gets used in the new coupon processing functionality 04-17-2019
														$ceuArray[$ceuItemCount]['qty'] = $item['qty'];
                                                        $ceuArray[$ceuItemCount]['total'] = $item['line_total'];
                                                        $programTotalItemDollars[$item['product_id']] = $item['line_total'];
                                                        $itemQuantity[$item['product_id']] = $item['qty']; // this will be passed to 'roster_process_coupon()'
														$ceuItemCount++;
													}
												}
												else
												{
													// program product only if $item['product_id'] == $product_id
													if($item['product_id'] == $product_id)
													{
                                                        $forCoupon_products[] = $item['product_id']; // this gets used in the new coupon processing functionality 04-17-2019
														$programArray[$programItemCount]['qty'] = $item['qty'];
                                                        $programArray[$programItemCount]['total'] = $item['line_total'];
                                                        $programTotalItemDollars[$item['product_id']] = $item['line_total'];
                                                        $itemQuantity[$item['product_id']] = $item['qty']; // this will be passed to 'roster_process_coupon()'
														$programItemCount++;
														$lineItemCount++;
													}
												}
											break;

											case "fee": // this will be the auto discount for buying program and material at same time
												// name should contain "coupon"
												$couponPosition = stripos($item['name'], "coupon");
												if($program_discount == "yes")
												{
													if($couponPosition >= 0)
													{
														$feeArray[$feeItemCount]['code'] = "promo30";
													}
													else
													{
														$feeArray[$feeItemCount]['code'] = "y-auto";
													}
                                                    $feeItemCount++;
                                                    
                                                    $feeArrayDollars[] = $item['line_total'];
												}
											break;

											case "coupon":
												// 'name' is coupon code
                                                $couponArray[$couponItemCount]['code'] = $item['name'];
												$couponItemCount++;
											break;
										}
										$itemName = $item['name'];
										$itemId = $item['product_id'];
										// for test -> 
										$itemDump[$countItemLoop] = $item;
										$countItemLoop++;
                                        // for testing -> break;

                                        // 04-17-2019 coupon processing
                                            // returns ['codes'] as a string (with <br /> included)
                                            // returns ['discount'] as the sum total (positive num) of discounts
                                        if($couponItemCount > 0)
                                        {
                                            
                                            $couponProcessedData = roster_process_coupon($couponArray, $forCoupon_products, $requestedProgramId, $programTotalItemDollars, $itemQuantity);
                                        }
                                        else
                                        {
                                            $couponProcessedData = [
                                                "codes"     => "",
                                                "discount"  => 0
                                            ];
                                        }
                                        
                                        
                                        // total dollars for the program
                                        $programTotalDollars = array_sum($programTotalItemDollars);
                                        // for test $programTotalDollars = count($programTotalItemDollars);
                                        //unset($programTotalItemDollars);

                                        // 03-31-2019 krista has decided she wants the coupon totals subtracted from program total
                                        $couponDiscountTotal = $couponProcessedData['discount'];
                                        $feeDiscountTotal = array_sum($feeDollarsArray);

                                        // **** put fee discount stuff here

                                        // this subtracts coupon amount
                                        // coupons are 'lumped' together for the order
                                        //if($item['discount_amount'])
                                        //{
                                            $programTotalDollars -= $couponDiscountTotal;
                                            $programTotalDollars -= $feeDiscountTotal;
                                        //}
									}
								}

                                $couponCopy = $couponProcessedData['codes'];
                                
                                foreach($feeArray AS $feeArray_key => $feeArray_value)
                                {
                                    foreach($feeArray_value AS $feeValueKey => $feeValueName)
                                    {
                                        if($discountItemIncluded != FALSE)
                                        {
                                            if(strlen($couponCopy) > 0)
                                            {
                                                $couponCopy = $couponCopy."<br />".$feeValueName;
                                            }
                                            else
                                            {
                                                $couponCopy = $feeValueName;
                                            }
                                        }
                                    }
                                }

                                if($countOrderLoop % 2 == 0)
                                {
                                    $tr_background = "#d3d3d3";
                                }
                                else
                                {
                                    $tr_background = "#ffffff";
                                }

								$content .= "		<tr style=\"background-color: ".$tr_background.";\">
													<td style=\"border-right: 1px solid #9ebaa0;\">".$order_id."</td>
													<td style=\"border-right: 1px solid #9ebaa0;\">".$order->order_date."</td>
													<td style=\"border-right: 1px solid #9ebaa0;\">".$order->billing_first_name." ".$order->billing_last_name."</td>
													<td style=\"border-right: 1px solid #9ebaa0;\">".$order->billing_phone."</td>
													<td style=\"border-right: 1px solid #9ebaa0;\">".$order->billing_email."</td>
													<td style=\"border-right: 1px solid #9ebaa0;\">".$order->billing_city."</td>
													<td style=\"border-right: 1px solid #9ebaa0;\">".$profession."</td>
													<td style=\"border-right: 1px solid #9ebaa0;\"><center>".$programArray[$programItemCount -1]['qty']."</center></td>
													<td style=\"border-right: 1px solid #9ebaa0;\"><center>$".$programArray[$programItemCount -1]['total']."</center></td>
													<td style=\"border-right: 1px solid #9ebaa0;\"><center>".$manualArray[$manualItemCount - 1]['qty']."</center></td>
													<td style=\"border-right: 1px solid #9ebaa0;\"><center>$".$manualArray[$manualItemCount - 1]['total']."</center></td>
													<td style=\"border-right: 1px solid #9ebaa0;\"><center>".$dvdArray[$dvdItemCount - 1]['qty']."</center></td>
													<td style=\"border-right: 1px solid #9ebaa0;\"><center>$".$dvdArray[$dvdItemCount - 1]['total']."</center></td>
													<td style=\"border-right: 1px solid #9ebaa0;\"><center>$".$ceuArray[$ceuItemCount - 1]['total']."</center></td>
													<td style=\"border-right: 1px solid #9ebaa0;\"><center>".$couponCopy."</center></td>
													<td><center>$".number_format($programTotalDollars, 2)."</center></td>
													</tr>";
								/* for testing
								$content .= "   <tr>
													<td>";
														for($x = 0; $x < $countItemLoop; $x++)
														{
															foreach($itemDump[$x] AS $key=>$value)
															{
																$content .= "<span style=\"color: #ff0000;\">the key: ".$key."</span> <span style=\"color: #0000ff;\">the value: ".$value."</span><br />";
															}
														}
								$content .= "       </td>
												</tr>";
								end test code */
							}
							else
							{
								$content .= "		<tr>
													<td colspan=\"7\">
														Nothing returned from database. Count: ".$countOrderLoop.".
													</td>
												</tr>";
                            }
						} // end foreach $orders->posts loop
					/*
					} // end check for properties in 'posts'
					else
					{
						$content .= "	<tr>
										<td colspan=\"7\">
											The object is empty.
										</td>
									</tr>";
					}*/
					
					/* for testing
					$content .= "	<tr>
									<td colspan=\"6\" style=\"text-align: center;\">
										Finished loop.  Count: ".$countOrderLoop."
									</td>
								</tr>";
					end test code */
				}
				$content .= "		</tbody>
							</table>";
				
                
			}
			else
			{
				$returnThis['type'] = "fail";
				$content = "<h3>No Results.</h3>";
			}
		}
		else
		{
			$returnThis['type'] = "idBad";
			$content = "<h3>Product ID not found.</h3>";
		}
		
		$returnThis['html'] = $content;
		$returnThis = json_encode($returnThis);
			
		print $returnThis;
		
		die(); // always end script with this when using AJAX / JSON or a 0 / -1 will get appended to the end of your string
	} // end generate_roster_list function
	
	function narrow_program_list()
	{
		global $wpdb;
		$content = ""; // this will hold the html that is returned
		$returnThis = array(); // return things as JSON to AJAX call
		
		if (!wp_verify_nonce($_REQUEST['nonce'], "get_roster_nonce"))
		{
			// exit("Access denied.");
			// for testing ->
			header("Location: ".$_SERVER['HTTP_REFERER']); // if nonce is not being verified, this should refresh the page... visual feedback.
		}
		
		// get passed values and format
		// 02-12-2019 - Krista says they want to filter by "display_start_month" "_day" "_year" not the creation date
		$theStartDate = $_REQUEST['start_year']."-".$_REQUEST['start_month']."-".$_REQUEST['start_day']." 00:00:01";
		$theEndDate = $_REQUEST['end_year']."-".$_REQUEST['end_month']."-".$_REQUEST['end_day']." 23:59:59";
		$displayStartDate = $_REQUEST['start_month']."/".$_REQUEST['start_day']."/".$_REQUEST['start_year'];
		$displayEndDate = $_REQUEST['end_month']."/".$_REQUEST['end_day']."/".$_REQUEST['end_year'];

		$epochStart = mktime(0,0,0,$_REQUEST['start_month'],$_REQUEST['start_day'],$_REQUEST['start_year']);
		$epochEnd = mktime(23,59,59,$_REQUEST['end_month'],$_REQUEST['end_day'],$_REQUEST['end_year']);
		
		// call the search of wp_postmeta the "sql_pre"
		$sql_pre = "SELECT post_id FROM wp_postmeta WHERE meta_key = 'orderBy_date' AND meta_value >= '".$epochStart."' AND meta_value <= '".$epochEnd."' ORDER BY meta_value DESC";
		$getProgramIds = $wpdb->get_results($sql_pre);
		$idRowCount = $wpdb->num_rows;

		$programTitleList = array();
		if($idRowCount > 0)
		{
			if($getProgramIds != FALSE)
			{
				foreach($getProgramIds AS $theId)
				{
					// first check if program is of (meta_key) "program_type" = (meta_value) "our"
					$sql_ourTest = "SELECT * FROM wp_postmeta WHERE post_id = ".$theId->post_id." AND meta_key = 'program_type' AND meta_value = 'our'";
					$doOurTest = $wpdb->get_results($sql_ourTest);

					if($doOurTest != FALSE)
					{
						$sql = "SELECT post_title FROM wp_posts WHERE ID = ".$theId->post_id;
						// $test_sql = "SELECT ID, post_title FROM wp_posts WHERE post_type = 'program_pages' AND post_status = 'publish' ORDER BY post_date DESC";
						$getPrograms = $wpdb->get_results($sql);

						if($getPrograms != FALSE)
						{
							foreach($getPrograms AS $theProgram)
							{
								$programTitleList[$theId->post_id]['title'] = $theProgram->post_title;
								$programTitleList[$theId->post_id]['post_id'] = $theId->post_id;
							}
						}

						// 02/27/2019 - add the date to the program list dropdown
						// get the program's date form wp_postmeta
						$startMonth_sql = "SELECT meta_value FROM wp_postmeta WHERE post_id = ".$theId->post_id." AND meta_key = 'display_start_month'";
						$getStartMonth = $wpdb->get_results($startMonth_sql);

						if ($getStartMonth != FALSE)
						{
							foreach($getStartMonth AS $theStartMonth)
							{
								$numericStartMonth = $theStartMonth->meta_value;
							}

							$startMonthBool = TRUE;
						}

						$startDay_sql = "SELECT meta_value FROM wp_postmeta WHERE post_id = ".$theId->post_id." AND meta_key = 'display_start_day'";
						$getStartDay = $wpdb->get_results($startDay_sql);

						if ($getStartDay != FALSE)
						{
							foreach($getStartDay AS $theStartDay)
							{
								$numericStartDay = $theStartDay->meta_value;
							}

							$startDayBool = TRUE;
						}

						$startYear_sql = "SELECT meta_value FROM wp_postmeta WHERE post_id = ".$theId->post_id." AND meta_key = 'display_start_year'";
						$getStartYear = $wpdb->get_results($startYear_sql);

						if ($getStartYear != FALSE)
						{
							foreach($getStartYear AS $theStartYear)
							{
								$numericStartYear = $theStartYear->meta_value;
							}

							$startYearBool = TRUE;
						}

						if($startMonthBool && $startDayBool && $startYearBool)
						{
							$insertFriendlyDate = date("F d, Y", mktime(0,0,0,$numericStartMonth,$numericStartday,$numericStartYear));
							$programTitleList[$theId->post_id]['friendlyDate'] = $insertFriendlyDate;
						}
					}
					
				}
				
				if(count($programTitleList) > 0)
				{
					$returnThis['type'] = "success";
					$content .= "<option value=\"\">Program List...</option>";
					
					foreach($programTitleList AS $programTitle)
					{
						$optionValue = $programTitle['post_id'];
						$optionTextTitle = $programTitle['title'];
						$optionTextDate = $programTitle['friendlyDate'];

						$content .= "<option value=\"".$optionValue."\">".$optionTextTitle." (".$optionTextDate.")</option>";
					}
				}
				else
				{
					// nothing returned from search
					$returnThis['type'] = "fail";
					$content .= "<option value=\"\">".$displayStartDate." - ".$displayEndDate."</option>";
				}
			}
			else
			{
				$returnThis['type'] = "fail";
				$content .= "<option value=\"\">getProgramIds returned 'false'</option>";
			}
		}
		else
		{
			$returnThis['type'] = "fail";
			$content .= "<option value=\"\">getProgramIds is 0, row count = ".$idRowCount."</option>";
			$content .= "<option value=\"\">".$sql_pre."</option>";
		}
		

		if($returnThis['type'] != "success" && $returnThis['type'] != "fail")
		{
			$returnThis['type'] = "other";
			$content .= "Result value equal to or less than ZERO.";
		}
		
		$returnThis['html'] = $content;
		$returnThis = json_encode($returnThis);
			
		print $returnThis;
		
		die(); // always end script with this when using AJAX / JSON or a 0 / -1 will get appended to the end of your string
	} // end narrow_program_list function
	
    add_action("wp_ajax_generate_csv", "generate_csv"); // for listing participants in program
	add_action("wp_ajax_nopriv_generate_csv", "roster_user_must_login"); // for listing participants in program
    
    function generate_csv()
	{
        $docArray = array(
			1 => array("Last Name", "First Name", "Email", "Phone", "City", "Profession", "Order Date", "Registrations QTY", "Registrations Total $", "Manuals QTY", "Manuals Total $", "DVDs Purchased QTY", "DVDs Total $", "CEs QTY", "CEs Total $", "Total Paid", "Promo Code", "Notes")
        );
        $docArrayIndex = 2;
        
        global $wpdb;
		$content = ""; // this will hold the html that is returned
		$returnThis = array(); // return things as JSON to AJAX call
		// array that will hold the data for csv file and column headers
		
		
		if (!wp_verify_nonce($_REQUEST['nonce'], "get_roster_nonce"))
		{
			// exit("Access denied.");
			// for testing ->
			header("Location: ".$_SERVER['HTTP_REFERER']); // if nonce is not being verified, this should refresh the page... visual feedback.
		}
		
		// get passed values and format
		$passedProgramId = $_REQUEST['postId'];
		$passedProductId = $_REQUEST['productId'];
		
		// replace starts here
		// the product id is stored in wp_postmeta with the program post's id
		$first_sql = "SELECT * FROM wp_postmeta WHERE post_id = ".$passedProgramId." AND meta_key = 'shopping_product_id'";
		$getProductInfo = $wpdb->get_results($first_sql);
		
		if($getProductInfo != FALSE)
		{
			foreach($getProductInfo AS $theProductInfo)
			{
				$product_id = $theProductInfo->meta_value;
				$returnThis['product_id'] = $product_id;
			}
			
			$orders = rt_get_orders_by_product_id($product_id);
			
			/* for testing ->
			$returnThis['type'] = "success";
			$content .= "Object content:<br />
						".var_dump($orders)."<br />";
			end testing */
			
			// loop through orders
			// original test -> if (!empty($orders))
			
			// test to see if there is anything in the $orders object
			//$testArray = (array)$orders; // cast object to array
			//if(!$testArray) // test if anything in array
			if(!empty($orders))
			{
				$returnThis['type'] = "success";
				
				if ($orders == "query_fail")
				{
					$content .= "For CSV: Query failed in 'rt_get_orders_by_product_id'";
				}
				else
				{
					$countOrderLoop = 0;
					
					// test to see if there is anything in the $orders object
					/*$testArray = (array)$orders; // cast object to array
					if(!$testArray) // test if anything in array
					{*/
						foreach($orders->posts AS $order_id)
						{
							/* for testing
							$content .= "	<tr>
											<td colspan=\"7\">
												The foreach loop is entered.
											</td>
										</tr>";
							end test code */
							
                            $order = new WC_Order($order_id);
							
							// get custom checkout fields
							$profession = get_post_meta($order_id, "checkout_profession", true);
							// get metafields associated with the PROGRAM
                            $ceu_with_order = get_post_meta($passedProgramId, "ceu_variant", true);
                            $ceu_product_id = get_post_meta($passedProgramId, "ceu_product_id", true);
							$material_with_order = get_post_meta($passedProgramId, "material_product_ids", true); // this is a comma seperated string
							$program_discount = get_post_meta($passedProgramId, "include_discount", true); // this will be 'yes' if discount assoc with program
							$program_discount_amount = get_post_meta($passedProgramId, "discount_percent", true);

                            $materialArray = explode(",", $material_with_order);
                            
                            // take care of orders before the 'ceu_product_id' existed
                            if($ceu_with_order && !$ceu_product_id)
                            {
                                $ceu_product_id = 8928;
                            }
							
							$countOrderLoop++;
							
							if($order)
							{
								// format addresses
								$address = array(	"first_name"	=> $order->billing_first_name,
												"last_name"	=> $order->billing_last_name,
												"company"		=> $order->billing_company,
												"address_1"	=> $order->billing_address_1,
												"address_2"	=> $order->billing_address_2,
												"city"		=> $order->billing_city,
												"state"		=> $order->billing_state,
												"postcode"	=> $order->billing_postcode
												);
								
								$formatted_billing_address = WC()->countries->get_formatted_address($address);
								
								// get order items
                                $itemTypes = array("line_item", "fee", "coupon");
                                $items = "";
								$items = $order->get_items($itemTypes);
                                // for test -> $itemResult = $items ? "items works" : "fail";
                                // for testing -> $content .= "Order ID: ".$order_id." get items: ".$itemResult." Number of items: ".count($items)."<br />";
								// for test ->
								$numOfItems = count($items);

								if($items)
								{   
									$countItemsLoop = 0;
									$countItemLoop = 0;
									$lineItemCount = 0;
									$couponItemCount = 0;
									$feeItemCount = 0;
									$manualItemCount = 0;
									$dvdItemCount = 0;
									$programItemCount = 0;
                                    $ceuItemCount = 0;
                                    $programTotalDollars = 0;
									$programArray = array(); // this will hold the program qty and total $
									$manualArray = array(); // this will hold manual product qty and total $
									$dvdArray = array(); // this will hold dvd product qty and total $
                                    $ceuArray = array(); // this will hold the ceu info
                                    $feeArray = array(); // this holds fee or 'discount' info
									$couponArray = array(); // this will hold the coupon info - this is the special 'fee' discount + coupons
                                    $itemDump = array(); // for test
                                    $programTotalItemDollars = array(); // this holds the total for program and related items per line
                                    $feeArrayDollars = array(); // this holds the dollar amount of each discout, sum for total
                                    $feeDollarsArray = array(); // this holds the calc amount for the discount -> mat'l_qty * mat'l_dollars * 0.3
                                    $couponCopy = "";  // when all logic on $couponArray is complete, values will be joined and stored here
                                    $discountItemIncluded = FALSE; // if the promo30 code is to be included, this will be changed to true
                                    $forCoupon_products = array(); // this is to send a list of products to the 'roster_process_coupon' function
                                    $itemQuantity = array(); // this is to send the quantity of each item in cart to 'roster_process_coupon' function

									foreach($items AS $item)
									{
										// for test -> $countItemsLoop++;
                                        
                                        $dvdPosition = 0;
                                        $manualPosition = 0;
										switch ($item['type'])
										{
                                            case "line_item": // this is the program or material
												// three possibilities here
												// first test for contains 'dvd', next test for 'manual', if neither of these, default as program
												$dvdPosition = stripos($item['name'], "dvd");
												$manualPosition = stripos($item['name'], "manual");
												if($dvdPosition > 0)
												{
													// first check to make sure this is associated with the program
													if(in_array($item['product_id'], $materialArray))
													{
                                                        // fill dvd array
                                                        $forCoupon_products[] = $item['product_id'];
														$dvdArray[$dvdItemCount]['qty'] = $item['qty'];
                                                        $dvdArray[$dvdItemCount]['total'] = $item['line_total'];
                                                        $programTotalItemDollars[$item['product_id']] = $item['line_total'];
                                                        $itemQuantity[$item['product_id']] = $item['qty'];
                                                        $feeDollarsArray[] = $item['line_total'] * 0.3;
														$dvdItemCount++;
                                                        $lineItemCount++;
                                                        if($discountItemIncluded == FALSE)
                                                        {
                                                            $discountItemIncluded = TRUE;
                                                        }
                                                    }
                                                    
                                                    // for testing -> $content .= "DVD case :: Line Item Type: ".$item['type']." Item name: ".$item['name']." Item ID: ".$item['product_id']."<br />";
												}
												elseif($manualPosition > 0)
												{
													// first check to make sure this is associated with the program
													if(in_array($item['product_id'], $materialArray))
													{
                                                        // fill manual array
                                                        $forCoupon_products[] = $item['product_id'];
														$manualArray[$manualItemCount]['qty'] = $item['qty'];
                                                        $manualArray[$manualItemCount]['total'] = $item['line_total'];
                                                        $programTotalItemDollars[$item['product_id']] = $item['line_total'];
                                                        $itemQuantity[$item['product_id']] = $item['qty'];
                                                        $feeDollarsArray[] = $item['line_total'] * 0.3;
														$manualItemCount++;
                                                        $lineItemCount++;
                                                        if($discountItemIncluded == FALSE)
                                                        {
                                                            $discountItemIncluded = TRUE;
                                                        }
                                                    }
												}
												elseif($item['product_id'] == $ceu_product_id)
												{
													// ceu $item['name'] = "Program CEs" or $item['product_id'] = 8928
													// must make sure the CEs are associated with the program
													if($item['variation_id'] == $ceu_with_order)
													{
                                                        // only need total $ for ceu product
                                                        $forCoupon_products[] = $item['product_id'];
														$ceuArray[$ceuItemCount]['qty'] = $item['qty'];
                                                        $ceuArray[$ceuItemCount]['total'] = $item['line_total'];
                                                        $programTotalItemDollars[$item['product_id']] = $item['line_total'];
                                                        $itemQuantity[$item['product_id']] = $item['qty'];
                                                        $ceuItemCount++;
                                                        $lineItemCount++;
                                                    }
                                                    
                                                    // for testing -> $content .= "CE case :: Line Item Type: ".$item['type']." Item name: ".$item['name']." Item ID: ".$item['product_id']."<br />";
												}
												else
												{
													// program product only if $item['product_id'] == $product_id
													if($item['product_id'] == $product_id)
													{
                                                        $forCoupon_products[] = $item['product_id'];
														$programArray[$programItemCount]['qty'] = $item['qty'];
                                                        $programArray[$programItemCount]['total'] = $item['line_total'];
                                                        $programTotalItemDollars[$item['product_id']] = $item['line_total'];
                                                        $itemQuantity[$item['product_id']] = $item['qty'];
														$programItemCount++;
														$lineItemCount++;
                                                    }
                                                    // for testing -> $defaultLineItemCount++;
                                                    // for testing -> $content .= "Program product case :: Line Item Type: ".$item['type']." Item name: ".$item['name']." Item ID: ".$item['product_id']."<br />";
												}
											break;

											case "fee": // this will be the auto discount for buying program and material at same time
												// name should contain "coupon"
												$couponPosition = stripos($item['name'], "coupon");
												if($program_discount == "yes")
												{
													if($couponPosition >= 0)
													{
														$feeArray[$feeItemCount]['code'] = "promo30";
													}
													else
													{
														$feeArray[$feeItemCount]['code'] = "y-auto";
													}
                                                    $feeItemCount++;
                                                    $lineItemCount++;
                                                    
                                                    $feeArrayDollars[] = $item['line_total'];
                                                }
                                                
                                                // for testing -> $content .= "Fee case :: Line Item Type: ".$item['type']." Item name: ".$item['name']." Item ID: ".$item['product_id']."<br />";
											break;

											case "coupon":
												// 'name' is coupon code
                                                $couponArray[$couponItemCount]['code'] = $item['name'];
												$couponItemCount++;
                                                
												//$couponItemCount++;
                                                $feeItemCount++;
                                                $lineItemCount++;

                                                // for testing -> $content .= "Coupon case :: Line Item Type: ".$item['type']." Item name: ".$item['name']." Item ID: ".$item['product_id']."<br />";
											break;
										}  // end switch
										$itemName = $item['name'];
										$itemId = $item['product_id'];
										// for test -> 
										$itemDump[$countItemLoop] = $item;
										$countItemLoop++;
                                        // fortesting -> break;

                                        // 04-17-2019 coupon processing
                                        // returns ['codes'] as a string (with <br /> included)
                                        // returns ['discount'] as the sum total (positive num) of discounts
                                        if($couponItemCount > 0)
                                        {
                                            
                                            $couponProcessedData = roster_process_coupon($couponArray, $forCoupon_products, $requestedProgramId, $programTotalItemDollars, $itemQuantity);
                                        }
                                        else
                                        {
                                            $couponProcessedData = [
                                                "codes"     => "",
                                                "discount"  => 0
                                            ];
                                        }
                                        
                                        // total dollars for the program
                                        $programTotalDollars = array_sum($programTotalItemDollars);
                                        // for test $programTotalDollars = count($programTotalItemDollars);
                                        //unset($programTotalItemDollars);

                                        // 03-31-2019 krista has decided she wants the coupon totals subtracted from program total
                                        $couponDiscountTotal = $couponProcessedData['discount'];
                                        $feeDiscountTotal = array_sum($feeDollarsArray);

                                        $programTotalDollars -= $couponDiscountTotal;
                                        $programTotalDollars -= $feeDiscountTotal;
									}
								}

                                $couponCopy = $couponProcessedData['codes'];
                                
                                foreach($feeArray AS $feeArray_key => $feeArray_value)
                                {
                                    foreach($feeArray_value AS $feeValueKey => $feeValueName)
                                    {
                                        if($discountItemIncluded != FALSE)
                                        {
                                            if(strlen($couponCopy) > 0)
                                            {
                                                $couponCopy = $couponCopy." ".$feeValueName;
                                            }
                                            else
                                            {
                                                $couponCopy = $feeValueName;
                                            }
                                        }
                                    }
                                }

                            $docArray[$docArrayIndex] = array(  $order->billing_last_name,
                                                                $order->billing_first_name,
                                                                $order->billing_email,
                                                                $order->billing_phone,
                                                                $order->billing_city,
                                                                $profession,
                                                                $order->order_date,
                                                                $programArray[$programItemCount -1]['qty'],
                                                                $programArray[$programItemCount -1]['total'],
                                                                $manualArray[$manualItemCount - 1]['qty'],
                                                                $manualArray[$manualItemCount - 1]['total'],
                                                                $dvdArray[$dvdItemCount - 1]['qty'],
                                                                $dvdArray[$dvdItemCount - 1]['total'],
                                                                $ceuArray[$ceuItemCount - 1]['qty'],
                                                                $ceuArray[$ceuItemCount - 1]['total'],
                                                                $programTotalDollars,
                                                                $couponCopy,
                                                            );
                            // for testing -> $content .= "LINE ITEM COUNT: ".$lineItemCount." / ".$defaultLineItemCount." DVD Item Count: ".$dvdItemCount." Maunal Item Count: ".$manualItemCount." CE Item Count: ".$ceuItemCount." Program Item Count: ".$programItemCount." Fee Item Count: ".$feeItemCount;
                            /* for testing
                            $content .= "   <tr>
                                                <td>";
                                                    for($x = 0; $x < $countItemLoop; $x++)
                                                    {
                                                        foreach($itemDump[$x] AS $key=>$value)
                                                        {
                                                            $content .= "<span style=\"color: #ff0000;\">the key: ".$key."</span> <span style=\"color: #0000ff;\">the value: ".$value."</span><br />";
                                                        }
                                                    }
                            $content .= "       </td>
                                            </tr>";
                            end test code */

                            $docArrayIndex++;
                        }
                        else
                        {
                            $content .= "<em>Nothing returned from database. Count: ".$countOrderLoop.".</em>";
                        }
                    } // end foreach $orders->posts loop

                    /* this section is all moved to ../data/index.php
                    // generate excel (xml) file/*
                    // this line is at beginning of file -> use includes\SimpleExcel\SimpleExcel;
                    require_once ("includes/SimpleExcel.php");
                    $now = time();
                    $fileDate = date(DATE_ATOM, $now);
                    $xls = new SimpleExcel('csv');
                    $xls->writer->setData($docArray);
                    $xls->writer->setDelimiter(";");
                    $xls->writer->saveFile("rosterData_".$fileDate);
                    */

                    $content .= "<br />The file has been generated.  Your browser should open a \"save\" dialog allowing you to save the file locally.";
				}
			}
			else
			{
				$returnThis['type'] = "fail";
				$content = "<h3>No Results.</h3>";
			}
		}
		else
		{
			$returnThis['type'] = "idBad";
			$content = "Product ID not found.";
		}
		
        $returnThis['html'] = $content;
        $returnThis['roster'] = $docArray;
		$returnThis = json_encode($returnThis);
			
		print $returnThis;
		
		die(); // always end script with this when using AJAX / JSON or a 0 / -1 will get appended to the end of your string
	} // end generate_csv function

	function rt_get_orders_by_product_id($passedId)
	{
		global $wpdb;
		$table_orderItemMeta = $wpdb->prefix."woocommerce_order_itemmeta";
		$table_orderItems = $wpdb->prefix."woocommerce_order_items";
		
		$sql = "SELECT b.order_id FROM ".$table_orderItemMeta." a, ".$table_orderItems." b WHERE a.meta_key = '_product_id' AND a.meta_value = ".$passedId." AND a.order_item_id = b.order_item_id ORDER BY b.order_id DESC";
		$getOrders = $wpdb->get_results($sql);
		
		if($getOrders)
		{
			$order_ids = array();
			
			foreach($getOrders AS $theOrder)
			{
				array_push($order_ids, $theOrder->order_id);
			} // end foreach loop
			
			if($order_ids)
			{
				$args = array(	"post_type"		=> "shop_order",
							"post_status"		=> array('wc-processing', 'wc-completed'),
							"posts_per_page"	=> -1,
							"post__in"		=> $order_ids,
							"fields"			=> "ids"
							);
				
				$query = new WP_Query($args);
				
				if($query)
				{
					return $query;
				}
				else
				{
					return "query_fail";
				}
			} // end if $order_ids
		} // end if $getOrders
	} // end rt_get_orders_by_product_id function
	
	function roster_user_must_login()
	{
		echo "You must login and have admin permission to generate roster(s).";
	} // end roster_user_must_login function
	
	/* * end code for program roster page * */
	
	/* code for program carat section 11/2018 */

	function generate_carat_management_pg()
	{
		global $wpdb;
		$defaultCarat_select_options = ""; // hold the default carats from db
		$customCarat_select_options = ""; // holds the current custom carats that are in the db

		$nonce = wp_create_nonce("new_carat_nonce");
		$link_ajaxSaveCarats = admin_url("admin-ajax.php?action=save_new_carat&nonce=".$nonce);
		$link_ajaxSaveCaratOrder = admin_url("admin-ajax.php?action=save_carat_order&nonce=".$nonce);
		$link_ajaxDeleteCustomCarat = admin_url("admin-ajax.php?action=delete_custom_carat&nonce=".$nonce);
		$link_ajaxEditCustomCarat = admin_url("admin-ajax.php?action=edit_custom_carat&nonce=".$nonce);

		$defaultCarat_sql = "SELECT * FROM wp_fits_progmng_carats WHERE carat_role = 'default' ORDER BY carat_order";
		$getDefaultCarats = $wpdb->get_results($defaultCarat_sql);

		if($getDefaultCarats != NULL)
		{
			foreach ($getDefaultCarats AS $theDefaultCaratItem)
			{
				$defaultCarat_list_items .= "<li class=\"ui-state-default default-draggable\" data-default_id=\"".$theDefaultCaratItem->carat_id."\" data-default_position=\"".$theDefaultCaratItem->carat_order."\"><span class=\"ui-icon ui-icon-arrowthick-2-n-s\"></span>".$theDefaultCaratItem->carat_title."</li>";
			}
		}

		$customCarat_sql = "SELECT * FROM wp_fits_progmng_carats WHERE carat_role = 'custom' ORDER BY carat_order";
		$getCustomCarats = $wpdb->get_results($customCarat_sql);

		if($getCustomCarats != NULL)
		{
			if(!empty($getCustomCarats))
			{
				foreach ($getCustomCarats AS $theCustomCaratItem)
				{
					$customCarat_list_items .= "<li class=\"ui-state-default custom-draggable\" data-custom_id=\"".$theCustomCaratItem->carat_id."\" data-custom_position=\"".$theCustomCaratItem->carat_order."\"><span class=\"ui-icon ui-icon-arrowthick-2-n-s\"></span>".$theCustomCaratItem->carat_title."<span class=\"ui-icon ui-icon-trash carat-delete customCaratDelete_btn\" style=\"cursor: pointer;\"></span> <span class=\"ui-icon ui-icon-pencil carat-edit customCaratEdit_btn\" style=\"cursor: pointer;\"></span></li>";
				}
			}
			else
			{
				$customCarat_list_items = "<li>There are no custom carats yet.</li>";
			}
		}
		else
		{
			$customCarat_list_items = "<li>There are no custom carats.</li>";
		}

		?>
			<div class="wrap">
				<div id="carat_form_container">
					<form id="manageProgramCarats" name="manageProgramCarats" action="" method="post">
						<table style="width: 100%; border-collapse: collapse;">
							<tr>
								<td style="width: 50%; padding: 10px; vertical-align: top;">
									<label for="defaultCarat_select">Default Carats</label><br />
									<span id="defaultCarat_message"></span>
									<ul class="defaultCarat_sortable">
										<?php //print $defaultCarat_list_items; ?>
									</ul>
									<br />
									<div id="default_carat_message"></div>
									<!-- <a href="<?php //echo $link_ajaxSaveCaratOrder; ?>" id="save_carat_settings" name="save_carat_settings" class="button button-primary button-large" data-nonce="<?php echo $nonce; ?>" type="submit">Save Default Carat Order</a> -->
								</td>
								<td style="width: 50%; padding: 10px; vertical-align: top;">
									<label for="customCarat_select">Custom Carats</label><br />
									<span id="customCarat_message"></span>
									<ul class="customCarat_sortable">
										<?php print $customCarat_list_items; ?>
									</ul>
									<br />
									<div id="custom_carat_message"></div>
									<a href="<?php echo $link_ajaxSaveCaratOrder; ?>" id="save_custom_carat_order" name="save_custom_carat_order" class="button button-primary button-large" data-nonce="<?php echo $nonce; ?>" type="submit">Save Custom Carat Order</a>
								</td>
							</tr>
							<tr>
								<td colspan="2">
									&nbsp;
								</td>
					</form>
							</tr>
							<tr>
								<td colspan="2">
									<!-- add/edit form -->
									<div style="border: 1px solid #d3d3d3; margin-top: 15px; padding: 10px;">
										<h3>Add / Modify Carat Info</h3>
										<p>To add a new custom carat (not already in the list above), begin filling in this form and click the 'Save' button.  To modify an existing custom carat, click the carat title in the list above (right), make your changes, and click the 'Save' button.</p>
										<form id="customCaratMgmt" name="customCaratMgmt" action="" method="post">
											<table style="border-collapse: collapse; width: 98%;">
												<tr>
													<td style="width: 60%;">
														<label for="modify_carat_title">Carat Title</label><br />
														<input type="text" style="width: 98%;" id="modify_carat_title" name="modify_carat_title" value="" placeholder="Carat Title" />
													</td>
													<td style="width: 40%;">
														<label for="modify_carat_position">Carat Order</label><br />
														<select id="modify_carat_position" name="modify_carat_position">
															<option value="">Default Position</option>
															<option value="1">1</option>
															<option value="2">2</option>
															<option value="3">3</option>
															<option value="4">4</option>
															<option value="5">5</option>
															<option value="6">6</option>
															<option value="7">7</option>
															<option value="8">8</option>
															<option value="9">9</option>
															<option value="10">10</option>
															<option value="11">11</option>
															<option value="12">12</option>
															<option value="13">13</option>
															<option value="14">14</option>
															<option value="15">15</option>
														</select>
													</td>
												</tr>
												<tr>
													<td colspan="2" style="padding-top: 15px;">
														<input type="hidden" id="modify_carat_id" name="modify_carat_id" value="add" />
														<input type="hidden" id="modify_carat_role" name="modify_carat_role" value="custom" />
														<button id="modify_carat_btn" name="modify_carat_btn" class="button button-primary button-large" data-nonce="<?php echo $nonce; ?>" type="button">Add New</button>
													</td>
												</tr>
										</form>
									</div>
								</td>
							</tr>
						</table>
						
					
				</div>
				<hr />
				<div id="data_list"></div>
			</div>
			<script>
				jQuery(function()
				{
					jQuery(".defaultCarat_sortable").sortable();
					jQuery(".defaultCarat_sortable").disableSelection();
					jQuery(".customCarat_sortable").sortable();
					jQuery(".customCarat_sortable").disableSelection();
				});
			</script>
		<?php

	} // end functioin generate_carat_managment_pg
	
	add_action("wp_ajax_save_new_carat", "save_new_carat"); // for saving carats added/modified
	add_action("wp_ajax_nopriv_save_new_carat", "carat_user_must_login"); // for no privelage carat control
	
	function save_new_carat()
	{
		// save new carat to db
		global $wpdb;
		$returnThis = array(); // return the results to AJAX
		
		if (!wp_verify_nonce($_REQUEST['nonce'], "new_carat_nonce"))
		{
			// exit("Access denied.");
			// for testing ->	header("Location: ".$_SERVER['HTTP_REFERER']); // if nonce is not being verified, this should refresh the page... visual feedback.
			$returnThis['type'] = "other";
			$returnThis['html'] = "<span style=\"color: #00ffff; font-weight: 700;\">The transmission could not be verified.  Please try again.</span>";

			$returnThis = json_encode($returnThis);
			print $returnThis;
		
			die(); // always end script with this when using AJAX / JSON or a 0 / -1 will get appended to the end of your string
		}
		
		$desiredCaratOrder = $_REQUEST['caratOrder'] + 10;
		
		$saveResult = $wpdb->insert(
			"wp_fits_progmng_carats",
			array(  "carat_title"           => $_REQUEST['caratTitle'],
					"carat_textarea_name"   => $_REQUEST['caratTextareaName'],
					"carat_role"            => $_REQUEST['caratRole'],
					"carat_order"           => $desiredCaratOrder
			),
			array(  "%s",
					"%s",
					"%s",
					"%d"
			)
		);
		
		if ($saveResult != FALSE)
		{
			$insertRecordId = $wpdb->insert_id;

			if ($insertRecordId > 0)
			{
				$returnThis['type'] = "success";
				$returnThis['html'] = "<li class=\"ui-state-default custom-draggable\" data-custom_id=\"".$insertRecordId."\" data-custom_position=\"".$_REQUEST['caratOrder']."\"><span class=\"ui-icon ui-icon-arrowthick-2-n-s\"></span>".$_REQUEST['caratTitle']."<span  class=\"ui-icon ui-icon-trash carat-delete customCaratDelete_btn\" style=\"cursor: pointer;\"></span> <span class=\"ui-icon ui-icon-pencil carat-edit customCaratEdit_btn\" style=\"cursor: pointer;\"></span></li>";
			}
			else
			{
				$returnThis['type'] = "fail";
				$returnThis['html'] = "<span style=\"color: #00ffff; font-weight: bold;\">The new carat information was not saved.</span>";
			}
		}
		else
		{
			$returnThis['type'] = "other";
			$returnThis['html'] = "<span style=\"color: #00ffff; font-weight: bold;\">Error: The carat data was not saved.</span>";
		}

		$returnThis = json_encode($returnThis);
		print $returnThis;
		
		die(); // always end script with this when using AJAX / JSON or a 0 / -1 will get appended to the end of your string

	} // end function save_new_carat

	add_action("wp_ajax_save_carat_order", "save_carat_order"); // for saving carats added/modified
	add_action("wp_ajax_nopriv_save_carat_order", "carat_user_must_login"); // for no privelage carat control
	
	function save_carat_order()
	{
		// this function recieves info via an ajax send and then saves the carat order to the database

		// save new carat to db
		global $wpdb;
		$returnThis = array(); // return the results to AJAX
		$successCount = 0; // count the number of successful updates
		
		if (!wp_verify_nonce($_REQUEST['nonce'], "new_carat_nonce"))
		{
			// exit("Access denied.");
			// for testing ->	header("Location: ".$_SERVER['HTTP_REFERER']); // if nonce is not being verified, this should refresh the page... visual feedback.
			$returnThis['type'] = "other";
			$returnThis['html'] = "<span style=\"color: #00ffff; font-weight: 700;\">The transmission could not be verified.  Please try again.</span>";

			$returnThis = json_encode($returnThis);
			print $returnThis;
		
			die(); // always end script with this when using AJAX / JSON or a 0 / -1 will get appended to the end of your string
		}

		$passed_orderArray = array();
		$passed_orderArray = $_REQUEST['caratOrder'];

		for ($x = 0; $x < count($passed_orderArray); $x++)
		{
			if($_REQUEST['role'] == "custom")
			{
				$caratOrder = $x + 11; // want to make sure the custom carates are always saved with numbers higher than default carats
			}
			else
			{
				$caratOrder = $x + 1;
			}
			$theData = array("carat_order" => $caratOrder);
			$theWhere = array("carat_id" => $passed_orderArray[$x]);
			$theFormat = array("%d");

			$saveResult = $wpdb->update("wp_fits_progmng_carats", $theData, $theWhere, $theFormat);

			if($saveResult !== FALSE)
			{
				$successCount++;
			}
		}

		// test for success - the value of $successCount should = number of items in array
		if($successCount == count($passed_orderArray))
		{
			// success
			$returnThis['type'] = "success";
			$returnThis['html'] = "<span style=\"color: #ff000f;\">Successfully updated carat order.</span>";
		}
		elseif($successCount > count($passed_orderArray))
		{
			// something really weird happened
			$returnThis['type'] = "other";
			$returnThis['html'] = "<span style=\"color: #00ffff; font-weight: 700;\">The order was updated.  But, there may have been errors.  <em>Contact your developer or account rep with the message 'carat save error 1' for more info.</em></span>";
		}
		else
		{
			// something went wrong
			$returnThis['type'] = "fail";
			$returnThis['html'] = "<span style=\"color: #00ffff; font-weight: 700;\">The order was not saved!  Please try again.</span>";
		}

		$returnThis = json_encode($returnThis);
		print $returnThis;

		die(); // always end script with this when using AJAX / JSON or a 0 / -1 will get appended to the end of the 'print' string
	}

	add_action("wp_ajax_delete_custom_carat", "delete_custom_carat"); // for saving carats added/modified
	add_action("wp_ajax_nopriv_delete_custom_carat", "carat_user_must_login"); // for no privelage carat control
	
	function delete_custom_carat()
	{
		// this function will delete the custom carat when the trashcan icon is clicked.

		global $wpdb;
		$returnThis = array(); // return the results to AJAX
		$theId = $_REQUEST['dbId'];

		$dbWhere = array("carat_id" => $theId);
		$dbFormat = array("%d");

		$successCount = $wpdb->delete("wp_fits_progmng_carats", $dbWhere, $dbFormat);

		if($successCount != FALSE && $successCount == 1)
		{
			// this is what I want, it's deleted successfully
			$returnThis['type'] = "success";
			$returnThis['html'] = "<span style=\"color: #ff000f; font-weight: bold;\">Success:</span> The carat was deleted.";
		}
		elseif ($successCount != FALSE && $successCount !=1)
		{
			// something else happened, get the admin
			$returnThis['type'] = "other";
			$returnThis['html'] = "<span style=\"color: #00ffff; font-weight: bold;\">Possible problem:</span> It looks like the carat was deleted, but there was an unexpected response.  Refresh the page.  If the carat remains, please try again.  If you continue to get this message, contact the system admin";
		}
		else
		{
			// most likely the query did not even execute here
			$returnThis['type'] = "fail";
			$returnThis['html'] = "<span style=\"color: #00ffff; font-weight: bold;\">Failure:</span> The carat was not deleted.  Please try again.  (A page refresh may be necessary).";
		}

		$returnThis = json_encode($returnThis);
		print $returnThis;

		die(); // always end script with this when using AJAX / JSON or a 0 / -1 will get appended to the end of the 'print' string
	} // end delete_custom_carat function

	add_action("wp_ajax_edit_custom_carat", "edit_custom_carat"); // for saving carats added/modified
	add_action("wp_ajax_nopriv_edit_custom_carat", "carat_user_must_login"); // for no privelage carat control
	
	function edit_custom_carat()
	{
		// this function will edit the custom carat when the pencil icon is clicked.

		global $wpdb;
		$returnThis = array(); // return the results to AJAX
		$theId = $_REQUEST['dbId'];
		$theTitle = sanitize_text_field($_REQUEST['newTitle']);

		$dbWhere = array("carat_id" => $theId);
		$dbData = array("carat_title" => $theTitle);
		$dbWhereFormat = array("%d");
		$dbDataFormat = array("%s");

		$successCount = $wpdb->update("wp_fits_progmng_carats", $dbData, $dbWhere, $dbDataFormat, $dbWhereFormat);

		if($successCount != FALSE && $successCount == 1)
		{
			// this is what I want, 1 row has been updated
			$returnThis['type'] = "success";
			$returnThis['html'] = "<span style=\"color: #00ff0f; font-weight: bold;\">Success:</span> The carat edit was saved successfully.";
		}
		elseif ($successCount != FALSE && $successCount !=1)
		{
			// something else happened, more than one row was effected. get the admin!
			$returnThis['type'] = "other";
			$returnThis['html'] = "<span style=\"color: #ff0000; font-weight: bold;\">Possible problem:</span> It looks like the carat data was saved, but there was an unexpected response.  More than one carat may have been effected by your edit. Refresh the page.  If two carats have the same name, contact the system admin.  <span style=\"color: #ff0000;\">NOTE:</span> Remember what you were doing and the two carats (or more) effected";
		}
		else
		{
			// most likely the query did not even execute here
			$returnThis['type'] = "fail";
			$returnThis['html'] = "<span style=\"color: #ff0000; font-weight: bold;\">Failure:</span> The carat change was not saved.  Please try again.  (A page refresh may be necessary).";
		}

		$returnThis = json_encode($returnThis);
		print $returnThis;

		die(); // always end script with this when using AJAX / JSON or a 0 / -1 will get appended to the end of the 'print' string
	} // end delete_custom_carat function

	function carat_user_must_login()
	{
		// user must be logged in as admin
		echo "You must login and have admin permission to work with carats.";
	} // end function carat_user_must_login

	/* ** end code for program carat section */
	
	// this is for the program + material discount - added 08-09-2017
	function display_program_material_discount($program_page)
	{
		$stored_include_discount = esc_attr(get_post_meta($program_page->ID, "include_discount", true));
		$include_discount_input = $stored_include_discount == "yes" ? "<input type=\"checkbox\" id=\"include_discount\" name=\"include_discount\" value=\"yes\" checked=\"checked\" />" : "<input type=\"checkbox\" id=\"include_discount\" name=\"include_discount\" value=\"yes\" />";
		$material_product_ids_content = get_post_meta($program_page->ID, "material_product_ids", true);
		$getDiscount_percent_value = get_post_meta($program_page->ID, 'discount_percent', true);
		$discount_percent_value = trim($getDiscount_percent_value) != FALSE ? $getDiscount_percent_value : "30";
		
		?>
			<table id="discount-table" name="discount-table" class="form-table" style="width: 90%;">
				<tr valign="top">
					<td colspan="2">
						<?php echo $include_discount_input; ?> Include discount for material products when purchased with this program?
					</td>
				<tr valign="top">
					<td style="width: 50%; vertical-align: top;">
						<label for="discount_percent">Discount % Off</label><br />
						<input type="text" id="discount_percent" name="discount_percent" style="width: 100%;" value="<?php echo esc_attr($discount_percent_value); ?>" />
					</td>
					<td style="width: 50%; vertical-align: top;">
						<label for="material_product_ids">Product IDs</label><br />
						<textarea id="material_product_ids" name="material_product_ids" rows="3" style="width: 100%;"><?php echo esc_textarea($material_product_ids_content); ?></textarea><br />
						<small>Look under WooCommerce &gt; Products to find this/these number(s). Multiple products must be seperated by commas.</small>
					</td>
				</tr>
			</table>
		<?php
	} // end display_program_material_discount function for material discount metabox
	
	function display_program_page_meta_box($program_page)
	{
		global $wpdb;
		?>
			<div class="wrap">
				<h2>Programs Entry Form</h2>
				<hr>
				<form action="options.php" method="post">
					<?php
						$main_description_settings = array(			"textarea_name" => "main_description",
																"textarea_rows" => 15);
						$main_description_content = 					get_post_meta($program_page->ID, "main_description", true);
						
						$left_date_settings = array(					"textarea_name" => "left_date",
																"textarea_rows" => 8,
																"media_buttons" => false);
						$left_date_content = 						get_post_meta($program_page->ID, "left_date", true);
						
						$left_time_settings = array(					"textarea_name" => "left_time",
																"textarea_rows" => 8,
																"media_buttons" => false);
						$left_time_content = 						get_post_meta($program_page->ID, "left_time", true);
						
						$right_instructor_settings = array(			"textarea_name" => "right_instructor",
																"textarea_rows" => 8,
																"media_buttons" => false);
						$right_instructor_content = 					get_post_meta($program_page->ID, "right_instructor", true);
						
						$right_location_settings = array(				"textarea_name" => "right_location",
																"textarea_rows" => 8,
																"media_buttons" => false);
						$right_location_content =					get_post_meta($program_page->ID, "right_location", true);
						
						$left_fee_settings = array(					"textarea_name" => "left_fee",
																"textarea_rows" => 6,
																"media_buttons" => false);
						$left_fee_content = 						get_post_meta($program_page->ID, "left_fee", true);
								
						// 11/2018 to 01/2019 - this section has to do with the new carat functionality
						// get the custom carats for one array and the default carats for another
						$caratItemNames_sqlStatement = "SELECT * FROM wp_fits_progmng_carats";
						$getProgramCaratItemNames = $wpdb->get_results($caratItemNames_sqlStatement, ARRAY_A);
						$caratItemsArray = array();

						if ($getProgramCaratItemNames != FALSE)
						{
								foreach($getProgramCaratItemNames AS $theCaratItem)
								{
									$caratItemsArray[] = array(     
																"title"        =>   $theCaratItem['carat_title'],
																"name"         =>   $theCaratItem['carat_textarea_name'],
																"role"         =>   $theCaratItem['carat_role'],
																"position"     =>   $theCaratItem['carat_id']
															);
								}
						}

						// use the $caratItemsArray to get the values neede for diplaying the carat items
						for ($x = 0; $x < count($caratItemsArray); $x++)
						{
							unset($content);
							unset($position);
							$content = get_post_meta($program_page->ID, $caratItemsArray[$x]['name'], true);
							$position = get_post_meta($program_page->ID, $theCaratItem[$x]['name']."Position", true);

							// now add these to the array
							$caratItemsArray[$x]['content'] = (strlen($content) > 0 ? $content : "");
							if ($caratItemsArray[$x]['role'] == "default")
							{
									$caratItemsArray[$x]['position'] = (strlen($position) > 0 ? $position : $caratItemsArray[$x]['position']);
							}
							else
							{
									$caratItemsArray[$x]['position'] = (strlen($position) > 0 ? $position : "");
							}
						}

						/* for testing
						print_r($caratItemsArray);
						print "<br /><br />";
						*/
						
						// delete array values without content
						foreach ($caratItemsArray AS $key => $value)
						{
							/*
							print $key."<br />";
							print_r($value);
							print "<br />the content = ".htmlentities($value['content']);
							print "<br /><br />";
							*/
							
							$convertFuckingDbString = str_replace("&nbsp;", ' ', $value['content']); // this is to change &nbsp; to a space when stored in the db - this took way to fucking long to figure out!
							if (strlen(trim($convertFuckingDbString)) == 0)
							{
								unset($caratItemsArray[$key]);
							}
						}

						// for testing -> var_dump($caratItemsArray);

						// sort the multidimentional array by position value (PHP v5.4 on server, can use annonymous functions)
						usort($caratItemsArray, function($a, $b)
						{
							return $a['position'] - $b['position'];
						});
						
						// program type
						$stored_program_type = esc_attr(get_post_meta($program_page->ID, "program_type", true));
						
						if ($stored_program_type == "our")
						{
							$select_program_type = "<option value=\"our\">Our</option>";
						}
						elseif ($stored_program_type == "other")
						{
							$select_program_type = "<option value=\"other\">Other</option>";
						}
						else
						{
							$select_program_type = "<option value=\"\">Choose</option>";
						}
						
						$select_program_type .= "	<option value=\"our\">Our</option>
												<option value=\"other\">Other</option>";
						
						// CEUs
                        $stored_use_ceu = esc_attr(get_post_meta($program_page->ID, "use_ceu", true));
                        $is_stored_ceu_product_num = esc_attr(get_post_meta($program_page->ID, "ceu_product_id", true));
                        $stored_ceu_product_num = ($is_stored_ceu_product_num) ? $is_stored_ceu_product_num : "";
                        $stored_ceu_variant = esc_attr(get_post_meta($program_page->ID, "ceu_variant", true));
                        
                            // 03-22-2019 changing checkbox to dropdown.  also using hidden field for 'use_ceu'
						if(isset($stored_use_ceu) && $stored_use_ceu == "yes")
						{
                            $use_ceu_value = "yes";
						}
						else
						{
                            $use_ceu_value = "";
                        }

                            // get all ceu product IDs and names
                        $cat_args = array(
                            "post_type"         => "product",
                            "post_status"       => "publish",
                            "posts_per_page"    => -1,
                            "order_by"          => "ID",
                            "order"             => "DESC",
                            "tax_query"         => array(
                                array(
                                    "taxonomy"      => "product_cat",
                                    "field"         => "term_id",
                                    "terms"         => 263,
                                    "operator"      => "IN",
                                )
                            )
                        );

                        $ceu_dropdown = "<select id=\"ceu_product_id\" name=\"ceu_product_id\">
                                            <option value=\"choose\">Choose...</option>";

                        $getCEProducts = new WP_Query($cat_args);
                        if($getCEProducts)
                        {
                            // for testing -> var_dump($getCEProducts);
                            $ceProductCount = 1;
                            
                            foreach($getCEProducts->posts AS $theCEProduct)
                            {
                                // for testing -> $tempCEU = "This is a test.<br />";
                                // for testing -> echo "This is a test: ".$ceProductCount." product found. ID: ".$theCEProduct->ID."<br />";
                                if (strlen($theCEProduct->ID) > 0 && $theCEProduct->ID != "")
                                {
                                    if($theCEProduct->ID == $stored_ceu_product_num && $stored_use_ceu == "yes")
                                    {
                                        // this is the product already stored for the program, mark it selected
                                        $ceu_dropdown .= "<option value=\"".$theCEProduct->ID."\" selected=\"selected\">".$theCEProduct->post_title."</option>";
                                    }
                                    else
                                    {
                                        $ceu_dropdown .= "<option value=\"".$theCEProduct->ID."\">".$theCEProduct->post_title."</option>";
                                    }
                                }

                                $ceProductCount++;
                            }
                            
                        }
                        else
                        {
                            $ceu_dropdown .= "<option value=\"choose\">CE database read fail</option>";
                        }
                        
                        $ceu_dropdown .= "</select>";


						
                        // get all variants from database
                        if($stored_ceu_product_num && $stored_ceu_product_num > 0)
                        {
                            $product = get_product($stored_ceu_product_num); // this is the post id for the CEU product
                            
                            if ($product->is_type("variable"))  // at the time of programming, the ceu product is variable, but check just in case it gets changed
                            {
                                $select_ceu_variant = "<select id=\"ceu_variant\" name=\"ceu_variant\">";
                                $product_variations = $product->get_available_variations();
                                
                                foreach ($product_variations AS $variation)
                                {
                                    $post_object = get_post($variation[variation_id]);
                                    $variation_desc = get_post_meta($post_object->ID, "attribute_program-hours", true);
                                    
                                    if ($variation[variation_id] == $stored_ceu_variant)
                                    {
                                        $select_ceu_variant .= "<option value=\"".$variation[variation_id]."\" selected=\"selected\">".$variation_desc."</option>";
                                    }
                                    else
                                    {
                                        $select_ceu_variant .= "<option value=\"".$variation[variation_id]."\">".$variation_desc."</option>";
                                    }
                                }
                                
                                $select_ceu_variant .= "</select>";
                            }
                            else
                            {
                                $select_ceu_variant = "CE product does not seem to be a variable product.";
                            }
                        }
                        else
                        {
                            $select_ceu_variant = "No variants available.";
                        }
						
						// for select date fields
						// months
						$monthArray = array(	1 => "1 - Jan",
											2 => "2 - Feb",
											3 => "3 - Mar",
											4 => "4 - Apr",
											5 => "5 - May",
											6 => "6 - Jun",
											7 => "7 - Jul",
											8 => "8 - Aug",
											9 => "9 - Sep",
											10 => "10 - Oct",
											11 => "11 - Nov",
											12 => "12 - Dec");
						
						$stored_start_month = esc_attr(get_post_meta($program_page->ID, "display_start_month", true));
						$stored_end_month = esc_attr(get_post_meta($program_page->ID, "display_end_month", true));
						$stored_current_start_month = esc_attr(get_post_meta($program_page->ID, "current_start_month", true));
						$stored_current_end_month = esc_attr(get_post_meta($program_page->ID, "current_end_month", true));
						
						$select_month = "	<option value=\"1\">1 - Jan</option>
										<option value=\"2\">2 - Feb</option>
										<option value=\"3\">3 - Mar</option>
										<option value=\"4\">4 - Apr</option>
										<option value=\"5\">5 - May</option>
										<option value=\"6\">6 - Jun</option>
										<option value=\"7\">7 - Jul</option>
										<option value=\"8\">8 - Aug</option>
										<option value=\"9\">9 - Sep</option>
										<option value=\"10\">10 - Oct</option>
										<option value=\"11\">11 - Nov</option>
										<option value=\"12\">12 - Dec</option>";
						
						if($stored_start_month != "")
						{
							$select_month_start = "<option value=\"".$stored_start_month."\">".$monthArray[$stored_start_month]."</option>".$select_month;
						}
						else
						{
							$select_month_start = "<option value=\"\">Month</option>".$select_month;
						}
						
						if($stored_end_month != "")
						{
							$select_month_end = "<option value=\"".$stored_end_month."\">".$monthArray[$stored_end_month]."</option>".$select_month;
						}
						else
						{
							$select_month_end = "<option value=\"\">Month</option>".$select_month;
						}
						
						if($stored_current_start_month != "")
						{
							$select_current_month_start = "<option value=\"".$stored_current_start_month."\">".$monthArray[$stored_current_start_month]."</option>".$select_month;
						}
						else
						{
							$select_current_month_start = "<option value=\"\">Month</option>".$select_month;
						}
						
						if($stored_current_end_month != "")
						{
							$select_current_month_end = "<option value=\"".$stored_current_end_month."\">".$monthArray[$stored_current_end_month]."</option>".$select_month;
						}
						else
						{
							$select_current_month_end = "<option value=\"\">Month</option>".$select_month;
						}
						
						// day
						$stored_start_day = esc_attr(get_post_meta($program_page->ID, "display_start_day", true));
						$stored_end_day = esc_attr(get_post_meta($program_page->ID, "display_end_day", true));
						$stored_current_start_day = esc_attr(get_post_meta($program_page->ID, "current_start_day", true));
						$stored_current_end_day = esc_attr(get_post_meta($program_page->ID, "current_end_day", true));
						
						if($stored_start_day != "")
						{
							$select_day_start = "<option value=\"".$stored_start_day."\">".$stored_start_day."</option>";
						}
						else
						{
							$select_day_start = "<option value=\"\">Day</option>";
						}
						
						if($stored_end_day != "")
						{
							$select_day_end = "<option value=\"".$stored_end_day."\">".$stored_end_day."</option>";
						}
						else
						{
							$select_day_end = "<option value=\"\">Day</option>";
						}
						
						if($stored_current_start_day != "")
						{
							$select_current_day_start = "<option value=\"".$stored_current_start_day."\">".$stored_current_start_day."</option>";
						}
						else
						{
							$select_current_day_start = "<option value=\"\">Day</option>";
						}
						
						if($stored_current_end_day != "")
						{
							$select_current_day_end = "<option value=\"".$stored_current_end_day."\">".$stored_current_end_day."</option>";
						}
						else
						{
							$select_current_day_end = "<option value=\"\">Day</option>";
						}
						
						for ($day = 1; $day < 32; $day++)
						{
							$select_day_start .= "<option value=\"".$day."\">".$day."</option>";
							$select_day_end .= "<option value=\"".$day."\">".$day."</option>";
							$select_current_day_start .= "<option value=\"".$day."\">".$day."</option>";
							$select_current_day_end .= "<option value=\"".$day."\">".$day."</option>";
						}
						
						// year
						$stored_start_year = esc_attr(get_post_meta($program_page->ID, "display_start_year", true));
						$stored_end_year = esc_attr(get_post_meta($program_page->ID, "display_end_year", true));
						$stored_current_start_year = esc_attr(get_post_meta($program_page->ID, "current_start_year", true));
						$stored_current_end_year = esc_attr(get_post_meta($program_page->ID, "current_end_year", true));
						
						if($stored_start_year != "")
						{
							$select_year_start = "<option value=\"".$stored_start_year."\">".$stored_start_year."</option>";
						}
						else
						{
							$select_year_start = "<option value=\"\">Year</option>";
						}
						
						if($stored_end_year != "")
						{
							$select_year_end = "<option value=\"".$stored_end_year."\">".$stored_end_year."</option>";
						}
						else
						{
							$select_year_end = "<option value=\"\">Year</option>";
						}
						
						if($stored_current_start_year != "")
						{
							$select_current_year_start = "<option value=\"".$stored_current_start_year."\">".$stored_current_start_year."</option>";
						}
						else
						{
							$select_current_year_start = "<option value=\"\">Year</option>";
						}
						
						if($stored_current_end_year != "")
						{
							$select_current_year_end = "<option value=\"".$stored_current_end_year."\">".$stored_current_end_year."</option>";
						}
						else
						{
							$select_current_year_end = "<option value=\"\">Year</option>";
						}
						
						
						$currentYear = date("Y");
						for ($y = -1; $y < 5; $y++)
						{
							$displayYear = $currentYear + $y;
							$select_year_start .= "<option value=\"".$displayYear."\">".$displayYear."</option>";
							$select_year_end .= "<option value=\"".$displayYear."\">".$displayYear."</option>";
							$select_current_year_start .= "<option value=\"".$displayYear."\">".$displayYear."</option>";
							$select_current_year_end .= "<option value=\"".$displayYear."\">".$displayYear."</option>";
						}
					?>
					<table id="form-table-1" name="form-table-1" class="form-table" style="width: 90%;">
						<tr valign="top">
							<td colspan="2">
								<label for="program_title">Program Title</label><br />
								<input type="text" id="program_title" name="program_title" style="width: 100%;" value="<?php echo esc_attr(get_post_meta($program_page->ID, 'program_title', true)); ?>" />
								<small>Most often, this will be the same as the title used above to create the permalink.</small>
							</td>
						</tr>
						<tr valign="top">
							<td colspan="2">
								<label for="program_sponsor">Sponsor Line</label><br />
								<input type="text" id="program_sponsor" name="program_sponsor" style="width: 100%;" value="<?php echo esc_attr(get_post_meta($program_page->ID, 'program_sponsor', true)); ?>" />
							</td>
						</tr>
						<tr valign="top">
							<td style="width: 50%; vertical-align: top;">
								<label for="program_type">Program Type</label><br />
								<select id="program_type" name="program_type" style="width: 90%;">
									<?php echo $select_program_type; ?>
								</select>
							</td>
							<td style="width: 50%; vertical-align: top;">
								<label for="shopping_product_id">Shopping Product ID</label><br />
								<input type="text" id="shopping_product_id" name="shopping_product_id" style="width: 90%;" value="<?php echo esc_attr(get_post_meta($program_page->ID, 'shopping_product_id', true)); ?>" /><br />
								<small>Look under WooCommerce &gt; Products to find this number.</small>
							</td>
						</tr>
						<tr valign="top">
							<td style="vertical-align: top;">
								<label for="ceu_product_id">Choose CE Product</label><br />
								<?php echo $ceu_dropdown; ?><br />
								<small>If this program is elegable for CEs, choose the product from the dropdown.</small>
							</td>
							<td id="ceu_variant_select_holder" style="vertical-align: top;">
								<label for="ceu_variant">Choose Program CE Hours</label><br />
								<?php echo $select_ceu_variant; ?>
							</td>
						</tr>
						<tr valign="top">
							<td>
								<label>Starting Display Date</label><br />
								<select id="display_start_month" name="display_start_month"><?php echo $select_month_start; ?></select> <select id="display_start_day" name="display_start_day"><?php echo $select_day_start; ?></select> <select id="display_start_year" name="display_start_year"><?php echo $select_year_start; ?></select><br />
								<small>Date program begins.</small>
							</td>
							<td>
								<label>Ending Display Date</label><br />
								<select id="display_end_month" name="display_end_month"><?php echo $select_month_end; ?></select> <select id="display_end_day" name="display_end_day"><?php echo $select_day_end; ?></select> <select id="display_end_year" name="display_end_year"><?php echo $select_year_end; ?></select><br />
								<small>Date the program ends.</small>
							</td>
						</tr>
						<tr valign="top" style="vertical-align: top;">
							<td valign="top" style="vertical-align: top;">
								<label>Current Status - Start Date</label><br />
								<select id="current_start_month" name="current_start_month"><?php echo $select_current_month_start; ?></select> <select id="current_start_day" name="current_start_day"><?php echo $select_current_day_start; ?></select> <select id="current_start_year" name="current_start_year"><?php echo $select_current_year_start; ?></select><br />
								<small>This is the date to begin displaying the program on the 'current' listing page. This does not have to be the first day the program is available.</small>
							</td>
							<td valign="top" style="vertical-align: top;">
								<label>Current Status - End Date</label><br />
								<select id="current_end_month" name="current_end_month"><?php echo $select_current_month_end; ?></select> <select id="current_end_day" name="current_end_day"><?php echo $select_current_day_end; ?></select> <select id="current_end_year" name="current_end_year"><?php echo $select_current_year_end; ?></select><br />
								<small>Last day for display before moving to 'one year archive' page.</small>
							</td>
						</tr>
					</table>
					<h3>Program Description:</h3>
					<?php wp_editor($main_description_content, "main_description", $main_description_settings); ?>
					<table id="form-table-2" name="form-table-2" class="form-table">
						<tr valign="top">
							<td>
								<h3>Date:</h3>
								<?php wp_editor($left_date_content, "left_date", $left_date_settings); ?>
							</td>
							<td>
								<h3>Instructor:</h3>
								<?php wp_editor($right_instructor_content, "right_instructor", $right_instructor_settings); ?>
							</td>
						</tr>
						<tr valign="top">
							<td>
								<h3>Time:</h3>
								<?php wp_editor($left_time_content, "left_time", $left_time_settings); ?>
							</td>
							<td>
								<h3>Location:</h3>
								<?php wp_editor($right_location_content, "right_location", $right_location_settings); ?>
							</td>
						</tr>
						<tr valign="top">
							<td>
								<h3>Fee:</h3>
								DO NOT add the 'add to cart' button or link here.
								<?php wp_editor($left_fee_content, "left_fee", $left_fee_settings); ?>
							</td>
							<td>
								
							</td>
						</tr>
					</table>
					<hr />
					<h3>Carat Section</h3>
						<?php
							// for now, carats are going to be limited to 25
							for($x = 1; $x <= 25; $x++)
							{
								$positionOptions .= "<option value=\"".$x."\">".$x."</option>";
							}
						?>
					Enter information for each of the caret sections in the editors below.  If there is no information, simply leave that editor blank.  When the Program Pages are assembled, sections without information will be skipped (will not be displayed on the public-facing page).<br />
					<table id="form-table-3" name="form-table-3" class="form-table">
						<tr>
							<td colspan="2">
								<h4>Default Carats</h4>
							</td>
						</tr>
						<?php
							$defaultCaratArray = getCaratItems("default", $program_page->ID);

							/*
							// for testing
							print " <br /><br />Test results: <br />
									var_dump:<br />";
							var_dump($defaultCaratArray);
							print "<br />print_r:<br />";
							print_r($defaultCaratArray);
							print "<br />";

							if(is_array($defaultCaratArray))
							{
								print "<br /><br />The returned value for defaultCaratArray is an array.<br />";
								print $defaultCaratArray."<br />";
								print "number of array items: ".count($defaultCaratArray)."<br /><br />";
								for($x = 0; $x < count($defaultCaratArray); $x++)
								{
									/*
									foreach($defaultCaratArray[$x] AS $key => $value)
									{
										print "<br /><br />Test results: <br />";
										print $key."<br />";
										print_r($value);
										print "<br />the content = ".htmlentities($value['content']);
										print "<br /><br />";
										
									}
									
								}
							}
							else
							{
								print "<br /><br />defaultCaratArray is not an array!<br />";
							}
							// end testing
							*/

							foreach($defaultCaratArray AS $key => $value)
							{

								$currentSettings = array(     "textarea_name" => $value['name'],
																"textarea_rows" => 10);
								
								print "   <tr valign=\"top\">
												<td style=\"padding-bottom: 0px !important;\">
													<h3>".$value['title']."</h3>
												</td>
												<td style=\"padding-bottom: 0px !important;\">
													<select id=\"".$value['name']."Position\" name=\"".$value['name']."Position\" style=\"float: right;\">
														<option value=\"".$value['position']."\" selected>".$value['position']."</option>
														".$positionOptions."
													</select>
												</td>
											</tr>
											<tr valign=\"top\">
												<td colspan=\"2\" style=\"padding-top: 0px !important;\">";
								wp_editor($value['content'], $value['name'], $currentSettings);
								print "        </td>
											</tr>";
							
								unset($currentSettings);
							}
						?>
						<tr>
							<td colspan="2">
								<hr />
							</td>
						</tr>
						<tr>
							<td colspan="2">
								<h4>Custom Carats</h4>
							</td>
						</tr>
						<?php
							$customCaratArray = getCaratItems("custom", $program_page->ID);

							foreach($customCaratArray AS $key => $value)
							{
								$currentSettings = array(     "textarea_name" => $value['name'],
																"textarea_rows" => 10);
								
								print "   <tr valign=\"top\">
												<td style=\"padding-bottom: 0px !important;\">
													<h3>".$value['title']."</h3>
												</td>
												<td style=\"padding-bottom: 0px !important;\">
													<select id=\"".$value['name']."Position\" name=\"".$value['name']."Position\" style=\"float: right;\">
														<option value=\"".$value['position']."\" selected>".$value['position']."</option>
														".$positionOptions."
													</select>
												</td>
											</tr>
											<tr valign=\"top\">
												<td colspan=\"2\" style=\"padding-top: 0px !important;\">";
								wp_editor($value['content'], $value['name'], $currentSettings);
								print "        </td>
											</tr>";
							
								unset($currentSettings);
							}
						?>
					</table>
				</form>
			</div><!-- end div class="wrapper" -->
            <script type="text/javascript">
                // test right now
                jQuery(document).ready(function($)
                {
                    // for testing -> alert("The custom js has loaded.");

                    jQuery("#ceu_product_id").change(function()
                    {
                        var currentCE_id = jQuery("#ceu_product_id option:selected").val();
                        // for testing -> alert("The CE product selected is: " + currentCE_id);
                        // for testing -> console.log ("ajaxurl is: " + ajaxurl);

                        var data = {
                            "action": "ceu_AJAX",
                            "ceu_id": currentCE_id
                        };

                        // give user a sign something is happening
                        jQuery("#ceu_variant_select_holder").html("working...");

                        jQuery.post(ajaxurl, data, function(response)
                        {
                            // replace the content of the td id=ceu_variant_select_holder
                            // for testing -> alert("Is response and array: " + jQuery.isArray(response));
                            console.log("The response: " + response);

                            if(response.result == "good")
                            {
                                jQuery("#ceu_variant_select_holder").html(""); // clear if first, just in case
                                jQuery("#ceu_variant_select_holder").html(response.html);
                            }
                            else if (response.result == "error")
                            {
                                jQuery("#ceu_variant_select_holder").html("There has been an error retrieving variant data.");
                            }
                            else
                            {
                                jQuery("#ceu_variant_select_holder").html("There has been an unknown error.<br />" + response);
                            }
                        }, "json");
                    });
                });
            </script>
		<?php
    } // end display_program_page_meta_box function
    
    /*  This is the function to handle the AJAX call that changes the ceu variant select. */
    function ceu_AJAX()
    {
        global $wpdb;
        $passedCEid = intval($_POST['ceu_id']);
        $returnThis = array();
        $returnThis['passed_id'] = $passedCEid;

        // get all variants from database
        $product = get_product($passedCEid);
        
        if ($product->is_type("variable"))  // at the time of programming, the ceu product is variable, but check just in case it gets changed
        {
            $returnThis['result'] = "good";
            $select_ceu_variant = "<label for=\"ceu_variant\">Choose Program CE Hours</label><br /><select id=\"ceu_variant\" name=\"ceu_variant\">";
            $product_variations = $product->get_available_variations();
            
            foreach ($product_variations AS $variation)
            {
                $post_object = get_post($variation[variation_id]);
                $variation_desc = get_post_meta($post_object->ID, "attribute_program-hours", true);
                
                $select_ceu_variant .= "<option value=\"".$variation[variation_id]."\">".$variation_desc."</option>";
            }
            
            $select_ceu_variant .= "</select>";
        }
        else
        {
            $returnThis['result'] = "error";
            $select_ceu_variant = "CE product does not seem to be a variable product.";
        }

        $returnThis['html'] = $select_ceu_variant;
        $returnThis = json_encode($returnThis);
        print $returnThis;

        die(); // needs to be at the end of all AJAX calls in WordPress
    } // end ceu_AJAX function

    add_action("wp_ajax_ceu_AJAX", "ceu_AJAX");
	
	function getCaratItems($theRole, $programId)
	{
		// 11/2018 to 01/2019 - this section has to do with the new carat functionality
		// get the custom carats for one array and the default carats for another

		global $wpdb;

		$caratItemNames_sqlStatement = "SELECT * FROM wp_fits_progmng_carats WHERE carat_role = '".$theRole."' ORDER BY carat_order DESC";
		$getProgramCaratItemNames = $wpdb->get_results($caratItemNames_sqlStatement, ARRAY_A);
		$caratItemsArray = array();

		if ($getProgramCaratItemNames != FALSE)
		{
				foreach($getProgramCaratItemNames AS $theCaratItem)
				{
					$caratItemsArray[] = array(     
												"title"        =>   $theCaratItem['carat_title'],
												"name"         =>   $theCaratItem['carat_textarea_name'],
												"role"         =>   $theCaratItem['carat_role'],
												"position"     =>   $theCaratItem['carat_order']
											);
				}
		}

		// use the $caratItemsArray to get the values neede for diplaying the carat items
		for ($x = 0; $x < count($caratItemsArray); $x++)
		{
			unset($content);
			unset($position);
			$content = get_post_meta($programId, $caratItemsArray[$x]['name'], true);
			$position = get_post_meta($programId, $caratItemsArray[$x]['name']."Position", true);

			// now add these to the array
			// for testing
			// $caratItemsArray[$x]['content'] = (strlen($content) > 0 ? $content : "error -> ID: ".$programId." Name: ".$caratItemsArray[$x]['name']);
			$caratItemsArray[$x]['content'] = (strlen($content) > 0 ? $content : "");
				// this will override the default positions from the wp_fits_progmng_carats table, if the user has set special positions.
			if ($caratItemsArray[$x]['role'] == "default")
			{
					$caratItemsArray[$x]['position'] = (strlen($position) > 0 ? $position : $caratItemsArray[$x]['position']);
			}
			else
			{
					$caratItemsArray[$x]['position'] = (strlen($position) > 0 ? $position : $caratItemsArray[$x]['position']);
			}
		}

		/* for testing
		print_r($caratItemsArray);
		print "<br /><br />";
		*/
		
		// delete array values without content
		/* don't want to do this with new version 01/2019
		foreach ($caratItemsArray AS $key => $value)
		{
			// for testing
			print $key."<br />";
			print_r($value);
			print "<br />the content = ".htmlentities($value['content']);
			print "<br /><br />";
			// end testing
			
			$convertFuckingDbString = str_replace("&nbsp;", ' ', $value['content']); // this is to change &nbsp; to a space when stored in the db - this took way to fucking long to figure out!
			if (strlen(trim($convertFuckingDbString)) == 0)
			{
				unset($caratItemsArray[$key]);
			}
		}
		*/

		// for testing -> var_dump($caratItemsArray);

		// sort the multidimentional array by position value (PHP v5.4 on server, can use annonymous functions)
		usort($caratItemsArray, function($a, $b)
		{
			return $a['position'] - $b['position'];
		});

		return $caratItemsArray;
				
	} // end getCaratItems function
	
	function save_program_page_fields($program_page_id, $program_page)
	{
		// the default post stuff is auto saved.  I do not have 'post_content' in the admin dashboard, so it remains empty in the db
		// I want the post_content to be the same as the custom 'main_description' so that rt_exerpt can be used in the list page.
		$contentArray = array(	"ID" => $program_page_id,
							"post_content" => $_POST['main_description']);
		
		// check post type for program_pages
		if($program_page->post_type == "program_pages")
		{
			if(isset($_POST['main_description']) && $_POST['main_description'] != "")
			{
				update_post_meta($program_page_id, "main_description", $_POST['main_description']);
				
				// have to stop infinite loop here
				remove_action('save_post', 'save_program_page_fields');
				wp_update_post($contentArray);
				add_action('save_post', 'save_program_page_fields');
			}
			
			if(isset($_POST['left_date']))
			{
				update_post_meta($program_page_id, "left_date", $_POST['left_date']);
			}
			
			if(isset($_POST['left_time']))
			{
				update_post_meta($program_page_id, "left_time", $_POST['left_time']);
			}
			
			if(isset($_POST['left_fee']))
			{
				update_post_meta($program_page_id, "left_fee", $_POST['left_fee']);
			}
			
			if(isset($_POST['right_instructor']))
			{
				update_post_meta($program_page_id, "right_instructor", $_POST['right_instructor']);
			}
			
			if(isset($_POST['right_location']))
			{
				update_post_meta($program_page_id, "right_location", $_POST['right_location']);
			}
            
            /* 03/30/2019 - change how CEUs are displayed / used. now they are a dropdown with product ID value
			$ceu_value = isset($_POST['use_ceu']) ? "yes" : "";
			update_post_meta($program_page_id, "use_ceu", $_POST['use_ceu']);
            */
            
            if(isset($_POST['ceu_product_id']) && $_POST['ceu_product_id'] != "choose")
            {
                // first save the ceu product ID
                update_post_meta($program_page_id, "ceu_product_id", $_POST['ceu_product_id']);

                // next set 'use_ceu' to yes in db
                update_post_meta($program_page_id, "use_ceu", "yes");
            }
			
			if(isset($_POST['ceu_variant']))
			{
				update_post_meta($program_page_id, "ceu_variant", $_POST['ceu_variant']);
			}
			
			// 02/04/2018 change the default carat save to work like the custom save (so that the position is saved with min code). remember it's "caret_"
			foreach($_POST AS $key => $value)
			{
				if(substr($key,0,6) == "caret_")
				{
					update_post_meta($program_page_id, $key, $value);
				}
			}

			/* original default carat save code
			if(isset($_POST['caret_instructorBio']))
			{
				update_post_meta($program_page_id, "caret_instructorBio", $_POST['caret_instructorBio']);
			}
			
			if(isset($_POST['caret_testimonials']))
			{
				update_post_meta($program_page_id, "caret_testimonials", $_POST['caret_testimonials']);
			}
			
			if(isset($_POST['caret_continuingEducation']))
			{
				update_post_meta($program_page_id, "caret_continuingEducation", $_POST['caret_continuingEducation']);
			}
			
			if(isset($_POST['caret_directionsParking']))
			{
				update_post_meta($program_page_id, "caret_directionsParking", $_POST['caret_directionsParking']);
			}
			
			if(isset($_POST['caret_forMoreInfo']))
			{
				update_post_meta($program_page_id, "caret_forMoreInfo", $_POST['caret_forMoreInfo']);
			}
			
			if(isset($_POST['caret_syllabus']))
			{
				update_post_meta($program_page_id, "caret_syllabus", $_POST['caret_syllabus']);
			}
			
			if(isset($_POST['caret_programSchedule']))
			{
				update_post_meta($program_page_id, "caret_programSchedule", $_POST['caret_programSchedule']);
			}
			
			if(isset($_POST['caret_financialAid']))
			{
				update_post_meta($program_page_id, "caret_financialAid", $_POST['caret_financialAid']);
			}
			
			if(isset($_POST['caret_whatToBring']))
			{
				update_post_meta($program_page_id, "caret_whatToBring", $_POST['caret_whatToBring']);
			}
			
			if(isset($_POST['caret_preRegistrationInfo']))
			{
				update_post_meta($program_page_id, "caret_preRegistrationInfo", $_POST['caret_preRegistrationInfo']);
			}
			* end original default carat save code */
			
			if(isset($_POST['program_title']))
			{
				update_post_meta($program_page_id, "program_title", $_POST['program_title']);
			}
			
			if(isset($_POST['shopping_product_id']))
			{
				update_post_meta($program_page_id, "shopping_product_id", $_POST['shopping_product_id']);
			}
			
			if(isset($_POST['display_start_month']))
			{
				update_post_meta($program_page_id, "display_start_month", $_POST['display_start_month']);
			}
			
			if(isset($_POST['display_start_day']))
			{
				update_post_meta($program_page_id, "display_start_day", $_POST['display_start_day']);
			}
			
			if(isset($_POST['display_start_year']))
			{
				update_post_meta($program_page_id, "display_start_year", $_POST['display_start_year']);
			}
			
			if(isset($_POST['display_end_month']))
			{
				update_post_meta($program_page_id, "display_end_month", $_POST['display_end_month']);
			}
			
			if(isset($_POST['display_end_day']))
			{
				update_post_meta($program_page_id, "display_end_day", $_POST['display_end_day']);
			}
			
			if(isset($_POST['display_end_year']))
			{
				update_post_meta($program_page_id, "display_end_year", $_POST['display_end_year']);
			}
			
			if(isset($_POST['current_start_month']))
			{
				update_post_meta($program_page_id, "current_start_month", $_POST['current_start_month']);
			}
			
			if(isset($_POST['current_start_day']))
			{
				update_post_meta($program_page_id, "current_start_day", $_POST['current_start_day']);
			}
			
			if(isset($_POST['current_start_year']))
			{
				update_post_meta($program_page_id, "current_start_year", $_POST['current_start_year']);
			}
			
			if(isset($_POST['current_end_month']))
			{
				update_post_meta($program_page_id, "current_end_month", $_POST['current_end_month']);
			}
			
			if(isset($_POST['current_end_day']))
			{
				update_post_meta($program_page_id, "current_end_day", $_POST['current_end_day']);
			}
			
			if(isset($_POST['current_end_year']))
			{
				update_post_meta($program_page_id, "current_end_year", $_POST['current_end_year']);
			}
			
			if(isset($_POST['program_type']) && $_POST['program_type'] != "")
			{
				update_post_meta($program_page_id, "program_type", $_POST['program_type']);
			}
			
			if(isset($_POST['program_sponsor']))
			{
				update_post_meta($program_page_id, "program_sponsor", $_POST['program_sponsor']);
			}
			
			// added for program + material discount 08-09-2017
			$include_discount_value = isset($_POST['include_discount']) ? "yes" : "";
			update_post_meta($program_page_id, "include_discount", $_POST['include_discount']);
			
			if(isset($_POST['discount_percent']))
			{
				update_post_meta($program_page_id, "discount_percent", $_POST['discount_percent']);
			}
			
			if(isset($_POST['material_product_ids']))
			{
				update_post_meta($program_page_id, "material_product_ids", $_POST['material_product_ids']);
			}
			// end program + material discount save
			
			// 02/04/2019 - added for custom carat save -> all custom carat textarea names start with "carat_" old carats use the misspelled "caret_" (lucky mistake)
			foreach($_POST AS $key => $value)
			{
				if(substr($key,0,6) == "carat_")
				{
					update_post_meta($program_page_id, $key, $value);
				}
			}
			// end custom carat save
			
			if ($_POST['dispaly_start_month'] != "Month" && $_POST['display_start_day'] != "Day" && $_POST['display_start_year'] != "Year")
			{
				// added 09-09-2015 - client wants the lists displayed by 'most current' (whatever that means!).  Adding 'orderBy_date' for this purpose
				$orderByDate = mktime(0,0,1,$_POST['display_start_month'],$_POST['display_start_day'],$_POST['display_start_year']);
				
				update_post_meta($program_page_id, "orderBy_date", $orderByDate);
			}
		}
	} // end save_program_page_fields function
	
    add_action("save_post", "save_program_page_fields", 10, 2);
    
    // this function is to get coupon info based on 'post_title' and determine if the coupon is for given program and amount to discount if so
    // returns the discount amount or 'not_applicable' or 'error' if any problem
    function roster_process_coupon($couponNames, $productIds, $programProductId, $lineItemDollars, $itemQuantity)
    {
        global $wpdb;
        $couponId = 0;
        $loopIndex = 0;
        $codeValues = array();
        $discountValues = array();
        $useProdId = array();
        $useCatId = array();
        $returnThis = array();
            /* use for the returnThis array
                ['code'] -> comma seperated string of codes (names) of coupons that apply to this program
                ['amount'] -> total amount to be subtracted from the order total. if this is a percent value use decimal
            */
        $allProgramCategories = array();
        $productPrice = 0;
        $discountTotals = array();

        // get categories for the $value (product id)
        $args = array(	"type"			    =>	"product",
                        "child_of"		    =>	0,
                        "parent"			=>	193,
                        "orderby"			=>	"name",
                        "order"			    =>	"ASC",
                        "hide_empty"		=>	1,
                        "hierarchical"		=>	1,
                        "exclude"			=>	"",
                        "include"			=>	"",
                        "number"			=>	"",
                        "taxonomy"		    =>	"product_cat",
                        "pad_counts"		=>	false);

        $categories = get_categories($args);

        if ($categories)
        {
            foreach($categories AS $category)
            {
                $allProgramCategories[] = $category->term_id;
            }
        }

        // couponNames is an array, must loop through each value to get info and process
        foreach($couponNames AS $couponNames_key => $couponNames_value)
        {
            foreach($couponNames_value AS $coupon_name)
            {
                if($coupon_name == "promo30")
                {
                    break; // this is a 'just in case'
                }

                // get coupon info
                $coupon_sql = "SELECT ID FROM wp_posts WHERE post_title = '".$coupon_name."' AND post_type = 'shop_coupon'";
                $getCouponInfo = $wpdb->get_results($coupon_sql);

                if($getCouponInfo != FALSE)
                {
                    foreach($getCouponInfo AS $theCouponInfo)
                    {
                        $couponId = $theCouponInfo->ID;

                        if ($couponId != 0)
                        {
                            
                            $includeProducts = get_post_meta($couponId, "product_ids", true);  // returns comma separated string of ids (make array)
                            $excludeProducts = get_post_meta($couponId, "exclude_product_ids", true); // returns comma seperated string of ids (make array)
                            $includeCategories = get_post_meta($couponId, "product_categories", true); // returns array
                            $excludeCategories = get_post_meta($couponId, "exclude_product_categories", true); // returns array
                            $discountType = get_post_meta($couponId, "discount_type", true); // this is a string (search for 'fixed' or 'percent')
                            $discountAmount = get_post_meta($couponId, "coupon_amount", true); // number that represents dollar amount of percent off amount
                            $useLimit = get_post_meta($couponId, "limit_usage_to_x_items", true); // this limits the number of items the coupon can be used with in the cart
                            
                            $includeProductsArray = explode(",", $includeProducts);
                            $excludeProductsArray = explode(",", $excludeProducts);
                            // for testing ->
                            $stringifyProductIds = implode(", ", $productIds);
                            $codeValues[$loopIndex] = $stringifyProductIds;
                            // end testing

                            // for testing ->
                                // if(count($allProgramCategories) > 0 )
                                // {
                                //     $tempCodeValue = "";
                                //     foreach($allProgramCategories AS $key => $value)
                                //     {
                                //         $tempCodeValue .= $value."<br />";
                                //     }
                                //     $codeValues[$loopIndex] = count($allProgramCategories)." ".$tempCodeValue;
                                // }
                                // else
                                // {
                                //     $codeValues[$loopIndex] = "no cats";
                                // }
                                // $codeValues[$loopIndex] = $includeCategories;
                            //end testing

                            // ******* product id stuff *******
                            foreach($productIds AS $prod_key => $prod_id)
                            {
                                $useProdId[$prod_id] = "no_say"; // this will show if the product id is not in the 'include' or 'exclude' fields
                                
                                if (in_array($prod_id, $includeProductsArray))
                                {
                                    $useProdId[$prod_id] = in_array($prod_id, $excludeProductsArray) ? "do_not_use" : "use";
                                }
                                else
                                {
                                    $useProdId[$prod_id] = in_array($prod_id, $excludeProductsArray) ? "do_not_us" : "no_say";
                                }

                                $programProdCats = wp_get_post_terms($prod_id, "product_cat", array('fields' => 'all'));
                                
                                $catCount = 0;
                                $preCatDiscount = array();
                                foreach($programProdCats AS $aProgramProdCat)
                                {
                                    $preCatDiscount[$catCount] = in_array($aProgramProdCat->term_id, $includeCategories) ? (in_array($aProgramProdCat, $excludeCategories) ? "do_not_use" : "use") : "do_not_use";
                                    $catCount++;
                                }

                                $catDiscount[$prod_id] = in_array("use", $preCatDiscount) ? "use" : "do_not_use";

                                $codeValues[$loopIndex] = count($catDiscount[$prod_id])." ".$prod_id;
                            }

                            $count_useProdId_loops = 0;
                            foreach($useProdId AS $prod_key => $prod_value)
                            {
                                if ($prod_value === "use")
                                {
                                    $codeValues[$loopIndex] = $coupon_name;
                                    // 04-22-2019 @ 23:30 changed to line below -> $productPrice = rt_get_product_price($prod_key);
                                    $productPrice = $linItemDollars[$prod_key];
                                    // 04-23-2019 @ 00:49 add quantity multiplier - this is the number of products in the cart or the usage limit for the coupon, whichever is smaller
                                    if($useLimit && $useLimit > 0)
                                    {
                                        $quantityMultiplier = $itemQuantity[$prod_key] > $useLimit ? $useLimit : $itemQuantity[$prod_key];
                                    }
                                    else
                                    {
                                        $quantityMultiplier = $itemQuantity[$prod_key];
                                    }

                                    if(strlen($discountType) > 0)
                                    {
                                        if(stripos($discountType, "fixed") !== FALSE)
                                        {
                                            $discountValues[$count_useProdId_loops] = $discountAmount * $quantityMultiplier;
                                        }
                                        elseif(stripos($discountType, "percent") !== FALSE)
                                        {
                                            $discountValues[$count_useProdId_loops] = ($productPrice * $discountAmount * 0.01) * $quantityMultiplier;
                                        }
                                        else
                                        {
                                            $discountValues[$count_useProdId_loops] = 0;
                                        }
                                        
                                    }
                                    else
                                    {
                                        $discountValues[$count_useProdId_loops] = 0;
                                    }

                                    // for testing -> $codeValues[$loopIndex] = $coupon_name." $".$discountValues[$count_useProdId_loops];
                                } // end if $useProdId yes
                                elseif ($prod_value === "no_say")
                                {
                                    if($catDiscount[$prod_key] === "use")
                                    {
                                        $codeValues[$loopIndex] = $coupon_name;

                                        // 04-22-2019 @ 23:30 changed to line below -> $productPrice = rt_get_product_price($prod_key);
                                        $productPrice = $lineItemDollars[$prod_key];
                                        // 04-23-2019 @ 00:49 add quantity multiplier - this is the number of products in the cart or the usage limit for the coupon, whichever is smaller
                                        if($useLimit && $useLimit > 0)
                                        {
                                            $quantityMultiplier = $itemQuantity[$prod_key] > $useLimit ? $useLimit : $itemQuantity[$prod_key];
                                        }
                                        else
                                        {
                                            $quantityMultiplier = $itemQuantity[$prod_key];
                                        }

                                        if(strlen($discountType) > 0)
                                        {
                                            if(stripos($discountType, "fixed") !== FALSE)
                                            {
                                                $discountValues[$count_useProdId_loops] = $discountAmount * $quantityMultiplier;
                                            }
                                            elseif(stripos($discountType, "percent") !== FALSE)
                                            {
                                                $discountValues[$count_useProdId_loops] = ($productPrice * $discountAmount * 0.01) * $quantityMultiplier;
                                            }
                                            else
                                            {
                                                $discountValues[$count_useProdId_loops] = 0;
                                            }
                                            
                                        }
                                        else
                                        {
                                            $discountValues[$count_useProdId_loops] = 0;
                                        }

                                        // for testing -> $codeValues[$loopIndex] = $coupon_name." $".$productPrice; //$discountValues[$count_useProdId_loops];

                                    }
                                } // end if $useProdId no_say
                                else
                                {
                                    // do nothing
                                }

                                $count_useProdId_loops++;
                            }

                            // for testing ->
                            //$codeValues[$loopIndex] = $couponId;
                            //$discountValues[$loopIndex] = 3000;
                            // end testing
                        }
                        else
                        {
                            $codeValues[$loopIndex] = "couponidZero";
                            $discountValues[$loopIndex] = 2000;  // for testing -> production change value to 0
                        }
                    } // end foreach getCouponInfo
                }
                else
                {
                    $codeValues[$loopIndex] = "error";
                    $discountValues[$loopIndex] = 1000;
                }
                
                $discountTotals[$loopIndex] = array_sum($discountValues);

                $loopIndex++;
            } // end inner foreach (on $couponNames_value)
        }

        // join the code values into a string
        $stringifyCodes = implode("<br />", $codeValues);
        // add the discount amounts
        $sumDiscounts = array_sum($discountTotals);

        $returnThis = [
            "codes"     => $stringifyCodes,
            "discount"  => $sumDiscounts
        ];
        return $returnThis;
    } // end roster_process_coupon function

    function rt_get_product_price($useProdId)
    {
        $productInfo_sql = "SELECT meta_value FROM wp_postmeta WHERE post_id = ".$useProdId." AND  meta_key = '_price'";
        $getProductInfo = $wpdb->get_results($productInfo_sql);

        if($getProductInfo != FALSE)
        {
            foreach($getProductInfo AS $theProductInfo)
            {
                $productPrice = $theProductInfo->meta_value;
            }
        }

        return $productPrice;
    } // end rt_get_product_price function
?>
