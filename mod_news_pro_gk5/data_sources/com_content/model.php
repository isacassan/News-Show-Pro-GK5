<?php

/**
* This Model is responsible for getting data from the com_content data source
* @package News Show Pro GK5
* @Copyright (C) 2009-2013 Gavick.com
* @ All rights reserved
* @ Joomla! is Free Software
* @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
* @version $Revision: GK5 1.3.3 $
**/

// access restriction
defined('_JEXEC') or die('Restricted access');


class NSP_GK5_com_content_Model {
	// Method to get sources of articles
	static function getSources($config) {
		if($config['data_source'] != 'com_content_all') {
			//
			$db = JFactory::getDBO();
			// if source type is section / sections
			$source = false;
			$where1 = '';
			$where2 = '';
			$tag_join= '';
			$tag_where = '';
			
			//
			if($config['data_source'] == 'com_content_tags' ) {
				$tag_join = ' LEFT JOIN #__contentitem_tag_map AS tag_map ON content.id = tag_map.content_item_id LEFT JOIN #__tags AS tag ON tag.id = tag_map.tag_id ';
				$config['com_content_tags'] = implode(',',$config['com_content_tags']);
				$tag_where = ' AND tag_map.type_alias = ' . $db->quote('com_content.article');
			}
			//
			if($config['data_source'] == 'com_content_categories'){
				$source = $config['com_content_categories'];
				$where1 = ' c.id = ';
				$where2 = ' OR c.id = ';
			} else if($config['data_source'] == 'com_content_tags'){
				$source = strpos($config['com_content_tags'],',') !== false ? explode(',', $config['com_content_tags']) : $config['com_content_tags'];
				$where1 = ' tag.id = ';
				$where2 = ' OR tag.id = ';	
			} else {
				$source = strpos($config['com_content_articles'],',') !== false ? explode(',', $config['com_content_articles']) : $config['com_content_articles'];
				$where1 = ' content.id = ';
				$where2 = ' OR content.id = ';	
			} 
			//	
			$where = ''; // initialize WHERE condition
			// generating WHERE condition
			for($i = 0;$i < count($source);$i++){
				if(count($source) == 1) $where .= (is_array($source)) ? $where1.$source[0] : $where1.$source;
				else $where .= ($i == 0) ? $where1.$source[$i] : $where2.$source[$i];		
			}
			//
			$query_name = '
				SELECT 
					c.id AS CID
				FROM 
					#__categories AS c
				LEFT JOIN 
					#__content AS content 
					ON 
					c.id = content.catid 
					'.$tag_join.'	
				WHERE 
					( '.$where.' ) 
					AND 
					c.extension = '.$db->quote('com_content').
					$tag_where.
					' AND 
					c.published = 1
		        ';	
		        
			// Executing SQL Query
			$db->setQuery($query_name);
			// check if some categories was detected
			if($categories = $db->loadObjectList()) {
				$categories_array = array();
				// iterate through all items 
				foreach($categories as $item) {
					if(!in_array($item->CID, $categories_array)) {
						array_push($categories_array, $item->CID);
					}
				}
				//
				return $categories_array;
			} else {
				// when no categories detected
				return null;
			}
		} else {
			return null;
		}
	}
	// Method to get articles in standard mode 
	static function getArticles($categories, $config, $amount) {	
		//
		$sql_where = '';
		$tag_join = '';
		$tag_where = '';
		$db = JFactory::getDBO();
		//
		if($categories) {		
			// getting categories ItemIDs
			for($j = 0; $j < count($categories); $j++) {
				$sql_where .= ($j != 0) ? ' OR content.catid = ' . $categories[$j] : ' content.catid = '. $categories[$j];
			}	
		}
		// Overwrite SQL query when user set IDs manually
		if($config['data_source'] == 'com_content_articles' && $config['com_content_articles'] != ''){
			// initializing variables
			$sql_where = '';
			$ids = explode(',', $config['com_content_articles']);
			//
			for($i = 0; $i < count($ids); $i++ ){	
				// linking string with content IDs
				$sql_where .= ($i != 0) ? ' OR content.id = '.$ids[$i] : ' content.id = '.$ids[$i];
			}
		}
		// Overwrite SQL query when user set tags
		if($config['data_source'] == 'com_content_tags' && $config['com_content_tags'] != ''){
			// initializing variables
			$sql_where = '';
			$config['com_content_tags'] = implode(',',$config['com_content_tags']);
			$tag_join = ' LEFT JOIN #__contentitem_tag_map AS tag_map ON content.id = tag_map.content_item_id LEFT JOIN #__tags AS tag ON tag.id = tag_map.tag_id ';
			$tag_where = ' AND tag_map.type_alias = ' . $db->quote('com_content.article');
			//
			$ids = explode(',', $config['com_content_tags']);
			//
			for($i = 0; $i < count($ids); $i++ ){	
				// linking string with content IDs
				$sql_where .= ($i != 0) ? ' OR tag.id = '.$ids[$i] : ' tag.id = '.$ids[$i];
			}
			
			
		}
		
		// Arrays for content
		$content = array();
		$news_amount = 0;
		// Initializing standard Joomla classes and SQL necessary variables
		$access_con = '';
		
		$user = JFactory::getUser();	
		if($config['news_unauthorized'] == '0') {
			$access_con = ' AND content.access IN ('. implode(',', $user->getAuthorisedViewLevels()) .') ';
		}
		$date = JFactory::getDate($config['time_offset'].' hour '.date('Y-m-d H:i:s', strtotime('now')));
		$now  = $date->toSql(true);
		$nullDate = $db->getNullDate();
		// if some data are available
		// when showing only frontpage articles is disabled
		$frontpage_con = '';
		
		if($config['only_featured'] == 0 && $config['news_featured'] == 0) {
		 	$frontpage_con = ' AND content.featured = 0 ';
		} else if($config['only_featured'] == 1) {
			$frontpage_con = ' AND content.featured = 1';
		}
		
		$since_con = '';
		if($config['news_since'] !== '') {
			$since_con = ' AND content.created >= ' . $db->Quote($config['news_since']);
		}
		//
		if($config['news_since'] == '' && $config['news_in'] != '') {
			$since_con = ' AND content.created >= ' . $db->Quote(strftime('%Y-%m-%d 00:00:00', time() - ($config['news_in'] * 24 * 60 * 60)));
		}
		
		// current article hiding
		$current_con = '';
		
		if(
			$config['hide_current_com_content_article'] == '1' && 
			JRequest::getCmd('option') == 'com_content' &&
			JRequest::getCmd('view') == 'article' &&
			JRequest::getVar('id') != ''
		) {
			$id = JRequest::getVar('id');
			// filter the alias from ID
			if(stripos($id, ':') !== FALSE) {
				$id = explode(':', $id);
				$id = $id[0];
			}
			// create the condition
			$current_con = ' AND (content.id != '.$id.') ';
		}
		
		// Ordering string
		$order_options = '';
		// When sort value is random
		if($config['news_sort_value'] == 'random') {
			$order_options = ' RAND() '; 
		}else{ // when sort value is different than random
			$order_options = ' content.'.$config['news_sort_value'].' '.$config['news_sort_order'].' ';
		}	
		// language filters
		$lang_filter = '';
		if (JFactory::getApplication()->getLanguageFilter()) {
			$lang_filter = ' AND content.language in ('.$db->quote(JFactory::getLanguage()->getTag()).','.$db->quote('*').') ';
		}
		
		if($config['data_source'] != 'com_content_all') {
			$sql_where = ' AND ( ' . $sql_where . ' ) ';
		}
		
		// one article per page - helper variables
		$article_id_query = 'content.id AS iid';
		$one_article_query = '';
		
		if($config['one_article_per_category']) {
			$article_id_query = 'MAX(content.id) AS iid, content.catid AS cid';
			$one_article_query = ' GROUP BY content.catid ';
		}
		// creating SQL query			
		$query_news = '
		SELECT
			'.$article_id_query.'				
		FROM 
			#__content AS content
			'.$tag_join.' 
		WHERE 
			content.state = 1
                '. $access_con .'   
		 		AND ( content.publish_up = '.$db->Quote($nullDate).' OR content.publish_up <= '.$db->Quote($now).' )
				AND ( content.publish_down = '.$db->Quote($nullDate).' OR content.publish_down >= '.$db->Quote($now).' )
			'.$sql_where.'
			'.$lang_filter.'
			'.$frontpage_con.' 
			'.$since_con.'
			'.$current_con.'
			
		'.$one_article_query.'
		
		ORDER BY 
			'.$order_options.'
		LIMIT
			'.($config['offset']).','.$amount.';
		';
		// run SQL query
		$db->setQuery($query_news);
		// when exist some results
		if($news = $db->loadAssocList()) {			
			// generating tables of news data
			foreach($news as $item) {	
				$content[] = $item; // store item in array
				$news_amount++;	// news amount
			}
		}
		// generate SQL WHERE condition
		$second_sql_where = '';
		for($i = 0; $i < count($content); $i++) {
			$second_sql_where .= (($i != 0) ? ' OR ' : '') . ' content.id = ' . $content[$i]['iid'];
		}
		if($second_sql_where != '') {
			$second_sql_where = ' AND ('.$second_sql_where.')';
		}
		// second SQL query to get rest of the data and avoid the DISTINCT
		$second_query_news = '
		SELECT
			content.id AS iid,
			'.($config['use_title_alias'] ? 'content.alias' : 'content.title').' AS title, 
			content.introtext AS text, 
			content.created AS date, 
			content.publish_up AS date_publish,
			content.hits AS hits,
			content.images AS images,
			content.featured AS frontpage,
			content.access AS access,
			categories.title AS catname, 
			users.email AS author_email,
			content.created_by_alias AS author_alias,
			'.$config['username'].' AS author_username,
			content_rating.rating_sum AS rating_sum,
			content_rating.rating_count AS rating_count,
			CASE WHEN CHAR_LENGTH(content.alias) 
				THEN CONCAT_WS(":", content.id, content.alias) 
					ELSE content.id END as id, 
			CASE WHEN CHAR_LENGTH(categories.alias) 
				THEN CONCAT_WS(":", categories.id, categories.alias) 
					ELSE categories.id END as cid			
		FROM 
			#__content AS content 
			LEFT JOIN 
				#__categories AS categories 
				ON categories.id = content.catid 
			LEFT JOIN 
				#__users AS users 
				ON users.id = content.created_by 			
			LEFT JOIN 
				#__content_rating AS content_rating 
				ON content_rating.content_id = content.id
		WHERE 
			1=1
			'.$second_sql_where.'
		ORDER BY 
			'.$order_options.'
		';
		// run the query
		$db->setQuery($second_query_news);
		// when exist some results
		if($news2 = $db->loadAssocList()) {
			// create the iid array
			$content_iid = array();
			// create the content IDs array
			foreach($content as $item) {
				array_push($content_iid, $item['iid']);
			}
			// generating tables of news data
			foreach($news2 as $item) {						
			    $pos = array_search($item['iid'], $content_iid);
				// check the access restrictions
				$authorised = JAccess::getAuthorisedViewLevels(JFactory::getUser()->get('id'));
				$access = JComponentHelper::getParams('com_content')->get('show_noauth');
				// set the IDs to 0 if the unauthorized items are displayed
				if($config['news_unauthorized'] == '1') {
					if (!($access || in_array($item['access'], $authorised))) { 
						$item['id'] = 0; 
					}
				}
				// merge the new data to the array of items data
				if(isset($content[$pos]) && is_array($content[$pos])) {
					$content[$pos] = array_merge($content[$pos], (array) $item);
				}
			}
		}
		// load comments
		if(stripos($config['info_format'], '%COMMENTS') !== FALSE || stripos($config['info2_format'], '%COMMENTS') !== FALSE) {
			$content = NSP_GK5_com_content_Model::getComments($content, $config);
		}
		if(stripos($config['info_format'], '%TAGS') !== FALSE || stripos($config['info2_format'], '%TAGS') !== FALSE) {
			$content = NSP_GK5_com_content_Model::getTags($content, $config);
		}
		// the content array
		return $content; 
	}
	
