<?php

$plugin['version'] = '0.3';
$plugin['author'] = 'Walker Hamilton';
$plugin['author_uri'] = 'http://www.walkerhamilton.com';
$plugin['description'] = 'Users can rate an article up or down.';

$plugin['type'] = 1;

@include_once('zem_tpl.php');

if (0) {
?>
# --- BEGIN PLUGIN HELP ---
h1. wlk_helpful

This plugin is provided "as is". I've only tested it with 4.0.5, but it'll probably work with older installs as long as you've got jQuery linked in your pages.

h2. Installation

Since you can read this help, you have installed the plugin to txp.
Did you activate it?

h2. Installation

# Create a page called wlk_helpful_ajax
# Delete everything in that page.
# Put this tag in that page: @<txp:wlk_helpful_ajax />@ (Tell it @which="ratio"@ to have it count by ratio rather than positivity -- simple.)
# Create a section called "wlk_helpful_ajax"
# To have it use the page wlk_helpful_ajax
# Make sure that all your article pages that will display @<txp:wlk_helpful />@ reference the jquery file found in your @/textpattern/@ directory.
# Add the javascript found at "this link":http://dev.signalfade.com/txp/wlk_helpful_js.zip within the head of your document or referenced in an external javascript file.

h2. Tag Placement

Place the @<txp:wlk_helpful />@ tag in an article form or on a page that has a single article.

Put @<txp:wlk_helpful_list />@ or @<txp:wlk_helpful_list which="bottom" />@ in a sidebar or on your main page to show an unordered list of the most helpful or least helpful articles as rated by your users.



# --- END PLUGIN HELP ---
<?php
}

