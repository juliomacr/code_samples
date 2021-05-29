<?php
/**
 * @file
 * plum_monday.module
 *
 * This module contains features and functions for dealing with the Monday.com API
 * Other than testing or the intial build, this should only return data structures
 *
 */

define('MONDAY_API_URL', 'https://api.monday.com/v2/');
define('MONDAY_URL', 'https://plumdm.monday.com/');
define('PLUM_MONDAY__CACHE_ENABLE', '0');
define('PLUM_MONDAY__MODE', '0'); // 1 is production, 0 is dev

// Grab API key or fail
if (!empty($_SERVER['MONDAY_API_KEY'])) {
  define('MONDAY_API_KEY', $_SERVER['MONDAY_API_KEY']);
} else {
  drupal_set_message(t('Cannot read MONDAY_API_KEY from _SERVER. Check web server configuration.'), 'error');
  return;
}

define('COLOR_PASS', 'green'); //
define('COLOR_FAIL', 'red');
define('COLOR_WARNING', '#ff7700');

// Increase memory
ini_set('memory_limit', '256M');

// Set a text string we can use to choose the right database and so on
if (RUN_MODE == 'production') {
  define('MONDAY_MODE', 'live');
  drupal_set_message(t('Running in LIVE mode sites'), 'warning');
}
else {
  define('MONDAY_MODE', 'test');
}


// TODO: require administration perm to view

/**
 * Implements hook_menu().
 */
function plum_monday_menu() {
  $items = array();
  $items['plum_monday'] = array(
    'title' => 'Monday.com API Functions',
    'page callback' => 'plum_monday_main',
    'page arguments' => array('home'),
    'access arguments' => array('administer nodes'),
    'type' => MENU_NORMAL_ITEM,
  );

  $items['plum_monday/items/%/%'] = array(
    'title' => 'Monday.com Board Items',
    'page callback' => 'plum_monday_main',
    'page arguments' => array(1, 2, 3, 'html'),
    'access arguments' => array('administer nodes'),
    'type' => MENU_NORMAL_ITEM,
  );
  $items['plum_monday/item/%/%'] = array(
    'title' => 'Monday.com Item Detail',
    'page callback' => 'plum_monday_main',
    'page arguments' => array(1, 2, 3, 'html'),
    'access arguments' => array('administer nodes'),
    'type' => MENU_NORMAL_ITEM,
  );
  $items['plum_monday/board/%/%'] = array(
    'title' => 'Monday.com Boards',
    'page callback' => 'plum_monday_main',
    'page arguments' => array(1, 2, 3),
    'access arguments' => array('administer nodes'),
    'type' => MENU_NORMAL_ITEM,
  );
  $items['plum_monday/config/%/%'] = array(
    'title' => 'Monday.com Configuration Information',
    'page callback' => 'plum_monday_main',
    'page arguments' => array(1, 2, 3, 'html'),
    'access arguments' => array('administer nodes'),
    'type' => MENU_NORMAL_ITEM,
  );
  $items['plum_monday/log/%/%'] = array(
    'title' => 'Activity Log',
    'page callback' => 'plum_monday_log',
    'page arguments' => array(2, 3),
    'access arguments' => array('administer nodes'),
    'type' => MENU_NORMAL_ITEM,
  );
  $items['plum_monday/user/%'] = array(
    'title' => 'User Information',
    'page callback' => 'plum_monday_user',
    'page arguments' => array(2),
    'access arguments' => array('administer nodes'),
    'type' => MENU_CALLBACK,
  );
  $items['plum_monday/api/items/%/%'] = array(
    'access callback' => true,
    'page callback' => 'plum_monday_main',
    'page arguments' => array(2, 3, 4, 'json'),
    'delivery callback' => 'drupal_json_output',
  );
  $items['plum_monday/api/item/%/%'] = array(
    'access callback' => true,
    'page callback' => 'plum_monday_main',
    'page arguments' => array(2, 3, 4, 'json'),
    'delivery callback' => 'drupal_json_output',
  );
  $items['plum_monday/reference/%/%/%'] = array(
    'title' => 'Update Reference Items',
    'page callback' => 'plum_monday_references',
    'page arguments' => array(2, 3, 4),
    'file' => 'inc/plum_monday_references.inc.php',
    'access arguments' => array('administer nodes'),
    'type' => MENU_NORMAL_ITEM,
  );
  $items['plum_monday/create/webhook'] = array(
    'title' => 'Create Webhook for Monday.com',
    'page callback' => 'plum_monday_create_webhook',
    'access arguments' => array('administer nodes'),
    'type' => MENU_CALLBACK,
  );

  return $items;
}




/**
 * Implements hook_block_info().
 */
 /*
function plum_monday_block_info() {
  $blocks['plum_monday_site_info'] = array(
    'info' => t('Plum Monday Site Information Block'),
  );
  $blocks['plum_monday_client_info'] = array(
    'info' => t('Plum Monday Client Information Block'),
  );
  return $blocks;
}
*/

/**
 * Implements hook_block_view().
 *
 * Prepares the contents of the block.
 */
/*
function plum_monday_block_view($delta = '') {
  switch ($delta) {
    case "plum_monday_site_info":
      $block['content'] = plum_monday_site_info_block();
      break;
    case "plum_monday_client_info":
      $block['content'] = plum_monday_client_info_block();
      break;

  }
  return $block;
}
*/

function plum_monday_site_info_block() {
  $nid = arg(1);
  $node = node_load($nid);

  // Switch here by type
  $field_site_id_raw = field_get_items('node', $node, 'field_site_id');

  // Check to see if "Multiple" is selected, in which case, we aren't needed
  if ($field_site_id_raw[0]['safe_value'] == 1) {
    $output = 'Multiple Sites';
    return($output);
  }
  elseif ($field_site_id_raw[0]['safe_value'] == 2) {
    $output = 'Other Site';
    return($output);
  }

  $board_id = plum_monday_lookup('board_sites', 'id');
  $item = plum_monday_get_board_item($board_id, $field_site_id_raw[0]['safe_value']);
  if (empty($item)) {
    $output = 'Could not find site.';
    return ($output);
  }
  $name = $item['name'];
  $data['name'] = $name;
  $needed = ['status', 'date4', 'link'];
  foreach ($needed as $need) {
    $key = array_search($need, array_column($item['column_values'], 'id'));
    $processed = plum_monday_get_column_values($item['column_values'][$key], $item['board']['id']);
    $title = $processed['title'];
    $data[$title] = $processed['display'];
  }
  $output = iota_tools_stacker($data);
  return ($output);
}

// Given a site ID, lookup its information and return as array
function plum_monday_site_info($id) {

  $board_id = plum_monday_lookup('board_crm', 'id');
  $item = plum_monday_get_board_item($board_id, $id);
  if (empty($item)) {
    $output = 'Could not find site.';
    return ($output);
  }
  $name = $item['name'];
  $output['name'] = $item['name'];
  $needed = ['status', 'date4', 'link'];
  foreach ($needed as $need) {
    $key = array_search($need, array_column($item['column_values'], 'id'));
    $processed = plum_monday_get_column_values($item['column_values'][$key], $item['board']['id']);
    $title = strtolower($processed['title']);
    $output[$title] = $processed['display'];
  }
  return($output);
}


function plum_monday_client_info_block() {
  // switch here by type
  $nid = arg(1);
  $node = node_load($nid);

  // Switch here by type

  // Must look up Site to get Client ID
  $field_site_id_raw = field_get_items('node', $node, 'field_site_id');

  // Check to see if "Multiple" is selected, in which case, we aren't needed
  if ($field_site_id_raw[0]['safe_value'] == 1) {
    $output = 'Multiple Sites';
    return($output);
  }
  elseif ($field_site_id_raw[0]['safe_value'] == 2) {
    $output = 'Other Site';
    return($output);
  }

  $board_id = plum_monday_lookup('board_sites', 'id');
  $item = plum_monday_get_board_item($board_id, $field_site_id_raw[0]['safe_value']);
  if (empty($item)) {
    $output = 'Could not find Site (which we need to look up Client).';
    return ($output);
  }

  $key = array_search('link_to_item', array_column($item['column_values'], 'id'));
  $processed = plum_monday_get_column_values($item['column_values'][1]);

  $client_id = $processed['value'];

  $board_id = plum_monday_lookup('board_clients', 'id');
  $item = plum_monday_get_board_item($board_id, $client_id);
  $name = $item['name'];
  $data['name'] = $name;
  $needed = ['status', 'people', 'time_zone3'];
  foreach ($needed as $need) {
    $key = array_search($need, array_column($item['column_values'], 'id'));
    $processed = plum_monday_get_column_values($item['column_values'][$key], $item['board']['id']);
    $title = $processed['title'];
    $data[$title] = $processed['display'];
  }

  $output = iota_tools_stacker($data);
  return ($output);
}

