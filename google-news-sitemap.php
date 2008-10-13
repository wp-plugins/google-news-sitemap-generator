<?
/* 
Plugin Name: Google News Sitemap
Plugin URI: http://southcoastwebsites.co.uk/wordpress/
Version: v1.2
Author: <a href="http://southcoastwebsites.co.uk/wordpress/">Chris Jinks</a>
Description: Basic XML sitemap generator for submission to Google News 


Installation:
==============================================================================
	1. Upload `google-news-sitemap-generator` directory to the `/wp-content/plugins/` directory
	2. Activate the plugin through the 'Plugins' menu in WordPress
	3. Move the file "google-news-sitemap.xml" into your blog root directory and CHMOD to 777 so it is writable
	4. Save/publish/delete a post to generate the sitemap


Release History:
==============================================================================
	2008-08-04		v1.00		First release
	2008-08-17		v1.1		Compatible with new Wordpress database taxonomy (>2.3)
	2008-10-11		v1.2		Improved installation instructions, admin panel, general bug fixing


 
*/

/*  Copyright 2008 Chris Jinks / David Stansbury
	
	Original concept: David Stansbury - http://www.kb3kai.com/david_stansbury/

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

function get_category_keywords($newsID)
{
	global $wpdb;
	
	//Check for new >2.3 Wordpress taxonomy	
	if (function_exists("get_taxonomy") && function_exists("get_terms"))
		{
			//Get categoy names
			$categories = $wpdb->get_results("
					SELECT $wpdb->terms.name FROM $wpdb->term_relationships,  $wpdb->term_taxonomy,  $wpdb->terms
					WHERE $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id
					AND $wpdb->term_taxonomy.term_id =  $wpdb->terms.term_id
					AND $wpdb->term_relationships.object_id = $newsID
					AND $wpdb->term_taxonomy.taxonomy = 'category'");
				$i = 0;
				$categoryKeywords = "";
				foreach ($categories as $category)
				{
					if ($i>0){$categoryKeywords.= ", ";} //Comma seperator
					$categoryKeywords.= $category->name; //ammed string
					$i++;
				}
				
			//Get tags				
			$tags = $wpdb->get_results("
					SELECT $wpdb->terms.name FROM $wpdb->term_relationships,  $wpdb->term_taxonomy,  $wpdb->terms
					WHERE $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id
					AND $wpdb->term_taxonomy.term_id =  $wpdb->terms.term_id
					AND $wpdb->term_relationships.object_id = $newsID
					AND $wpdb->term_taxonomy.taxonomy = 'post_tag'");
				$i = 0;
				$tagKeywords = "";
				foreach ($tags as $tag)
				{
					if ($i>0){$tagKeywords.= ", ";} //Comma seperator
					$tagKeywords.= $tag->name; //ammed string
					$i++;
				}
				

		}
		
	//Old Wordpress database <2.3
	else
		{
			$categories = $wpdb->get_results("SELECT category_id FROM $wpdb->post2cat WHERE post_id=$newsID");
			$i = 0;
			$categoryKeywords = "";
			foreach ($categories as $category)
			{
				if ($i>0){$categoryKeywords.= ", ";} //Comma seperator
				$categoryKeywords.= get_catname($category->category_id); //ammed string
				$i++;
			}
		}
	
	if (get_option('googlenewssitemap_tagkeywords') == 'on')
	{
		if($tagKeywords!=NULL)
		{
			$categoryKeywords = $categoryKeywords.', '.$tagKeywords; //IF tags are included 
		}
	} 
	
	 return $categoryKeywords; //Return post category names as keywords
}

function write_google_news_sitemap() 
{

	global $wpdb;
	// Fetch options from database
	$permalink_structure = $wpdb->get_var("SELECT option_value FROM $wpdb->options 
					WHERE option_name='permalink_structure'");
	$siteurl = $wpdb->get_var("SELECT option_value FROM $wpdb->options
				WHERE option_name='siteurl'");

	// Output XML header
	
	
	// Begin urlset
	$xmlOutput = "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\"
			xmlns:news=\"http://www.google.com/schemas/sitemap-news/0.9\">\n";
	
	
	if (get_option('googlenewssitemap_includePages') == 'on' && get_option('googlenewssitemap_includePosts') == 'on')
		$includeMe = 'AND (post_type="page" OR post_type = "post")';
	
	elseif (get_option('googlenewssitemap_includePages') == 'on')
		$includeMe = 'AND post_type="page"';
	
	elseif (get_option('googlenewssitemap_includePosts') == 'on')
		$includeMe = 'AND post_type="post"';

	
	//Limit to last 3 days, 1000 items					
	$rows = $wpdb->get_results("SELECT ID, post_date_gmt
						FROM $wpdb->posts 
						WHERE post_status='publish' 
						AND (DATEDIFF(CURDATE(), post_date_gmt)<=3)
						$includeMe
						ORDER BY post_date_gmt DESC
						LIMIT 0, 1000");	
										
	
	// Output sitemap data
	foreach($rows as $row){
		$xmlOutput.= "\t<url>\n";
		$xmlOutput.= "\t\t<loc>";
		$xmlOutput.= get_permalink($row->ID);
		$xmlOutput.= "</loc>\n";
		$xmlOutput.= "\t\t<news:news>\n";
		$xmlOutput.= "\t\t<news:publication_date>";
		$thedate = substr($row->post_date_gmt, 0, 10);
		$thetime = substr($row->post_date_gmt, 11, 20);
		$xmlOutput.= $thedate . 'T' . $thetime . 'Z';
		$xmlOutput.= "</news:publication_date>\n";
		$xmlOutput.= "\t\t<news:keywords>";
		
		//Use the categories for keywords
		$xmlOutput.= get_category_keywords($row->ID);
		
		$xmlOutput.= "</news:keywords>\n"; 
		$xmlOutput.= "\t\t</news:news>\n";
		$xmlOutput.= "\t</url>\n";
	}
	
	// End urlset
	$xmlOutput.= "</urlset>\n";
	$xmlOutput.= "<!-- Last build time: ".date("F j, Y, g:i a")."-->";
	
	$xmlFile = "../google-news-sitemap.xml";
	$fp = fopen($xmlFile, "w+"); // open the cache file "google-news-sitemap.xml" for writing
	fwrite($fp, $xmlOutput); // save the contents of output buffer to the file
	fclose($fp); // close the file
}


if(function_exists('add_action')) //Stop error when directly accessing the PHP file
{
	add_action('publish_post', 'write_google_news_sitemap');
	add_action('save_post', 'write_google_news_sitemap');
	add_action('delete_post', 'write_google_news_sitemap');
}
else  //Friendly error message :)
{
	?>
	<p style="color:#FF0000"><em>Accessing this file directly will not generate the sitemap.</em></p>
	<p>The sitemap will be generated automatically when you save/pubish/delete a post from the standard Wordpress interface.</p>
	<p><strong>Instructions</strong></p>
	<p>1. Upload `google-news-sitemap-generator` directory to the `/wp-content/plugins/` directory<br />
	2. Activate the plugin through the 'Plugins' menu in WordPress<br />
	3. Move the file "google-news-sitemap.xml" into your blog root directory and CHMOD to 777 so it is writable<br />
	4. Save/publish/delete a post to generate the sitemap</p>
	<?
}
?>


<?

//
// Admin panel options.... //
//

add_action('admin_menu', 'show_googlenewssitemap_options');

function show_googlenewssitemap_options() {
    // Add a new submenu under Options:
    add_options_page('Google News Sitemap Generator Plugin Options', 'Google News Sitemap', 8, 'googlenewssitemap', 'googlenewssitemap_options');
	
	
	//Add options for plugin
	add_option('googlenewssitemap_includePosts', 'on');
	add_option('googlenewssitemap_includePages', 'off');
	add_option('googlenewssitemap_tagkeywords', 'off');
	
}


// Admin page HTML

function googlenewssitemap_options() { ?>
<style type="text/css">
div.headerWrap { background-color:#e4f2fds; width:200px}
#options h3 { padding:7px; margin-top:10px; }
#options label { width: 200px; float: left; margin-left: 10px; }
#options input { float: left; margin-left:10px}
#options p { clear: both; padding-bottom:10px; }
</style>
<div class="wrap">
<form method="post" action="options.php" id="options">
<?php wp_nonce_field('update-options') ?>
<h2>Google News Sitemap Options</h2>

<div id="poststuff" style="float:right;width:220px;margin-left:10px; margin-left:-230px;">
<h3>Information</h3>
	<div id="dbx-content" style="text-decoration:none;">
		<a href="http://wordpress.org/extend/plugins/google-news-sitemap-generator/" style="text-decoration:none">Wordpress plugin page</a><br /><br />
		<a href="http://www.southcoastwebsites.co.uk/wordpress/" style="text-decoration:none">Plugin homepage</a><br />
<br />
<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=396299">
<img src="https://www.paypal.com/en_GB/i/btn/btn_donateCC_LG.gif" border="0" style="padding-left:10px" /></a><br />
Thank you for your support!
	</div>
</div>

<div style="float:left; margin-right:230px">

<div id="poststuff">
<h3>Sitemap contents</h3>
</div>

		<p>
			<?php
				if (get_option('googlenewssitemap_includePosts') == 'on') {echo '<input type="checkbox" name="googlenewssitemap_includePosts" checked="yes" />';}
				else {echo '<input type="checkbox" name="googlenewssitemap_includePosts" />';}
			?>
			<label>Include posts</label>
		</p>
		<p>
			<?php
				if (get_option('googlenewssitemap_includePages') == 'on') {echo '<input type="checkbox" name="googlenewssitemap_includePages" checked="yes" />';}
				else {echo '<input type="checkbox" name="googlenewssitemap_includePages" />';}
			?>
			<label>Include pages</label>
		</p>
		
<br />
<br />
		
<div id="poststuff">
<h3>Sitemap keywords</h3>
</div>

		
		<p>
			<?php
				if (get_option('googlenewssitemap_tagkeywords') == 'on') {echo '<input type="checkbox" name="googlenewssitemap_tagkeywords" checked="yes" />';}
				else {echo '<input type="checkbox" name="googlenewssitemap_tagkeywords" />';}
			?>
			<label>Use post tags in keywords</label>
		</p>		


<br />
<br />

<h4>After updating these options, please rebuild the Google News Sitemap by saving/editing/publishing a post or page.</h4>
		<input type="hidden" name="action" value="update" />
		<input type="hidden" name="page_options" value="googlenewssitemap_includePosts,googlenewssitemap_includePages,googlenewssitemap_tagkeywords" />
		<div style="clear:both;padding-top:20px;"></div>
		<p class="submit"><input type="submit" name="Submit" value="<?php _e('Update Options') ?>" /></p>
		<div style="clear:both;padding-top:20px;"></div>
		</form>
</div>
</div>
<?php } ?>