# --- BEGIN PLUGIN CODE ---
	
	if (@txpinterface == 'admin')
	{
		register_callback("wlk_helpful_install", "page");
	}

	function wlk_helpful_install() {
		safe_query('CREATE TABLE IF NOT EXISTS '.safe_pfx('txp_wlk_helpful').' (
					`id` int(11) NOT NULL auto_increment,
					`textpattern_id` int(11) NOT NULL,
					`plus` tinyint(1) NOT NULL,
					`minus` tinyint(1) NOT NULL,
					`ip` varchar(15) binary NOT NULL,
					PRIMARY KEY  (`id`)
					) TYPE=MyISAM AUTO_INCREMENT=1');
		safe_query('CREATE TABLE IF NOT EXISTS '.safe_pfx('txp_wlk_helpful_counts').' (
					`id` int(11) NOT NULL auto_increment,
					`textpattern_id` int(11) NOT NULL,
					`count` int(5) NOT NULL default \'0\',
					`pluses` varchar(5) binary NOT NULL default \'0\',
					`minuses` varchar(5) binary NOT NULL default \'0\',
					PRIMARY KEY  (`id`)
					) TYPE=MyISAM AUTO_INCREMENT=1');
	}
	
	function wlk_helpful($atts) {
		global $thisarticle;

		extract(lAtts(array(
			'label'=> (!empty($prefs['wlk_helpful_label']))?$prefs['wlk_helpful_label']:'Helpful?',
			'debug'=> 'false'
		),$atts));

		//Grab this article's count of plus & minus
		if($thisarticle['thisid'])
		{
			$results = safe_row('pluses, minuses', 'txp_wlk_helpful_counts', 'textpattern_id="'.addslashes($thisarticle['thisid']).'"');
			if(count($results)==0) { 
				safe_insert('txp_wlk_helpful_counts', "pluses='0',minuses='0',count='0',textpattern_id='".addslashes($thisarticle['thisid'])."'");
				$results = safe_row('pluses, minuses', 'txp_wlk_helpful_counts', 'textpattern_id="'.addslashes($thisarticle['thisid']).'"');
			}
		}

		//create the HTML
		$out = '
			<ul class="wlk_helpfulrater">
				<li class="helpful">Helpful?</li>
				<li class="plus"><span class="thecount">'.$results['pluses'].'</span><span class="thisid" style="display:none;">'.$thisarticle['thisid'].'</span> <a class="wlk_helpfulplus floatleft" href="#"><span class="distext">Yes</span></a></li>
				<li class="minus"><span class="thecount">'.$results['minuses'].'</span><span class="thisid" style="display:none;">'.$thisarticle['thisid'].'</span> <a class="wlk_helpfulminus floatleft" href="#"><span class="distext">No</span></a></li>
			</ul>
			';

		//Return it
		return $out;
	}

	function wlk_helpful_list($atts)
	{
		global $prefs;
		global $permlink_mode;

		extract(lAtts(array(
			'which'=> (isset($prefs['wlk_helpful_list_which']) && $prefs['wlk_helpful_list_which']=='bottom')?'bottom':'top',
			'order'=> (isset($prefs['wlk_helpful_list_which']) && $prefs['wlk_helpful_list_which']=='bottom')?'ASC':'DESC',
			'limit'=> (isset($prefs['wlk_helpful_list_limit']) && is_numeric($prefs['wlk_helpful_list_limit']))?$prefs['wlk_helpful_list_limit']:'5',
			'debug'=> 'false'
		),$atts));

		safe_query('DELETE FROM '.safe_pfx('txp_wlk_helpful_counts').' WHERE textpattern_id NOT IN ( SELECT ID FROM '.safe_pfx('textpattern').' )');

		//Grab the articles with the "top" or "bottom" count count
		$results = safe_query('SELECT txp.ID, txp.Title, txp.Section, txp.Posted, txp.url_title FROM '.safe_pfx('txp_wlk_helpful_counts').' AS helpful LEFT JOIN '.safe_pfx('textpattern').' AS txp ON txp.ID=helpful.textpattern_id ORDER BY count '.$order.' LIMIT 0, '.$limit);

		if(mysql_num_rows($results)>0)
		{
			$results_r = array();
			while($row = mysql_fetch_assoc($results))
			{
				$article_array = $row;
				$article_array['permlink'] = permlinkurl($article_array);
				$results_r[] = $article_array;
			}
			$out = '<ol id="wlkhlpfl'.$which.'">'."\r";
			//create the HTML
			foreach($results_r as $article)
			{
				$out .= "\r\t".'<li><a href="'.$article['permlink'].'">'.$article['Title'].'</a></li>';
			}
			$out .= "\r".'</ol>';
			//Return it
			return $out;
		} else {
			return '';
		}
	}