	//
	static function getTags($content, $config) {
		// 
		$db = JFactory::getDBO();
		$counters_tab = array();
		// 
		if(count($content) > 0) {
			// initializing variables
			$sql_where = '';
			//
			for($i = 0; $i < count($content); $i++ ) {	
				// linking string with content IDs
				$sql_where .= ($i != 0) ? ' OR content.id = '.$content[$i]['iid'] : ' content.id = '.$content[$i]['iid'];
			}
			// creating SQL query
			$query_news = '
			SELECT 
				content.id AS id,
				tags.title AS tag,
				tags.id AS tag_id		
			FROM 
				#__content AS content 
				LEFT JOIN 
					#__contentitem_tag_map AS tag_map 
					ON content.id = tag_map.content_item_id
				LEFT JOIN 
					#__tags AS tags 
					ON tags.id = tag_map.tag_id 		
			WHERE 
				tags.published
				AND ( '.$sql_where.' ) 
			ORDER BY
				content.id ASC
			;';
			// run SQL query
			$db->setQuery($query_news);
			// when exist some results
			if($counters = $db->loadObjectList()) {
				// generating tables of news data
				foreach($counters as $item) {			
					if(isset($counters_tab[$item->id])) {			
						$counters_tab[$item->id][$item->tag] = $item->tag_id;
					} else {
						$counters_tab[$item->id] = array($item->tag => $item->tag_id);
					}
				}
			}
		}
		//
		for($i = 0; $i < count($content); $i++ ) {	
			if(isset($counters_tab[$content[$i]['iid']])) {
				$content[$i]['tags'] = $counters_tab[$content[$i]['iid']];
			}
		}
		
		
		
		return $content;
	}

