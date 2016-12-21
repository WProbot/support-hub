<div class="wrap">
	<h2>
		<?php _e('Support Hub Inbox','support_hub');?>
		<!-- <a href="?page=support_hub_compose" class="add-new-h2"><?php _e('Compose','support_hub');?></a> -->
	</h2>
    <?php

    // what search keys do we want to save between states?
    $save_search = array('layout_type','orderquery');
    foreach($save_search as $save){
        if(isset($_REQUEST[$save])){
            $_SESSION['shub_save_'.$save] = $_REQUEST[$save];
        }else if(isset($_SESSION['shub_save_'.$save])){
            $_REQUEST[$save] = $_SESSION['shub_save_'.$save]; // please don't stab me.
        }
    }


    $layout_type = SupportHub::getInstance()->get_current_layout_type();

	// grab a mysql resource from all available social plugins (hardcoded for now - todo: hook)
	$search = isset($_REQUEST['search']) && is_array($_REQUEST['search']) ? $_REQUEST['search'] : array();
	if(!isset($search['shub_status'])){
		$search['shub_status'] = _shub_MESSAGE_STATUS_UNANSWERED;
	}
    $order = array();
    if(!empty($_REQUEST['orderquery'])) {
        $bits = explode(':',$_REQUEST['orderquery']);
        $order = array(
            'orderby' => $bits[0],
            'order' => $bits[1],
        );
    }else{
        $order = array(
            'orderby' => 'shub_column_time',
            'order' => 'desc',
        );
    }

	// retuin a combined copy of all available messages, based on search, as a MySQL resource
	// so we can loop through them on the global messages combined page.



    $myListTable = new SupportHubMessageList(array(
        'screen' => 'shub_inbox'
    ));
    $myListTable->set_columns( array(
		'cb' => 'Select All',
		'shub_column_account' => __( 'Account', 'support_hub' ),
		'shub_column_product' => __( 'Product', 'support_hub' ),
		'shub_column_time'    => __( 'Time', 'support_hub' ),
		'shub_column_from'    => __( 'From', 'support_hub' ),
		'shub_column_summary'    => __( 'Summary', 'support_hub' ),
		'shub_column_action'    => __( 'Action', 'support_hub' ),
	) );
    /*$myListTable->set_sortable_columns( array(
		'shub_column_time'    => array(
            'shub_column_time',
            1
        ),
	) );*/
    $myListTable->process_bulk_action(); // before we do the search on messages.

    $this_search = $search;
    if (isset($this_search['shub_status']) && $this_search['shub_status'] == -1) {
        unset($this_search['shub_status']);
    }
    SupportHub::getInstance()->load_all_messages($this_search, $order, $layout_type == 'continuous' ? 5 : false);
    // we store this data in the session so that continuous mode can access it and perform hte same search when loading more data.

    $all_messages = SupportHub::getInstance()->all_messages;
    $has_more = false;
    if($layout_type == 'continuous'){

        $message_ids = array();
        foreach($all_messages as $all_message){
            $message_ids[]=$all_message['shub_message_id'];
        }
        // this is used in class-support-hub.php to load the next batch of messages.
        // todo: pull this out of sessions into ajax variables so we can have two tabs open with different searches going at the same time.
        $_SESSION['_shub_search_rules'] = array($this_search, $order, $message_ids);
        // todo: ajax notification when currently listed messages are updated or new ones appear at the top of the list.

    }else{
        $screen = get_current_screen();
        // retrieve the "per_page" option
        $screen_option = $screen->get_option('per_page', 'option');
        // retrieve the value of the option stored for the current user
        $per_page = get_user_meta(get_current_user_id(), $screen_option, true);
        if ( empty ( $per_page) || $per_page < 1  || is_array($per_page) ) {
            // get the default value if none is set
            $per_page = $screen->get_option( 'per_page', 'default' );
        }
        if(!$per_page)$per_page=20;
        $myListTable->items_per_page = $per_page;
        $limit_pages = 2; // get about 10 pages of data to display in WordPress.
        if(count($all_messages) >= ($myListTable->get_pagenum() * $myListTable->items_per_page) + ($limit_pages * $myListTable->items_per_page)){
            $has_more = true; // a flag so we can show "more" in the pagination listing.
        }
    }


	// todo - hack in here some sort of cache so pagination works nicer ?
	//module_debug::log(array( 'title' => 'Finished social messages', 'data' => '', ));

    $myListTable->set_layout_type($layout_type);
    $myListTable->set_data($all_messages);
    $myListTable->prepare_items();
    $myListTable->pagination_has_more = $has_more;

    // for ajax it would be $myListTable->single_row($message_array);
    ?>
    <form method="post" id="shub_search_form">
        <div class="shub_header_box">


        <input type="hidden" name="paged" value="<?php echo isset($_REQUEST['paged']) ? (int)$_REQUEST['paged'] : 0; ?>" />
        <?php //$myListTable->search_box(__('Search','support_hub'), 'search_id'); ?>
        <p class="search-box shub_search_box">

            <span>
            <label for="simple_inbox-search-extension"><?php _e('Network:','support_hub');?></label>
            <select id="simple_inbox-search-extension" name="search[extension]">
                <option value=""><?php _e('All','support_hub');?></option>
                <?php foreach($this->message_managers as $message_manager_id => $message_manager) {
                    if ($message_manager->is_enabled()) { ?>
                        <option
                            value="<?php echo $message_manager_id; ?>"<?php echo isset($search['extension']) && $search['extension'] == $message_manager_id ? ' selected' : ''; ?>><?php echo $message_manager->friendly_name; ?></option>
                    <?php }
                }?>
            </select>
            </span>
            <span>
            <label for="simple_inbox-search-product"><?php _e('Product:','support_hub');?></label>
            <select id="simple_inbox-search-product" name="search[shub_product_id]">
                <option value=""><?php _e('All','support_hub');?></option>
                <?php foreach(SupportHub::getInstance()->get_products() as $product){ ?>
                    <option value="<?php echo $product['shub_product_id'];?>"<?php echo isset($search['shub_product_id']) && $search['shub_product_id'] == $product['shub_product_id'] ? ' selected' : '';?>><?php echo esc_attr( $product['product_name'] );?></option>
                <?php } ?>
            </select>
            </span>
            <span>
            <label for="simple_inbox-search-input"><?php _e('Content:','support_hub');?></label>
            <input type="search" id="simple_inbox-search-input" name="search[generic]" value="<?php echo isset($search['generic']) ? esc_attr($search['generic']) : '';?>">
            </span>
            <span>
            <label for="simple_inbox-search-status"><?php _e('Status:','support_hub');?></label>
            <select id="simple_inbox-search-status" name="search[shub_status]">
                <option value="-1"<?php echo isset($search['shub_status']) && $search['shub_status'] == -1 ? ' selected' : '';?>><?php _e('All','support_hub');?></option>
                <option value="<?php echo _shub_MESSAGE_STATUS_UNANSWERED;?>"<?php echo isset($search['shub_status']) && $search['shub_status'] == _shub_MESSAGE_STATUS_UNANSWERED ? ' selected' : '';?>><?php _e('Inbox','support_hub');?></option>
                <option value="<?php echo _shub_MESSAGE_STATUS_ANSWERED;?>"<?php echo isset($search['shub_status']) && $search['shub_status'] == _shub_MESSAGE_STATUS_ANSWERED ? ' selected' : '';?>><?php _e('Archived','support_hub');?></option>
                <option value="<?php echo _shub_MESSAGE_STATUS_HIDDEN;?>"<?php echo isset($search['shub_status']) && $search['shub_status'] == _shub_MESSAGE_STATUS_HIDDEN ? ' selected' : '';?>><?php _e('Hidden','support_hub');?></option>
            </select>
            </span>
            <span>

            <label for="orderquery"><?php _e('Sort:','support_hub');?></label>
            <select id="orderquery" name="orderquery">
                <option value="shub_column_time:asc"<?php echo isset($_REQUEST['orderquery']) && $_REQUEST['orderquery'] == 'shub_column_time:asc' ? ' selected' : '';?>><?php _e('Ascending','support_hub');?></option>
                <option value="shub_column_time:desc"<?php echo isset($_REQUEST['orderquery']) && $_REQUEST['orderquery'] == 'shub_column_time:desc' ? ' selected' : '';?>><?php _e('Descending','support_hub');?></option>
            </select>
            </span>
            <span>

            <label for="layout_type"><?php _e('View:','support_hub');?></label>
            <select id="layout_type" name="layout_type">
                <option value="continuous"<?php echo $layout_type=='continuous' ? 'selected' : '';?>><?php _e('Continuous','support_hub');?></option>
                <option value="inline"<?php echo $layout_type=='inline' ? 'selected' : '';?>><?php _e('Inline','support_hub');?></option>
                <option value="table"<?php echo $layout_type=='table' ? 'selected' : '';?>><?php _e('Table','support_hub');?></option>
            </select>
            </span>

            <input type="submit" name="" id="search-submit" class="button" value="<?php _e('Search','support_hub');?>"></p>
            <?php //$myListTable->display_tablenav( 'top' ); ?>
        </div>
        <?php
        $myListTable->display();
        ?>
    </form>


	<script type="text/javascript">
	    jQuery(function () {
		    ucm.social.init();
		    <?php foreach($this->message_managers as $message_id => $message_manager){
				$message_manager->init_js();
			} ?>
	    });
	</script>


</div>