//-----------------------------------
//				Ajax Logic
//------------------------------------
	function wlk_helpful_ajax($atts)
	{
		extract(lAtts(array(
			'which'=> (isset($prefs['wlk_helpful_ajax']))?$prefs['wlk_helpful_ajax']:'simple',
			'debug'=> false
		),$atts));

		if(isset($_POST['article_id']) && isset($_SERVER['REMOTE_ADDR'])) {
			//receive the article id, rating
			$textpattern_id = $_POST['article_id'];
			$up_down = $_POST['up_down'];
			//get the IP
			$ip = $_SERVER['REMOTE_ADDR'];
			
			$out = array();
			
			if($up_down=='up' || $up_down=='down') {
				//make sure they haven't rated this one before
				$check = safe_query('SELECT * FROM '.safe_pfx('txp_wlk_helpful').' WHERE textpattern_id="'.addslashes($textpattern_id).'" AND ip="'.addslashes($ip).'"');
				$out[] = 'SELECT * FROM '.safe_pfx('txp_wlk_helpful').' WHERE textpattern_id="'.addslashes($textpattern_id).'" AND ip="'.addslashes($ip).'"';
				
				if($up_down=='down') {
					$up = 0;
					$down = 1;
				} else if($up_down=='up') {
					$up = 1;
					$down = 0;
				}
				
				if(mysql_num_rows($check)==0)
				{
					//save their rating if they haven't
					$add = safe_query('INSERT INTO '.safe_pfx('txp_wlk_helpful').' (id, textpattern_id, plus, minus, ip) VALUES (null, "'.addslashes($textpattern_id).'", "'.$up.'", "'.$down.'", "'.addslashes($ip).'")');
					$out[] = 'INSERT INTO '.safe_pfx('txp_wlk_helpful').' (id, textpattern_id, plus, minus, ip) VALUES (null, "'.addslashes($textpattern_id).'", "'.$up.'", "'.$down.'", "'.addslashes($ip).'")';
					
					//get the current overall from the db
					$change = safe_query('SELECT * FROM '.safe_pfx('txp_wlk_helpful_counts').' WHERE textpattern_id="'.addslashes($textpattern_id).'"');
					$out[] = 'SELECT * FROM '.safe_pfx('txp_wlk_helpful_counts').' WHERE textpattern_id="'.addslashes($textpattern_id).'"';
					
					if(mysql_num_rows($change)==1) {
						$row = mysql_fetch_assoc($change);
						if($up_down=='down') {
							if($which=='simple') {
								$row['count'] = $row['count']-1;
							} else {
								$row['count'] = ($row['pluses']/($row['pluses']+1+$row['minuses']));
							}
							$row['minuses']++;
						} else if($up_down=='up') {
							if($which=='simple') {
								$row['count']++;
							} else {
								$row['count'] = ($row['pluses']/($row['pluses']+1+$row['minuses']));
							}
							$row['pluses']++;
						}
						$update_count = safe_query('UPDATE '.safe_pfx('txp_wlk_helpful_counts').' SET count="'.addslashes($row['count']).'", pluses="'.addslashes($row['pluses']).'", minuses="'.addslashes($row['minuses']).'" WHERE textpattern_id="'.addslashes($textpattern_id).'"');
						$out[] = 'UPDATE '.safe_pfx('txp_wlk_helpful_counts').' SET count="'.addslashes($row['count']).'", pluses="'.addslashes($row['pluses']).'", minuses="'.addslashes($row['minuses']).'" WHERE textpattern_id="'.addslashes($textpattern_id).'"';
					} else {
						$row = array('count'=>0, 'pluses'=>0, 'minuses'=>0);
						if($up_down=='down') {
							if($which=='simple') {
								$row['count'] = $row['count']-1;
							} else {
								$row['count'] = ($row['pluses']/($row['pluses']+1+$row['minuses']));
							}
							$row['minuses']++;
						} else if($up_down=='up') {
							if($which=='simple') {
								$row['count']++;
							} else {
								$row['count'] = ($row['pluses']/($row['pluses']+1+$row['minuses']));
							}
							$row['pluses']++;
						}
						$insert_count = safe_query('INSERT INTO '.safe_pfx('txp_wlk_helpful_counts').' (id, textpattern_id, count, pluses, minuses) VALUES (null, "'.addslashes($textpattern_id).'", "'.addslashes($row['count']).'", "'.addslashes($row['pluses']).'", "'.addslashes($row['minuses']).'")');
						$out[] = 'INSERT INTO '.safe_pfx('txp_wlk_helpful_counts').'s (id, textpattern_id, count, pluses, minuses) VALUES (null, "'.addslashes($textpattern_id).'", "'.addslashes($row['count']).'", "'.addslashes($row['pluses']).'", "'.addslashes($row['minuses']).'")';
					}
					if($debug) {
						$theout = implode('<br />', $out);
					} else {
						$theout = '';
					}
				//return true or false (just to tell them that the request succeeded
					return $theout.'true';
				} else {
					if($debug) {
						$theout = implode('<br />', $out);
					} else {
						$theout = '';
					}
					return $theout.'';
				}
			} else {
				if($debug) {
					$theout = implode('<br />', $out);
				} else {
					$theout = '';
				}
				return $theout.'';
			}
		}
		//Should really run a cleanup to get rid of any counts & ratings for non-existent articles.
	}

# --- END PLUGIN CODE ---
?>