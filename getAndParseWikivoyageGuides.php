<?php
/*
========= WIKIJOURNEY API - getAndParseWikivoyagesGuides.php =============

This fonction connects to Wikivoyage in order to get guides around.

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

function getAndParseWikivoyageGuides($language, $user_latitude, $user_longitude, $wikiVoyageRange)
{
	// ===> Make the URL 
	$wikivoyageRequest = 'https://'.$language.'.wikivoyage.org/w/api.php?action=query&format=json&' // Base
	.'prop=coordinates|info|pageterms|pageimages&' // Props list
	.'piprop=thumbnail&pithumbsize=144&pilimit=50&inprop=url&wbptterms=description' // Properties dedicated to image, url and description
	."&generator=geosearch&ggscoord=$user_latitude|$user_longitude&ggsradius=". $wikiVoyageRange*1000 ."&ggslimit=50"; // Properties dedicated to geosearch

	// ===> Make the call and check
	if(!($wikivoyage_json = @file_get_contents($wikivoyageRequest))) 
		return array("error" => "WikiVoyage API is unreachable");

	// ===> Parse the json in an array
	$wikivoyage_array = json_decode($wikivoyage_json, true);

	// ===> Case there is no guide around (in the selected language)
	if (!isset($wikivoyage_array['query']['pages'])) 
		return array(); //Return an empty array

	// ===> Reindexing the array (because it's initially indexed by pageid)
	$wikivoyage_clean_array = array_values($wikivoyage_array['query']['pages']); 

	// ===> Copy the data we need in the output
	foreach ($wikivoyage_clean_array as $currentGuide => $currentGuideValue) {

		$output[$currentGuide]['pageid'] = $currentGuideValue['pageid'];
		$output[$currentGuide]['title'] = $currentGuideValue['title'];
		$output[$currentGuide]['sitelink'] = $currentGuideValue['fullurl'];

		// The next three can be null, so we put an @
		$output[$currentGuide]['latitude'] = @$currentGuideValue['coordinates'][0]['lat'];
		$output[$currentGuide]['longitude'] = @$currentGuideValue['coordinates'][0]['lon'];
		$output[$currentGuide]['thumbnail'] = @$currentGuideValue['thumbnail']['source'];

	}
	
	return $output;
}
