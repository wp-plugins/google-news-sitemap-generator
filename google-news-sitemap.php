<?
/* 
Plugin Name: Google News Sitemap
Plugin URI: http://southcoastwebsites.co.uk/wordpress/
Version: v1.1
Author: <a href="http://southcoastwebsites.co.uk/wordpress/">Chris Jinks</a>
Description: Basic XML sitemap generator for submission to Google News 


Installation:
==============================================================================
	1. Upload `google-news-sitemap-generator` directory to the `/wp-content/plugins/` directory
	2. Activate the plugin through the 'Plugins' menu in WordPress
	3. Create file "google-news-sitemap.xml" in your root directory and CHMOD so it is writable


Release History:
==============================================================================
	2008-08-04		v1.00		First release
	2008-08-17		v1.1		Compatible with new Wordpress database taxonomy (>2.3)



 
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
	
	//New >2.3 Wordpress taxonomy	
	if ( get_bloginfo('version') >= 2.3 )
		{
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
		}
		
	//Old Wordpress database taxonomy
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
	
	// Select all posts
	//$rows = $wpdb->get_results("SELECT ID, post_date_gmt
	//       				FROM $wpdb->posts WHERE post_status='publish'");
	
	
	//Limit to last 3 days, 1000 items					
	$rows = $wpdb->get_results("SELECT ID, post_date_gmt
						FROM $wpdb->posts 
						WHERE post_status='publish' AND (DATEDIFF(CURDATE(), post_date_gmt)<=3) 
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
	$fp = fopen($xmlFile, "w+"); // open the cache file "news-sitemap.xml" for writing
	fwrite($fp, $xmlOutput); // save the contents of output buffer to the file
	fclose($fp); // close the file
}

add_action('publish_post', 'write_google_news_sitemap');
add_action('save_post', 'write_google_news_sitemap');
add_action('delete_post', 'write_google_news_sitemap');
?>