// For old Monday.com functionality; use account_info until we migrate away from Monday
// Given a site ID, lookup the clients information and return as array
/*
function plum_monday_client_info($id) {
  $board_id = plum_monday_lookup('board_sites', 'id');

  $item = plum_monday_get_board_item($board_id, $id);
  if (empty($item)) {
    $output = 'Could not find Site (which we need to look up Client).';
    return;
  }

  $key = array_search('link_to_item', array_column($item['column_values'], 'id'));
  $processed = plum_monday_get_column_values($item['column_values'][1]);
  $client_id = $processed['value'];

  $board_id = plum_monday_lookup('board_clients', 'id');
  $item = plum_monday_get_board_item($board_id, $client_id);
  $name = $item['name'];
  $output['name'] = $name;
  $needed = ['status', 'people', 'time_zone3'];
  foreach ($needed as $need) {
    $key = array_search($need, array_column($item['column_values'], 'id'));
    $processed = plum_monday_get_column_values($item['column_values'][$key], $item['board']['id']);
    $title = $processed['title'];
    $output[$title] = $processed['display'];
  }
  return($output);
}
*/

// Use this function to get information about a client/site/contacts until migration is complete
// Given a site ID, lookup the clients information and return as array
/*
function plum_monday_account_info($id, $updates = NULL, $assets = NULL) {

  // Get board ID for CRM
  $board_id = plum_monday_lookup('board_crm', 'id');

  // Lookup item from Monday.com
  $item = plum_monday_get_board_item($board_id, $id);

  // Get value of fields
  foreach ($item as $id => $field) {
    if ($id != 'column_values') {
      $fields = ['name', 'id', 'state', 'updated_at', 'creator'];
      if (in_array($id, $fields)) {
        $raw = $field;
        $processed = [
          'title' => $id,
          'label' => $field,
          'value' => $raw,
          'display' => $raw,
        ];
        $title = ucwords(strtolower($id));
        $output[$id] = [
          'title' => $title,
          'raw' => $raw,
          'processed' => $processed,
        ];
      }
    } // end id != column values
  }

  // Get value of columns
  foreach ($item['column_values'] as $column) {
    $id = $column['id'];
    $processed = plum_monday_get_column_values($column, $item['board']['id']);
    $raw = $processed['value'];
    $title = ucwords(strtolower($processed['title']));
    $output[$id] = [
      'title' => $title,
      'raw' => $raw,
      'processed' => $processed,
    ];
  }

  return($output);
}
*/



/**
 * Raw display of queries for testing purposes
 * @param {string} op - which aspect of the system to show a test for
 * @param {string} id - a particular item id to display
 * @return {array} output
 */
function plum_monday_main($op, $board = NULL, $id = NULL, $format = 'html') {
  $description = '';

  if ($op == 'home') {
    $title = 'Home';
    $body = "<p>This module provides our access to Monday.com's API and is typically
    called by other modules and therefore not interactive.</p><p>Use the menu above to
    test various aspects of the system.</p>";

    // Test new function here

  }
  elseif ($op == 'board') {
    if (empty($board)) {
      $data = plum_monday_get_boards();
      $output = theme('boards_plum_monday', array('data' => $data));
    }
    else {
      $data = plum_monday_get_board($board);
      $output = theme('board_plum_monday', array('data' => $data));
    }
    return ($output);
  }
  elseif ($op == 'items') {
    // Show the items from one board
    $items = plum_monday_get_board_items($board);

    if ($format == 'html') {
      $title = $items['name'];
      $description = $items['name'];
      $body = theme('items_plum_monday', array('data' => $items));
    }
    else {
      return ($items);
    }
  }
  elseif ($op == 'item') {
    $item = plum_monday_get_board_item($board, $id);
    $board_info = plum_monday_lookup($board, 'reverse');
    if (!empty($board_info)) {
      $board_name = $board_info['data']['name'] . ': ';
    }
    else {
      $board_name = '';
    }
    if (!empty($item)) {
      $output = theme('item_plum_monday', array('data' => $item));
      drupal_set_title($board_name . $item['name']);
      return($output);
    }
    else {
      return 0;
    }
  }

  $data['title'] = $title;
  $data['description'] = $description;
  $data['body'] = $body;

  $output = theme('main_plum_monday', array('data' => $data));
  return ($output);

}


/**
 * Implements hook_theme().
 */
function plum_monday_theme($existing, $type, $theme, $path) {
  $module_path = drupal_get_path('module', 'plum_monday');
  return array(
    'main_plum_monday' => array(
      'template' => 'main_plum_monday',
      'variables' => array('data' => NULL),
      'path' => $module_path . '/tpl',
    ),
    'updates_plum_monday' => array(
      'template' => 'updates_plum_monday',
      'variables' => array('data' => NULL),
      'path' => $module_path . '/tpl',
    ),
    'update_plum_monday' => array(
      'template' => 'update_plum_monday',
      'variables' => array('data' => NULL),
      'path' => $module_path . '/tpl',
    ),
    'items_plum_monday' => array(
      'template' => 'items_plum_monday',
      'variables' => array('data' => NULL),
      'path' => $module_path . '/tpl',
    ),
    'boards_plum_monday' => array(
      'template' => 'boards_plum_monday',
      'variables' => array('data' => NULL),
      'path' => $module_path . '/tpl',
    ),
    'board_plum_monday' => array(
      'template' => 'board_plum_monday',
      'variables' => array('data' => NULL),
      'path' => $module_path . '/tpl',
    ),
    'log_items' => array(
      'template' => 'log_items.plum_monday',
      'variables' => array('data' => NULL),
      'path' => $module_path . '/tpl',
    ),
    'item_plum_monday' => array(
      'template' => 'item_plum_monday',
      'variables' => array('data' => NULL),
      'path' => $module_path . '/tpl',
    ),
    'column_plum_monday' => array(
      'template' => 'tabular_plum_monday',
      'variables' => array('data' => NULL),
      'path' => $module_path . '/tpl',
    ),
    'user_plum_monday' => array(
      'template' => 'user_plum_monday',
      'variables' => array('data' => NULL),
      'path' => $module_path . '/tpl',
    ),
  );
}



/**
 * Returns basic item information when we only know the ID, not the board_id
 * @return {array} output
 */