	// method to get comments amount
	static function getComments($content, $config) {
		// 
		$db = JFactory::getDBO();
		$counters_tab = array();
		// 
		if(count($content) > 0 && $config['com_content_comments_source'] != 'none') {
			// initializing variables
			$sql_where = '';
			//
			for($i = 0; $i < count($content); $i++ ) {	
				// linking string with content IDs
				$sql_where .= ($i != 0) ? ' OR content.id = '.$content[$i]['iid'] : ' content.id = '.$content[$i]['iid'];
			}
			
			if($config['com_content_comments_source'] == 'komento') {
				// creating SQL query
				$query_news = '
				SELECT 
					content.id AS id,
					COUNT(comments.cid) AS count			
				FROM 
					#__content AS content 
					LEFT JOIN 
						#__komento_comments AS comments
						ON comments.cid = content.id 		
				WHERE 
					comments.published = 1
					AND 
					( '.$sql_where.' )
					AND
					comments.component = \'com_content\'  
				GROUP BY 
					comments.cid
				;';
			}
			// run SQL query
			$db->setQuery($query_news);
			// when exist some results
			if($counters = $db->loadObjectList()) {
				// generating tables of news data
				foreach($counters as $item) {						
					$counters_tab[$item->id] = $item->count;
				}
			}
		}
		//
		for($i = 0; $i < count($content); $i++ ) {	
			if(isset($counters_tab[$content[$i]['iid']])) {
				$content[$i]['comments'] = $counters_tab[$content[$i]['iid']];
			}
		}

		return $content;
	}
}

// EOF