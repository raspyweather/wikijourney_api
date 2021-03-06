<?php
/*
========= WIKIJOURNEY API - getAndParseWikipediaPOI.php =============

This fonction connects to Wikipedia, get pages around using geosearch,
and make several calls to get other informations, like type id or 
thumbnail URL.

Source : https://github.com/WikiJourney/wikijourney_api

Copyright 2016 WikiJourney

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

*/

function getAndParseWikipediaPOI($language, $user_latitude, $user_longitude, $range, $maxPOI, $thumbnailWidth)
{
	// ===> Apply limits of Wikipedia API
	$range = min($range, 10);
	$maxPOI = min($maxPOI, 500);

	// ===> Call Wikipedia GeoSearch API to get pages around
	$apidata_request_geosearch = "https://".$language.".wikipedia.org/w/api.php?action=query&list=geosearch&gslimit=".$maxPOI."&gsradius=".($range * 1000)."&gscoord=".$user_latitude."|".$user_longitude."&format=json";
	if(!($apidata_json_geosearch = @file_get_contents($apidata_request_geosearch)))
		return array("error" => "Wikipedia API is unreachable");

	// ===> Case there is no POI around at all
	if(!($apidata_array_geosearch = @json_decode($apidata_json_geosearch, true)['query']['geosearch']))
		return array();

	$wikipedia_pagesid_list = "";

	// ===> Parse those data in the output array
	foreach ($apidata_array_geosearch as $currentPOI => $currentPOIdata) {
		$wikipedia_pagesid_list .= '|'.$currentPOIdata['pageid'];
		$output_array[$currentPOIdata['pageid']]['id'] = $currentPOIdata['pageid'];
		$output_array[$currentPOIdata['pageid']]['name'] = $apidata_array_geosearch[$currentPOI]['title'];
		$output_array[$currentPOIdata['pageid']]['latitude'] = $apidata_array_geosearch[$currentPOI]['lat'];
		$output_array[$currentPOIdata['pageid']]['longitude'] = $apidata_array_geosearch[$currentPOI]['lon'];
		$output_array[$currentPOIdata['pageid']]['distance'] = $apidata_array_geosearch[$currentPOI]['dist'];
	}
	$wikipedia_pagesid_list = substr($wikipedia_pagesid_list, 1);

	// ===> Now we got a list of wikipedia pages so we call Wikipedia API again to get infos on those pages
	$apidata_request_wikipedia_info = "https://".$language.".wikipedia.org/w/api.php?format=json&action=query&prop=pageprops|info|pageimages&inprop=url&pilimit=1000&pithumbsize=".$thumbnailWidth."&pageids=".$wikipedia_pagesid_list;
	if(!($apidata_json_wikipedia_info = @file_get_contents($apidata_request_wikipedia_info)))
		return array("error" => "Wikipedia API is unreachable");

	$apidata_array_wikipedia_info = json_decode($apidata_json_wikipedia_info,true);

	// ===> Parse wikipedia return
	$images_id_list = "";
	foreach ($apidata_array_wikipedia_info['query']['pages'] as $currentPOI => $currentPOIdata) {
		$output_array[$currentPOI]['name'] = $apidata_array_wikipedia_info['query']['pages'][$currentPOI]["title"];
		$output_array[$currentPOI]['sitelink'] = $apidata_array_wikipedia_info['query']['pages'][$currentPOI]["fullurl"];
		$output_array[$currentPOI]['wikidata_id'] = $apidata_array_wikipedia_info['query']['pages'][$currentPOI]["pageprops"]["wikibase_item"];
		// We put an @ because it can be null
		$output_array[$currentPOI]['image_url'] = @$apidata_array_wikipedia_info['query']['pages'][$currentPOI]["thumbnail"]["source"];

		// And for each wikipedia POI, we add the URL to the list of links we're going to call for wikidata's info
		$CURL_input_list[$currentPOI] = 'https://www.wikidata.org/w/api.php?action=wbgetclaims&format=json&property=P31&entity='.$output_array[$currentPOI]['wikidata_id'];
	}
	$type_id_list = "";

	// ===> Make the API call to Wikidata using multiple requests in the same time with CURL and parse the result
	$CURL_return = reqMultiCurls($CURL_input_list);
	
	foreach ($output_array as $currentPOI => $currentPOIdata) {
		// We put an @ because it can be null
		$output_array[$currentPOI]['type_id'] = @$CURL_return[$currentPOI]['claims']['P31'][0]['mainsnak']['datavalue']['value']['numeric-id'];
		// And in the case it's NOT null, we add it in the list of type names to fetch
		if($output_array[$currentPOI]['type_id'] != NULL) 
			$type_id_list .= '|Q'.$output_array[$currentPOI]['type_id'];
	}
	$type_id_list = substr($type_id_list, 1);

	// ===> Call WikiData to get type_name using the type_id (church, metro station, etc.)
	$apidata_request_wikidata_type_name = 'https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&props=labels&languages='.$language.'&ids='.$type_id_list;
	if(!($apidata_json_wikidata_type_name = @file_get_contents($apidata_request_wikidata_type_name)))
		return array("error" => "Wikidata API is unreachable");

	$apidata_array_wikidata_type_name = json_decode($apidata_json_wikidata_type_name,true);

	// ===> Putting those type_name in the output array
	foreach ($output_array as $currentPOI => $currentPOIdata) {
		$output_array[$currentPOI]['type_name'] = @$apidata_array_wikidata_type_name['entities']['Q'.$output_array[$currentPOI]['type_id']]['labels'][$language]['value'];
	}

	return array_values($output_array);
}