function plum_monday_get_item_by_id($id) {
  $key = MONDAY_API_KEY;
  $url = MONDAY_API_URL;

  // Die if we don't have what we need
  if (empty($id)) {
    $error_message = 'Failed: no item id provided: ' . $id;
    drupal_set_message(t(__FUNCTION__ . $error_message), 'error');
    watchdog(__FUNCTION__, $error_message, array(), WATCHDOG_ERROR);
    return;
  }

  // Create final CID
  $cid_final = 'get_item_by_id__' . $id;

  // If reset is a GET variable, flush the cache to provide latest records
  if (!empty($_GET['reset'])) {
    cache_clear_all($cid_final, 'cache');
    drupal_set_message(t(__FUNCTION__ . ': Cache reset for (' . $cid_final . ')'), 'status');
  }

  // Everything is cached
  if ($cache = cache_get($cid_final)) {
    if (!empty(DEBUG)) {
      drupal_set_message(t(__FUNCTION__ . ': Cache hit for (' . $cid_final . ')'), 'status');
    }
    $data = $cache->data;
  }
  else {
    if (!empty(DEBUG)) {
      drupal_set_message(t(__FUNCTION__ . ': Cache miss for (' . $cid_final . ')'), 'status');
    }

    $query = '
      {"query":"
        {
          items(ids: ' . $id . ') {
            id
            board {
              id
              name
            }
          }
        }
      "}';

    // Remove line breaks from query to avoid error
    $search = ["\n"];
    $replace = [''];
    $query = str_replace($search, $replace, $query);

    // Use the Service module to execute request
    $result = plum_service_graph_query($query, $url, $key);
    $data = json_decode($result->data, TRUE);
    if (empty($data['data']['items'])) {
      $message = __FUNCTION__ . ': lookup failed. Item might not exist anymore. (' . $id . ')';
      drupal_set_message(t($message), 'error');
      watchdog(__FUNCTION__, $error_message, array(), WATCHDOG_ERROR);
      return;
    }
    else {
      cache_set($cid_final, $data);
    }
  }

  $board_id = $data['data']['items'][0]['board']['id'];
  $item = plum_monday_get_board_item($board_id, $id);

  return ($item);
}


/**
 * Get the contents of a particular Monday.com board
 * @param {int} id - the board ID (look at the URL in Monday.com when on the board; it's the last number)
 * @param {int} limit - limit number of records returned
 * @param {array} fields - limit fields returned to those in the provide array  (NOT SUPPORTED YET)
 * @param {array} column_values - limit column_values returned to those in the provide array  (NOT SUPPORTED YET)
 * @param {int} start - record to start with with limit is specified the limit (NOT SUPPORTED YET)
 * @param {string} order - column to order results by (NOT SUPPORTED YET)
 * @param {string} sort - direction to sort results by column ASC or DESC (NOT SUPPORTED YET)
 * @return {array} output
 */
function plum_monday_get_board_items($board_id, $limit = NULL, $fields = NULL, $column_values = NULL, $force = NULL) {
  $key = MONDAY_API_KEY;
  $url = MONDAY_API_URL;

  // Set defaults
  $limit_query = '';
  $column_query = '';

  // Die if we don't have what we need
  if (empty($board_id)) {
    $error_message = 'Failed: no board id provided: ' . $board_id;
    drupal_set_message(t(__FUNCTION__ . $error_message), 'error');
    watchdog(__FUNCTION__, $error_message, array(), WATCHDOG_ERROR);
    return;
  }

  // CID: create along the way as we process what makes this query unique
  $cid[] = 'board_items__' . $board_id;

  // LIMIT
  if (empty($limit)) {
    $cid[] = '0';
    $limit_query = '';
  }
  else {
    $cid[] = $limit;
    $limit_query = 'limit: ' . $limit;
  }

  // COLUMN_VALUES we need
  if (!empty($column_values) && $column_values != 'none') {
    if (is_array($column_values)) {
      $column_names = implode(', ', $column_values);
      $cid[] = implode('_', $column_values);
      $columns = '(ids: [ ' . $column_names . ' ])';
    }
    else {
      $cid[] = $column_values;
      $columns = '(ids: [ ' . $column_values . ' ])';
    }
    // Create the query part that will show the column values
    $column_query = 'column_values' . $columns .' { id text title type value additional_info }';
  }

  // FIELDS we need
  if (!empty($fields)) {
    if (is_array($fields)) {
      $field_query = implode(' ', $fields);
      $cid[] = implode('_', $fields);
    }
    else {
      $field_query = $fields;
      $cid[] = $fields;
    }
  }
  else {
    $field_query = 'id name';
    $cid[] = 'id_name';
  }

  // Create final CID
  $cid_final = implode('__', $cid);

  // If reset is a GET variable, flush the cache to provide latest records
  if (!empty($_GET['reset']) || !empty($force)) {
    cache_clear_all($cid_final, 'cache');
    drupal_set_message(t(__FUNCTION__ . ': Cache reset for (' . $cid_final . ')'), 'status');
  }
  // Everything is cached
  if ($cache = cache_get($cid_final)) {
    if (!empty(DEBUG)) {
      drupal_set_message(t(__FUNCTION__ . ': Cache hit for (' . $cid_final . ')'), 'status');
    }
    return ($cache->data['data']['boards'][0]);
  }
  else {
    if (!empty(DEBUG)) {
      drupal_set_message(t(__FUNCTION__ . ': Cache miss for (' . $cid_final . ')'), 'status');
    }
  }

 // Build final Graph query to retrieve what we need
  $query = '
    {"query":"
      {
        boards(ids: ' . $board_id . ') {
          name
          description
          board_kind
          items (' . $limit_query . ') {
            '
            . $field_query .
            '
            '
            . $column_query .
            '
          }
        }
      }
    "}';

  // Remove line breaks from query to avoid error
  $search = ["\n"];
  $replace = [''];
  $query = str_replace($search, $replace, $query);

  // Use the Service module to execute request
  $result = plum_service_graph_query($query, $url, $key);

  if (!empty($result)) {
    // decode json data into array
    $data = json_decode($result->data, TRUE);
  }
  else {
    watchdog('plum_monday', 'Failed: empty board result for board id: ' . $board_id .  ' from Monday.com."', array(), WATCHDOG_ERROR);
  }


  cache_set($cid_final, $data);
  $output = $data['data']['boards'][0];

  // Add board id
  $output['board_id'] = $board_id;

  return ($output);
}


/**
 * Get one record from OLD CRM data
 * @param {int} item_id - id of item we want
 * @return {array}
 */
function plum_monday_get_board_item($board_id, $id, $options = NULL) {
  // TODO: make this part of the get board function
  $update_query = '';
  $assets_query = '';
  $key = MONDAY_API_KEY;
  $url = MONDAY_API_URL;

  // Check any options passed to us
  if (!empty($options)) {
    $force = (!empty($options['force'])) ? TRUE : FALSE;
    $assets = (!empty($options['assets'])) ? TRUE : FALSE;
    $updates = (!empty($options['updates'])) ? TRUE : FALSE;
  }

  // Die if we don't have what we need
  if (empty($board_id)) {
    $error_message = ' Failed: no board id provided: ' . $board_id;
    drupal_set_message(t(__FUNCTION__ . $error_message), 'error');
    watchdog(__FUNCTION__, $error_message, array(), WATCHDOG_ERROR);
    return;
  }

  // Create final CID
  $cid_final = 'board_item__' . $board_id . '__' . $id;

  // If reset is a GET variable, flush the cache to provide latest records
  if (!empty($_GET['reset']) || !empty($force)) {
    cache_clear_all($cid_final, 'cache');
    if (!empty(DEBUG)) {
      drupal_set_message(t(__FUNCTION__ . ': Cache reset for (' . $cid_final . ')'), 'status');
    }
  }

  // Everything is cached
  if ($cache = cache_get($cid_final)) {
    if (!empty(DEBUG)) {
      drupal_set_message(t(__FUNCTION__ . ': Cache hit for (' . $cid_final . ')'), 'status');
    }
    return ($cache->data);
  }
  else {
    if (!empty(DEBUG)) {
      drupal_set_message(t(__FUNCTION__ . ': Cache miss for (' . $cid_final . ')'), 'status');
    }
  }

  // Add to query if we need updates
  if (!empty($updates)) {
    $update_query = 'updates { id text_body created_at updated_at creator { name id }}';
  }

  // Add to query if we need assets
  if (!empty($assets)) {
    $assets_query = 'assets { id name public_url }';
  }

  // Query to get name, ID, column values, updates and assets for one client
  $query = '
    {"query":"
      {
        boards(ids: ' . $board_id . ') {
          items(ids: ' . $id . ') {
            id
            name
            state
            board { name id }
            creator { id name }
            group { id title color }
            updated_at
            ' . $assets_query . '
            column_values {
              id
              text
              title
              type
              value
              additional_info
            }
            ' . $update_query . '

          }
        }
      }
    "}';

  // Prepare query
  $search = ["\n"];
  $replace = [''];
  $query = str_replace($search, $replace, $query);

  // Use the Service module to execute request
  $result = plum_service_graph_query($query, $url, $key);

  // Get data from Monday
  if (empty($result)) {
    watchdog(__FUNCTION__, 'Failed: empty board result for board id: ' . $board_id .  ' and item id: ' . $id . ' from Monday.com."', array(), WATCHDOG_ERROR);
  } else {
    $data = json_decode($result->data, TRUE);
    if (array_key_exists('errors', $data)) {
      foreach ($data['errors'] as $error) {
        $message = $error['message'] . ' (' . __FUNCTION__ . ')';
        drupal_set_message(t($message), 'error');
        watchdog('plum_monday', 'Error: "%message"', array('%message' => $message), WATCHDOG_ERROR);
      } // end foreach
      $output = '';
    } else {
      $output = $data['data']['boards'][0]['items'][0];
      cache_set($cid_final, $output);
    } // if array_key_exists
  } // end empty result

  return ($output);
}


/**
 * Process the value and additional information of the column into usable elements
 * @param {array} column - RAW one column of data to be processed
 * @param {int} board_id - ID of board where this column's item lives
 * @return {array} output
 */
function plum_monday_get_column_values($column, $board_id = NULL) {
  // Form values are what is useful when editing an item in an Olly Form (value_form)
  // Unpack values

  if (empty($column)) {
    $message = __FUNCTION__ . ": No column value provided; skipping item. This should probably be investigated";
    watchdog('plum_monday', 'Error: "%message"', array('%message' => $message), WATCHDOG_ERROR);
    return;
  }

  $values = json_decode($column['value'], TRUE);
  $additional_infos = json_decode($column['additional_info'], TRUE);

  $style = []; // raw styles associated with element. these are used to create $display but can be used separately
  $display = ''; // the value we should display on a web page
  $value = '';
  $value_label = '';
  $value_form = '';
  $label = '';
  if (!empty($values['changed_at'])) {
    $changed = $values['changed_at'];
  }
  else {
    $changed = '';
  }
  $title = $column['title'];

  // Customize method to get data where the index in the values array != type
  // or if other special instructions are needed (like custom label, etc.)
  switch ($column['type']) {
    case 'color':
      if (!isset($values['index'])) {
        $value = 0;
        $style_color = '#000000';
        $style_bgcolor = '#c4c4c4';
      }
      else {
        $value = $values['index'];
        $style_color = '#000000';
        $style_bgcolor = $additional_infos['color'];
      }
      $style['background-color'] = $style_bgcolor;
      $style['color'] = $style_color;
      $value_label = $column['text'];
      $value_form = $value;
      $label = $column['text'];
      $display = '<span style="background-color: ' . $style['background-color'] . '; color: ' . $style['color'] . ';" class="jtag">' . str_replace(' ', '&nbsp;', $label) . '</span>';
      break;
    case 'dropdown':
      // TODO: check to see if ids are empty, otherwise we get an undefined var warning
      if (!empty($values['ids'])) {
        $value = $values['ids'];
      }
      else {
        $value = '';
      }
      $value_label = explode(', ', $column['text']);
      $value_form = $value;
      $label = $value_label;
      $display = $value_label;
      break;
    case 'boolean':
      $value = $values['checked'];
      if ($value == 'true') {
        $value_label = 'Yes';
        $value = 1;
        $value_form = $value;
      }
      else {
        $value_label = 'No';
        $value = 0;
        $value_form = $value;
      }
      $display = $value_label;
      break;
    case 'link':
      if (empty($column['value'])) {
        // nothing to do
      }
      else {
        if (!empty($values['text'])) {
          $label = $values['text'];
        }
        else {
          $label = $values['url'];
        }
        $value = $values['url'];
        $value_form = $values['url'];
        $value_label = $label;
        $display = '<a href="' . $value . '">' . $label . '</a>';
      } // end empty column['value']
      break;
    case 'pulse-id':
      $value = $column['text'];
      $value_label = $column['text'];
      $value_form = $value;
      $display = $value;
      break;
    case 'pulse-updated':
      $value = strtotime($column['text']);
      $label = $column['text'];
      $value_label = $column['text'];
      $value_form = $value;
      $display = $value;
      break;
    case 'text':
      $value = $values;
      $value_label = $column['text'];
      $value_form = $value;
      $display = $value;
      $label = $column['title'];
      break;
    case 'board-relation':
      if (empty($column['value'])) {
        // nothing to do
      }
      else {
        if (!empty(DEBUG)) {
          drupal_set_message('Monday.com board relation fields have been shut off since they do not contain the information to link them to the related board.', 'warning');
        }
        else {
          $value = 0;
          $label = 'Board relation fields are broken in Monday.com';
          $value_form = $value;
          $value_label = $label;
          $display = $label;
        }
      }
      break;
    case 'multiple-person':
      // Do not look up user's Monday.com profile data here, it's too expensive on long lists
      if (empty($column['value'])) {
        // nothing to do
        $value = '';
        $label = '';
        $display = '';
      }
      else {
        // Get labels; try to make text field into an array
        if (empty($column['text'])) {
          $label = 'Unknown';
          $value = 0;
          $display = '';
        }
        else {
          $value_count = count($values['personsAndTeams']);
          if ($value_count == 1) {
            if (empty($column['text'])) {
              $name = 'Unknown';
            }
            else {
              $name = $column['text'];
            }
            $label = $name;
            $value = $values['personsAndTeams'][0]['id'];
            $display = '<a href="/olly_site/manager/' . $value . '" title="User: ' . $name . '">' . $name . '</a>';
          }
          else {
            // Unset values to avoid array warnings
            unset($value, $label, $display);
            $labels = explode(', ', $column['text']);
            foreach ($values['personsAndTeams'] as $k => $person) {
              if (empty($labels[$k])) {
                $name = 'Unknown';
                $display[] = 'Unknown';
              }
              else {
                $name = $labels[$k];
                $display[] = '<a href="/olly_site/manager/' . $person['id'] . '" title="User: ' . $name . '">' . $name . '</a>';
              } // end empty labels
              $label[] = $name;
              $value[] = $person['id'];
            } // end foreach values
          } // if value count
        } // end empty column text
        $value_form = $value;
        $value_label = $label;
      } // end empty column value
      break;
    case 'long-text':
      $value = $column['text'];
      $value_label = $value;
      $value_form = $value;
      $label = $column['title'];
      break;
    case 'duration':
      $value = $column['text'];
      if (empty($column['value'])) {
        $value_label = '(not started)';
      }
      elseif ($values['running'] == TRUE) {
        $value_label = $value . ' (running)';
        $style['background-color'] = 'green';
      }
      else {
        $value_label = $value . ' (stopped)';
        $style['background-color'] = 'red';
      }
      $label = $value_label;
      $value_form = $value_label;
      $changed = $values['changed_at'];
      break;
    case 'lookup':
      $value = $column['text'];
      $value_label = $column['text'];
      $value_form = $column['text'];
      $label = $column['text'];
      $display = $value;
      break;
    case 'numeric':
      $value = $column['value'];
      $value_label = $column['title'];
      $value_form = $column['value'];
      $label = $column['title'];
      $display = $value;
      break;
    case 'file':
      $value = $column['text'];
      $value_label = $column['title'];
      $value_form = $column['text'];
      $label = $values['files'][0]['name'];
      $display = '<a href="' . $column['text'] . '" target="_blank" title="' . $value_label . '">' . $value_label . '</a>';
      break;
    case 'date':
      $value = $column['text'];
      $value_label = $column['text'];
      $value_form = $column['text'];
      $label = $column['text'];
      if (empty($value)) {
        $label = 'Unknown';
        $display = '<span class="unknown" title="Date is not defined">Unknown</span>';
      }
      else {
        $label = $column['text'];
        $display = $label;
      }
      break;
    case 'tag':
      if (!empty($values['tag_ids'])) {
        unset($value); // otherwise, error trying to create array item in string
        foreach ($values['tag_ids'] as $tag_key => $tag_value) {
          $value[] = $tag_value;
        }
        // Labels array is created from text
        $label = explode(', ', $column['text']);
      }
      else {
        $value = $column['text'];
      }
      $value_label = $column['text'];
      $value_form = $column['text'];
      $display = $column['text'];
      break;
    case 'phone':
      if (empty($column['text'])) {
        // nothing to do
      }
      else {
        $value = $column['text'];
        $label = $value;
        $value_label = $label;
        $value_form = $value;
      }
      break;
    case 'email':
      if (empty($column['value'])) {
        // nothing to do
      }
      else {
        $value = $values['email'];
        $label = $values['text'];
        $value_label = $label;
        $value_form = $values['email'];
        $display = $label;
      }
      break;
    case 'timezone':
      if (empty($column['value'])) {
        // nothing to do
      }
      else {
        $value = $column['text'];
        $label = $column['text'];
        $value_label = $label;
        $value_form = $value;
        $display = $label;
      }

      break;
    case 'rating':
      if (empty($column['value'])) {
        // nothing to do
      }
      else {
        $value = $column['text'];
        $label = $column['text'];
        $value_label = $label;
        $value_form = $value;
        $display = $label;
      }
      break;
    case 'location':
      if (empty($column['value'])) {
        // nothing to do
      }
      else {
        $value = $values['lat'] . '|' . $values['lng'];
        $label = $column['text'];
        $value_label = $label;
        $value_form = $values['placeId'];
        $display = $label;
      }
      break;
    default:
      if (empty($column['value'])) {
        // nothing to do
      }
      else {
        if (!empty(DEBUG)) {
          $message = 'Using default processing for this field: ' . print_r($column, TRUE);
          drupal_set_message($message, 'warning');
        }
        $value = $values[$column['type']];
        $value_label = $column['text'];
        $value_form = $value;
        $display = $value;
        $label = $value;
      }
      break;
  }

  // Add to output
  $output = [
    'id' => $column['id'],
    'title' => $title,
    'label' => $label,
    'value' => $value,
    'type' => $column['type'],
    'value_label' => $value_label,
    'value_form' => $value_form,
    'display' => $display,
    'style' => $style,
    'value_other' => $values,
    'changed_at' => $changed,
    'additional_info' => $additional_infos,
    'raw' => $column
  ];

if ($column['type'] == 'locasdfation') {
  echo "<pre>" . __FUNCTION__ . " - column: '"; print_r($column); echo "'</pre>";

  echo "<pre>" . __FUNCTION__ . " - values: '"; print_r($values); echo "'</pre>";
/*
  echo "<pre>" . __FUNCTION__ . " - type: '"; print_r($type); echo "'</pre>";
  echo "<pre>" . __FUNCTION__ . " - value: '"; print_r($value); echo "'</pre>";
  echo "<pre>" . __FUNCTION__ . " - value_label: '"; print_r($value_label); echo "'</pre>";
  echo "<pre>" . __FUNCTION__ . " - value_form: '"; print_r($value_form); echo "'</pre>";
  echo "<pre>" . __FUNCTION__ . " - changed_at: '"; print_r($changed_at); echo "'</pre>";
*/
  echo "<pre>" . __FUNCTION__ . " - output: '"; print_r($output); echo "'</pre>";
}



  return ($output);
}



/**
 * Get listing of all Monday.com boards in our account
 * @param {string} op = if "reference" just return information for reference boards
 * @return {array} output
 */
function plum_monday_get_boards() {
  $key = MONDAY_API_KEY;
  $url = MONDAY_API_URL;

  // Build final Graph query to retrieve what we need
  $query = '{"query":"{ boards(limit: 100) { id description name state board_folder_id board_kind owner { id } } } "}';

  // Lookup which boards are reference boards so we know we need to update them.
  $list = plum_monday_lookup('', 'list');
  foreach ($list as $id => $item) {
    if (!empty($item['data'])) {
      if (!empty($item['data']['type'])) {
        if (!empty($item['id'])) {
          $rkey = $item['data']['cid'];
          $reference_boards[$rkey] = $item['id'];
        } // end empty id
      } // end empty type
    } // end empty data
  } // end foreach list

  // Use the Service module to execute request
  $result = plum_service_graph_query($query, $url, $key);

  if (!empty($result)) {
    // decode json data into array
    $data = json_decode($result->data, TRUE);
  }
  else {
    watchdog('plum_monday', __FUNCTION__ . 'Failed no results from Monday.com."', array(), WATCHDOG_ERROR);
  }

  foreach ($data['data']['boards'] as $board) {
    if (in_array($board['id'], $reference_boards)) {
      $board['reference'] = 1;
    }
    else {
      $board['reference'] = 0;
    }
    $output['items'][] = $board;
  }
  return ($output);
}



/**
 * Get everything we can about a particular board
 * @param {int} board_id - the ID of the particular board
 * @return {array} output
 */
function plum_monday_get_board($board_id) {
  $key = MONDAY_API_KEY;
  $url = MONDAY_API_URL;

  // Die if we don't have what we need
  if (empty($board_id)) {
    $error_message = 'Failed: no board id provided: ' . $board_id;
    drupal_set_message(t(__FUNCTION__ . $error_message), 'error');
    watchdog(__FUNCTION__, $error_message, array(), WATCHDOG_ERROR);
    return;
  }

  // Create CID
  $cid = __FUNCTION__ . '__' . $board_id;

  // If reset is a GET variable, flush the cache to provide latest records
  if (!empty($_GET['reset'])) {
    cache_clear_all($cid, 'cache');
    drupal_set_message(t(__FUNCTION__ . ': Cache reset for (' . $cid . ')'), 'status');
  }

  // Try to pull from cache
  if ($cache = cache_get($cid)) {
    if (!empty(DEBUG)) {
      drupal_set_message(t(__FUNCTION__ . ': Cache hit for (' . $cid . ')'), 'status');
    }
    $output = $cache->data;
  }
  else {
    // Build final Graph query to retrieve what we need (there's no way to do this without activity logs it seems)
    $query = '{"query":"{ boards(ids: ' . $board_id . ') { name id description board_kind activity_logs { user_id id entity event data created_at } state columns { title settings_str type id } } } "}';

    // Use the Service module to execute request
    $result = plum_service_graph_query($query, $url, $key);

    if (!empty($result)) {
      // decode json data into array
      $data = json_decode($result->data, TRUE);
    }
    else {
      watchdog('plum_monday', 'Failed: no plum_monday_get_board result for board id: ' . $board_id .  ' from Monday.com."', array(), WATCHDOG_ERROR);
    }

    // Delete the activity logs since we won't need them
    unset($data['data']['boards'][0]['activity_logs']);

    $output = $data['data']['boards'][0];

    // Populate cache
    cache_set($cid, $output);

  } // end cache

  return ($output);

} // end plum_monday_get_board



/**
 * Look up Monday.com-specific things so ids and labels and other things that change
 * only need to be updated here.
 * @param {string} name - column name
 * @return {array} output - will include actual column name and valid results to test against
 */
function plum_monday_lookup($term = NULL, $op = 'forward') {
  $output = '';
  // List of library items; might be better as Taxonomy, but not for now.
  // TODO: make this conditional on board id, since they vary by board

  // Old CRM Fields
  $items['sub_status'] = [
    'id' => 'subscription_status__no_subscription__active__on_hold__canceled_',
    'data' => [
      'active' => [
        0,
        1,
      ],
      'cancelled' => [
        2,
      ],
      'on hold' => [
        0,
      ],
      'pending' => [
        3,
      ],
      'in queue' => [
        5,
      ],
    ],
  ];
  $items['site_status'] = ['id' => 'site_status7', 'data' => ['active' => [0]]];
  $items['url'] = ['id' => 'link8'];

  // New Sites Fields (for monitoring)
  $items['sites_sub_status'] = [
    'id' => 'mirror',
    'data' => [
      'active' => ['On Hold','Active'],
      'field_name' => 'subscription_status__no_subscription__active__on_hold__canceled_',
    ]
  ];
  $items['sites_site_status'] = [
    'id' => 'status',
    'data' => [
      'active' => ['Live']
    ]
  ];
  $items['sites_url'] = [
    'id' => 'link',
    'data' => [],
  ];

  // Boards
  // CRM source board
  // Temporarily changing this so we always use live board
  $items['board_crm'] = [
    'id' => [
      'test' => 488896787,
      'live' => 488896787,
    ],
    'data' => [
      'name' => 'CRM',
    ],
  ];

  // Clients board
  $items['board_clients'] = [
    'id' => [
      'test' => 709424436,
      'live' => '',
    ],
    'data' => [
      'name' => 'Clients',
    ],
  ];

  // Contacts board
  $items['board_contacts'] = [
    'id' => [
      'test' => 709437917,
      'live' => '',
    ],
    'data' => [
      'name' => 'Contacts',
    ],  ];

  // This is just for the second contact created for a client during migration.
  // TODO: once migration is complete, remove the following entry
  $items['board_contacts2'] = [
    'id' => [
      'test' => 709437917,
      'live' => '',
    ],
    'data' => [
      'name' => 'Contacts',
    ],  ];

  // Sites board
  $items['board_sites'] = [
    'id' => [
      'test' => 714713354,
      'live' => 714713354,
    ],
    'data' => [
      'op' => 'reference',
      'type' => 'board',
      'column_id' => '',
      'name' => 'Sites',
    ],
  ];

  // Tickets board
  $items['board_tickets'] = [
    'id' => [
      'test' => 679654707,
      'live' => '',
    ],
    'data' => [
      'name' => 'Tickets',
    ],
  ];

  // Employees Reference board
  $items['board_employees'] = [
    'id' => [
      'test' => 746730705,
      'live' => '',
    ],
    'data' => [
      'op' => 'reference',
      'type' => 'board',
      'column_id' => '',
      'name' => 'Employees',
    ],
  ];

  // Industries Reference board
  $items['board_industries'] = [
    'id' => [
      'test' => 716579296,
      'live' => '',
    ],
    'data' => [
      'op' => 'reference',
      'type' => 'board',
      'column_id' => 'legacy_id',
      'name' => 'Industries',
    ],
  ];

  // Ticket Items Reference board
  $items['board_tasks'] = [
    'id' => [
      'test' => 769131173,
      'live' => '',
    ],
    'data' => [
      'op' => 'reference',
      'type' => 'board',
      'column_id' => '',
      'name' => 'Tasks',
    ],
  ];

  // Ticket Priority Reference column
  $items['column_tickets_priority'] = [
    'id' => [
      'test' => 679654707,
      'live' => '',
    ],
    'data' => [
      'op' => 'reference',
      'type' => 'column',
      'column_id' => 'status0',
      'name' => 'Ticket Priority',
    ],
  ];

  // Ticket Status Reference column
  $items['column_tickets_status'] = [
    'id' => [
      'test' => 679654707,
      'live' => '',
    ],
    'data' => [
      'op' => 'reference',
      'type' => 'column',
      'column_id' => 'status',
      'name' => 'Ticket Status',
    ],
  ];

  // Departments board
  $items['board_departments'] = [
    'id' => [
      'test' => 781960817,
      'live' => '',
    ],
    'data' => [
      'name' => 'Departments',
    ],
  ];



  // Operations and what to return
  if ($op == 'forward') {
    if (array_key_exists($term, $items)) {
      $items[$term]['raw'] = $items[$term];

      // Make the id field just the id for the mode, don't return both test and live
      if (is_array($items[$term]['id'])) {
        $this_id = $items[$term]['id'][MONDAY_MODE];
      }
      else {
        $this_id = $items[$term]['id'];
      }
      $items[$term]['id'] = $this_id;
      // Add the reverse lookup tag
      $items[$term]['reverse'] = $term;
      $output = $items[$term];
    }
  }
  elseif ($op == 'column_id') {
    foreach ($items as $k => $v) {
      if (!empty($v['column_id']) && $v['column_id'] == $term) {
        $output = $items[$k];
        $output['term'] = $k;
      }
    }
  }
  elseif ($op == 'reverse') {
    foreach ($items as $k => $v) {
      if (is_array($v['id'])) {
        if ($v['id']['test'] == $term || $v['id']['live'] == $term) {
          $output = $items[$k];
        }
      }
      elseif ($v['id'] == $term) {
        $output = $items[$k];
      }
    }
  }
  elseif ($op == 'id') {
    // just return id
    $output = $items[$term]['id'][MONDAY_MODE];
  }
  elseif ($op == 'list') {
    $output = $items;
  }
  else {
    drupal_set_message(t('Unknown lookup: term = ' . $term . ' op = ' . $op), 'warning');
    return ($output);
  }

  return ($output);
}




/**
 * Create a row in a particular Monday board
 * @param {int} board_id - the ID number of the board
 * @param {string} name - value for the name column of the new item
 * @param {string} name - values for the columns of the new item
 * @return {array} output - including a copy of the response if there's an error
 */
function plum_monday_create_item($board_id, $name, $column_values) {
  $key = MONDAY_API_KEY;
  $url = MONDAY_API_URL;
  $new_id = '';
  $final_query = '';
  $messages = [];
  $data = [];

  if (empty($column_values)) {
    $messages[] = 'No column_values';
    $verdict = 2;
  }
  elseif (empty($board_id)) {
    $board_id = ''; // avoid warning
    $messages[] = 'No board_id';
    $verdict = 2;
  }
  elseif (empty($name)) {
    $messages[] = 'No column_values';
    $verdict = 2;
  }
  else {
    $query = 'mutation ($myItemName: String!, $columnVals: JSON!) { create_item (board_id:' . $board_id . ', item_name:$myItemName, column_values:$columnVals) { id } }';
    $vars = ['myItemName' => $name, 'columnVals' => json_encode($column_values, JSON_NUMERIC_CHECK)];
    $final_query = json_encode(['query' => $query, 'variables' => $vars]);

//    echo "<pre>" . __FUNCTION__ . " - final_query: '"; print_r($final_query); echo "'</pre>";
  //  exit();

/*
  if ($board_id == 714713354) {
    echo "<pre>" . __FUNCTION__ . " - query: '"; print_r($query); echo "'</pre>";
    echo "<pre>" . __FUNCTION__ . " - vars: '"; print_r($vars); echo "'</pre>";
    echo "<pre>" . __FUNCTION__ . " - final_query: '"; print_r($final_query); echo "'</pre>";
    echo "<pre>" . __FUNCTION__ . " - column_values: '"; print_r($column_values); echo "'</pre>";
    exit();
  }
*/

    // Use the Service module to execute request
    $result = plum_service_graph_query($final_query, $url, $key);
    if (!empty($result)) {
      // decode json data into array
      $data = json_decode($result->data, TRUE);
      $new_id = $data['data']['create_item']['id'];
      if (!empty($new_id)) {
        $verdict = 1;
      }
      else {
        $messages[] = 'No ID in result';
        $verdict = 2;
      }
    }
    else {
      $verdict = 2;
      $messages[] = 'Empty result from graph query';
    }
  }

  // We have a result, CHECK FOR ERRORS!
  //    [data] => {"error_code":"ColumnValueException","status_code":200,"error_message":"Link to item column value structure invalid","error_data":{}}
/*
// Useful for troubleshooting the phone formatting bug
if ($board_id == 709437917) {
  echo "<pre>" . __FUNCTION__ . " - result: '"; print_r($result); echo "'</pre>";
  echo "<pre>" . __FUNCTION__ . " - data: '"; print_r($data); echo "'</pre>";
  exit();
}
*/

  $output = [
    'verdict' => $verdict,
    'id' => $new_id,
    'board_id' => $board_id,
    'messages' => $messages,
    'query' => $final_query,
    'data' => $data,
  ];

  return ($output);
} // end create_item


/**
 * Delete one item from Monday.com
 * @param {int} id - the ID of the item to be deleted
 * @return {int} id - the ID of the item deleted
 */
function plum_monday_delete_item($id) {
  $key = MONDAY_API_KEY;
  $url = MONDAY_API_URL;
  $messages = [];

  // Build final Graph query to retrieve what we need
  $query = 'mutation { delete_item (item_id:' . $id . ') { id } }';
  $final_query = json_encode(['query' => $query]);

  // Use the Service module to execute request
  $result = plum_service_graph_query($final_query, $url, $key);

  if (!empty($result)) {
    // decode json data into array
    $data = json_decode($result->data, TRUE);
    $deleted_id = $data['data']['delete_item']['id'];
    if (!empty($deleted_id)) {
      $verdict = 1;
      $messages[] = 'Item deleted: ' . $deleted_id;
    }
    else {
      $verdict = 2;
      $messages[] = 'Failed to delete item: ' . print_r($data, TRUE);
    }
  }
  else {
    $verdict = 2;
    $messages[] = 'Failed to delete item: empty result for ' . $id;
  }

  $output = [
    'verdict' => $verdict,
    'messages' => $messages,
  ];
  return($output);
} // end delete_item


/**
 * Update one item from Monday.com
 * @param {int} board_id = ID of the board in which the item lives
 * @param {int} id - the ID of the item
 * @param {string} name - the name of the item
 * @param {array} column_values - keyed array of column values to change
 * @return {int} id - the ID of the item deleted
 */
function plum_monday_update_item($board_id, $item_id, $name, $column_values) {
  $key = MONDAY_API_KEY;
  $url = MONDAY_API_URL;
  $messages = [];

  // Build final Graph query to mutate what we need
  $query = 'mutation ($columnVals: JSON!) { change_multiple_column_values (board_id: ' . $board_id . ', item_id: ' . $item_id . ', column_values:$columnVals) { id } }';
  $vars = ['columnVals' => json_encode($column_values, JSON_NUMERIC_CHECK)];
  $final_query = json_encode(['query' => $query, 'variables' => $vars]);

  // Use the Service module to execute request
  $result = plum_service_graph_query($final_query, $url, $key);

  if (!empty($result)) {
    // decode json data into array
    $data = json_decode($result->data, TRUE);
    $id = $data['data']['change_multiple_column_values']['id'];
    if (!empty($id)) {
      $verdict = 1;
      $messages[] = 'Item updated: ' . $id;
    }
    else {
      $verdict = 2;
      $messages[] = 'Failed to update item: ' . print_r($data, TRUE);
    }
  }
  else {
    $verdict = 2;
    $messages[] = 'Failed to update item: empty result for ' . $item_id;
  }

  $output = [
    'verdict' => $verdict,
    'id' => $id,
    'board_id' => $board_id,
    'messages' => $messages,
    'query' => $final_query,
    'data' => $data,
  ];

  return($output);
} // end delete_item




// TODO: remove the validate function from plum_migrate and use this function, as is
/**
 * Validate the value presented based on the type of field; Return a verdict 1 or 2 and/or messages
 * @param {array} processed - the processed raw value from CRM
 * @return {array} output verdict & message 1 = pass, 2 = fail
 */
function plum_monday_validate($value, $type) {
  $messages = [];

  // Validate $value based on $type
  if ($type == 'pulse-id') {
    if (is_numeric($value)) {
      $verdict = 1;
    }
    else {
      $verdict = 2;
      $messages[] = 'Value was not a number';
    }
  }
  elseif ($type == 'text') {
    if (is_string($value)) {
      $verdict = 1;
    }
    else {
      $verdict = 2;
      $messages[] = 'Value was not a string';
    }
  }
  elseif ($type == 'color') {
    // Value is the label for the field, as that's how we submit it
    if (is_string($value)) {
      $verdict = 1;
    }
    else {
      $verdict = 2;
      $messages[] = 'Value was not a string';
    }
  }
  elseif ($type == 'link') {
    if (filter_var($value, FILTER_VALIDATE_URL)) {
      $verdict = 1;
    }
    else {
      $verdict = 2;
      $messages[] = "Value was not a valid URL ($value)" . print_r($value, TRUE);
    }
  }
  elseif ($type == 'email') {
    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
      $verdict = 1;
    }
    else {
      $verdict = 2;
      $messages[] = "Value was not a valid email address ($value)";
    }
  }
  elseif ($type == 'numeric') {
    if (is_numeric($value)) {
    	$verdict = 1;
    }
    else {
      $verdict = 2;
      $messages[] = "Value was not a number.";
    }
  }
  elseif ($type == 'dropdown' || $type == 'multiple-person') {
    // Make sure value is array
    if (is_array($value)) {
      $verdict = 1;
    }
    else {
      $verdict = 2;
      $messages[] = "Value was not an array ($value)";
    }
  }
  elseif ($type == 'phone') {
    $length = strlen($value);
    if (is_numeric($value)) {
      if ($length <= 16) {
      	$verdict = 1;
      }
      else {
        $verdict = 2;
        $messages[] = "Value exceeds Monday.com limit of 16 digits ($value)";
      }
    }
    else {
      $verdict = 2;
     	$messages[] = "Value is not a number.";
    }
  }

  $output = [
    'verdict' => $verdict,
    'messages' => $messages,
  ];

  return($output);
}


// TODO: remove the format function from plum_migrate and update it to pass the new
// information, like the fact that you won't have processed

/**
 * Create the final value to be migrated based on type
 * @param {array} processed - the processed raw value from CRM
 * @return {int} verdict - 1 for valid, 0 otherwise
 */
function plum_monday_format($value, $type, $label = NULL) {
  echo "<pre>" . __FUNCTION__ . " - value: '"; print_r($value); echo "'</pre>";
  echo "<pre>" . __FUNCTION__ . " - type: '"; print_r($type); echo "'</pre>";
  echo "<pre>" . __FUNCTION__ . " - text: '"; print_r($text); echo "'</pre>";
  exit();

  $messages = [];
  $verdict = 1; // right now everything is valid

  if ($type == 'date') {
    $final = ['date' => $value, 'time' => '00:00:00'];
  }
  elseif ($type == 'email') {
    if (empty($label)) {
      $text = $value;
    }
    $final = ['email' => $value, 'text' => $text];
  }
  elseif ($type == 'link') {
    if (empty($label)) {
      $text = $value;
    }
    $final = ['url' => $value, 'text' => $text];
  }
  elseif ($type == 'phone') {
    // TODO: US should not be hard-coded
    $text = 'US';
    $final = ['phone' => (int)$value, 'countryShortName' => $text];
  }
  elseif ($type == 'color') {
    // $value contains IDs, text contains labels
    if (!empty($value)) {
      $final = ['index' => $value];
    }
    elseif (!empty($label)) {
      $final = ['label' => $label];
    }
  }
  elseif ($type == 'dropdown') {
    // $value contains IDs, $text contains labels
    // TODO: this only supports one value
    if (!empty($value)) {
      $final = ['ids' => $value];
    }
    elseif (!empty($label)) {
      $final = ['labels' => $label];
    }
  }
  elseif ($type == 'multiple-person') {
    // TODO: supports only people, not groups
    foreach ($values as $person) {
      $these_people[] = ['id' => $person, 'kind' => 'person'];
    }
    $final = ['personsAndTeams' => $these_people];
  }
  else {
    // for default types, just return value
    // default types include pulse-id, text
    $final = $value;
  }

  // Output this to see what the JSON encoded answer is
  // $test_value = json_encode($final);
  // echo "<pre>" . __FUNCTION__ . " - test_value: '"; print_r($test_value); echo "'</pre>";

  $output = [
    'value' => $final,
    'verdict' => $verdict,
    'messages' => $messages,
  ];

  return ($output);
}


/**
 * Manages activity log requets
 * @param {int} board_id = id of board with activities we want to see
 * @param {int} id = item id
 * @return {array} output
 */
function plum_monday_log($board_id, $id = NULL) {
  // NOT IMPLEMENTED YET
  // plum_monday_log_get_items
  // plum_monday_log_get_item

  // Die if we don't have what we need
  if (empty($board_id)) {
    $error_message = 'Failed: no board id provided: ' . $board_id;
    drupal_set_message(t(__FUNCTION__ . $error_message), 'error');
    watchdog(__FUNCTION__, $error_message, array(), WATCHDOG_ERROR);
    return;
  }

  if (!empty($id)) {
    // log item
    $item = plum_monday_get_log_item($board_id, $id);
    $output = theme('log_item', array('data' => $item));
  }
  else {
    // log items
    $board = plum_monday_get_board($board_id);
    $data['name'] = $board['name'];
    $data['board_id'] = $board['id'];
    $data['items'] = $board['activity_logs'];

    $output = theme('log_items', array('data' => $data));
  }
  return ($output);
}

function plum_monday_get_log_items($board_id) {
  // NOT IMPLEMENTED
  $output = $board['activity_logs'];
  return ($output);
}

/*
function plum_monday_get_log_item($board_id, $id) {
  // NOT IMPLEMENTED

  // Die if we don't have what we need
  if (empty($id)) {
    $error_message = 'Failed: no board id provided: ' . $board_id;
    drupal_set_message(t(__FUNCTION__ . $error_message), 'error');
    watchdog(__FUNCTION__, $error_message, array(), WATCHDOG_ERROR);
    return;
  }

  echo "<pre>" . __FUNCTION__ . " - board_id: '"; print_r($board_id); echo "'</pre>";
  echo "<pre>" . __FUNCTION__ . " - id: '"; print_r($id); echo "'</pre>";
  exit();

}
*/


/**
 * Include views .inc files as necessary.
 */
function plum_monday_include($file) {
  static $plum_monday_path;
  if (!isset($plum_monday_path)) {
    $plum_monday_path = DRUPAL_ROOT . '/' . drupal_get_path('module', 'plum_monday');
  }
  include_once $plum_monday_path . '/inc/' . $file . '.inc.php';
}



/**
 * Creates a standard Monday notification
 * @param {int} user_id = id of person to which the notification is addressed
 * @param {int} target_id = id of board where the item lives
 * @param {string} text = text of the body of the notification
 * @param {string} target_type = either Post (or update??) or Project for board-related stuff
 * @return {array} output
 */
function plum_monday_create_notification($user_id, $target_id, $text, $target_type = NULL) {
  $key = MONDAY_API_KEY;
  $url = MONDAY_API_URL;
  $messages = [];

  if (empty($target_type)) {
    $target_type = 'Project';
  }

  $query = 'mutation { create_notification (user_id:' . $user_id . ', target_id: ' . $target_id . ', text: "' . $text . '", target_type: ' . $target_type . ') { text } }';
  $final_query = json_encode(['query' => $query], JSON_NUMERIC_CHECK);

  // Use the Service module to execute request
  $result = plum_service_graph_query($final_query, $url, $key);

  if (!empty($result)) {
    // decode json data into array
    $data = json_decode($result->data, TRUE);

    if ($result->status_message == "OK") {
      $verdict = 1;
    }
    else {
      $messages[] = $result->status_message;
      $verdict = 2;
    }
  }
  else {
    $verdict = 2;
    $messages[] = 'Empty result from graph query';
  }

  $output = [
    'verdict' => $verdict,
    'user_id' => $user_id,
    'messages' => $messages,
    'query' => $final_query,
    'result' => $result,
  ];

  return ($output);
}




/**
 * Adds an update to an existing item
 * @param {int} item_id = id of the item
 * @param {string} body = text of the update
 * @return {array} output
 */
function plum_monday_create_update($item_id, $body) {
  $key = MONDAY_API_KEY;
  $url = MONDAY_API_URL;
  $messages = [];

  $query = 'mutation { create_update (item_id:' . $item_id . ', body: "' . $body . '") { id } }';
  $final_query = json_encode(['query' => $query], JSON_NUMERIC_CHECK,JSON_UNESCAPED_SLASHES);

  // Use the Service module to execute request
  $result = plum_service_graph_query($final_query, $url, $key);

  if (!empty($result)) {
    // decode json data into array
    $data = json_decode($result->data, TRUE);

    if ($result->status_message == "OK") {
      $verdict = 1;
    }
    else {
      $messages[] = $result->status_message;
      $verdict = 2;
    }
  }
  else {
    $verdict = 2;
    $messages[] = 'Empty result from graph query';
  }

  $output = [
    'verdict' => $verdict,
    'item_id' => $item_id,
    'body' => $body,
    'messages' => $messages,
    'query' => $final_query,
    'result' => $result,
  ];

  return ($output);
}

/**
 * Show a user page
 * @param {int} id = id of the user
 * @return {array} output
 */
function plum_monday_user($id) {
  // Die if we don't have what we need
  if (empty($id)) {
    $error_message = 'Failed: no user id provided: ' . $id;
    drupal_set_message(t(__FUNCTION__ . $error_message), 'error');
    watchdog(__FUNCTION__, $error_message, array(), WATCHDOG_ERROR);
    return;
  }

  // Fetch user data
  $data = plum_monday_get_user($id);
  if (empty($data)) {
    $error_message = 'User lookup failed. Check log.';
    drupal_set_message(t(__FUNCTION__ . $error_message), 'error');
    watchdog(__FUNCTION__, $error_message . ' ' . print_r($data, TRUE), array(), WATCHDOG_ERROR);
    return;
  }

  drupal_set_title($data['name']);

  $output = theme('user_plum_monday', array('data' => $data));
  return ($output);
}


/**
 * Get the information for one User in Monday.com
 * @param {int} id = id of the user
 * @return {array} output
 */
function plum_monday_get_user($id) {
  $key = MONDAY_API_KEY;
  $url = MONDAY_API_URL;

  // Die if we don't have what we need
  if (empty($id)) {
    $error_message = 'Failed: no user id provided: ' . $id;
    drupal_set_message(t(__FUNCTION__ . $error_message), 'error');
    watchdog(__FUNCTION__, $error_message, array(), WATCHDOG_ERROR);
    return;
  }

  // Create final CID
  $cid_final = 'plum_monday_get_user__' . $id;

  // If reset is a GET variable, flush the cache to provide latest records
  if (!empty($_GET['reset'])) {
    cache_clear_all($cid_final, 'cache');
    if (RUN_MODE == 'local') {
      drupal_set_message(t(__FUNCTION__ . ': Cache reset for (' . $cid_final . ')'), 'status');
    }
  }

  // Everything is cached
  if ($cache = cache_get($cid_final)) {
    if (!empty($cache->data['data']['users'][0])) {
      if (!empty(DEBUG)) {
        drupal_set_message(t(__FUNCTION__ . ': Cache hit for (' . $cid_final . ')'), 'status');
      }
      $output = $cache->data['data']['users'][0];
      return ($output);
    }
    else {
      $error_message = "Cache entry exists for $cid_final but it has no value for user. Will not try again. Clear cache if the user problem is fixed.";
      watchdog(__FUNCTION__, $error_message, array(), WATCHDOG_WARNING);
      return;
    }
  }
  else {
    if (!empty(DEBUG)) {
      drupal_set_message(t(__FUNCTION__ . ': Cache miss for (' . $cid_final . ')'), 'status');
    }
  }


  // Query to get name, ID, column values, updates and assets for one client
  $query = '
    {"query":"
      {
        users(ids: ' . $id . ') {
          id
          created_at
          email
          title
          name
          enabled
          location
          mobile_phone
          phone
          time_zone_identifier
          url
        }
      }
    "}';

  $search = ["\n"];
  $replace = [''];
  $query = str_replace($search, $replace, $query);

  // Use the Service module to execute request
  $result = plum_service_graph_query($query, $url, $key);

  // Get data from Monday
  if (!empty($result)) {
    // decode json data into array
    $data = json_decode($result->data, TRUE);
    if (empty($data['data']['users']) && !empty(DEBUG)) {
      $message = "Empty result returned looking up Monday.com user $id.";
      watchdog(__FUNCTION__, $message, array(), WATCHDOG_WARNING);
      return;
    }
  }
  else {
    watchdog(__FUNCTION__, 'Failed: empty result for user id: ' . $board_id, array(), WATCHDOG_ERROR);
  }

  // Save to cache
  cache_set($cid_final, $data);

  $output = $data['data']['users'][0];
  return ($output);
}




/**
 * Get the information for ALL users
 * @param {int} id = id of the user
 * @return {array} output
 */
function plum_monday_get_users($kind = 'all') {
  $key = MONDAY_API_KEY;
  $url = MONDAY_API_URL;

  // Create final CID
  $cid_final = 'get_users__' . $kind;

  // If reset is a GET variable, flush the cache to provide latest records
  if (!empty($_GET['reset'])) {
    cache_clear_all($cid_final, 'cache');
    if (!empty(DEBUG)) {
      drupal_set_message(t(__FUNCTION__ . ': Cache reset for (' . $cid_final . ')'), 'status');
    }
  }

  // Everything is cached
  if ($cache = cache_get($cid_final)) {
    if (!empty(DEBUG)) {
      drupal_set_message(t(__FUNCTION__ . ': Cache hit for (' . $cid_final . ')'), 'status');
    }
    $output = $cache->data['data']['users'];
    return ($output);
  }
  else {
    if (!empty(DEBUG)) {
      drupal_set_message(t(__FUNCTION__ . ': Cache miss for (' . $cid_final . ')'), 'status');
    }
  }

  // Query to get a list of all IDs for users (kinds: all, non_guests / guests / non_pending)
  $query = '
    {"query":"
      {
        users(kind: ' . $kind . ') {
          name
          title
          email
          id
        }
      }
    "}';

  $search = ["\n"];
  $replace = [''];
  $query = str_replace($search, $replace, $query);

  // Use the Service module to execute request
  $result = plum_service_graph_query($query, $url, $key);

  // Get data from Monday
  if (!empty($result)) {
    // decode json data into array
    $data = json_decode($result->data, TRUE);
  }
  else {
    watchdog(__FUNCTION__, 'Failed: empty result for users kind: ' . $kind, array(), WATCHDOG_ERROR);
  }

  // Save to cache
  cache_set($cid_final, $data);
  $output = $data['data']['users'];
  return ($output);
}




/**
 * Create a webhook from Monday.com; this is all canned
 */
function plum_monday_create_webhook() {
  $key = MONDAY_API_KEY;
  $url = MONDAY_API_URL;

  $url = 'https://ollywood.ollyolly.com/monday_webhook/create/item';
  $board_id = 764669391; // test
  $event = 'create_item';

  $query = 'mutation { create_webhook (board_id: ' . $board_id . ', url: "' . $url . '", event: "' . $event . '") { id board_id } }';
  $final_query = json_encode(['query' => $query], JSON_NUMERIC_CHECK);

  // Use the Service module to execute request
  $result = plum_service_graph_query($final_query, $url, $key);

  $data = json_decode(file_get_contents('php://input'), TRUE);

  $message = [
  "challenge" => $data["challenge"]
  ];

//file_put_contents("response1.log", print_r(json_encode($data), true));

  $output = json_encode($message);

  watchdog(__FUNCTION__, 'Result result %result / message %message / data %data', array(
  '%result' => print_r($result, TRUE),
  '%message' => print_r($message, TRUE),
  '%data' => print_r($result, TRUE)
  ), WATCHDOG_DEBUG);

  return ($output);

/*
mutation {
create_webhook ( board_id: 12593, url: "https://www.webhooks.my-webhook/test", event: create_item) {
id
board_id
}
}
*/

}
