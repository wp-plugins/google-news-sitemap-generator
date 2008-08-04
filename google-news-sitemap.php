<?
/* 
Plugin Name: Google News Sitemap
Plugin URI: http://southcoastwebsites.com/wordpress/
Version: v1.00
Author: <a href="http://southcoastwebsites.com/wordpress/">Chris Jinks</a>
Description: Basic XML sitemap generator for submission to Google News 
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
	$categories = $wpdb->get_results("SELECT category_id FROM $wpdb->post2cat WHERE post_id=$newsID");
	$i = 0;
	$categoryKeywords = "";
	foreach ($categories as $category)
	{
		if ($i>0){$categoryKeywords.= ", ";} //Comma seperator
		$categoryKeywords.= get_catname($category->category_id); //ammed string
		$i++;
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