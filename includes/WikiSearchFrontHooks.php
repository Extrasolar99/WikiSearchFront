<?php

namespace WikiSearchFront;

use WikiSearchFront\WikiSearchParams;
use MediaWiki\MediaWikiServices;
use Title;
use User;
use ParserOptions;

/**
 * Class for the Frontend of WikiSearch
 */
class WikiSearchFrontHooks {
	
	/**
	 * @param array $results
	 * @param string $template
	 * @param array $properties
	 *
	 * @return array
	 */
	public static function onWikiSearchApplyResultTranslations( &$results, &$template, &$properties ) {
		if (!$template) {
			return $results;
		}

		function strip($string) {
			return str_replace(array("|", "{", "}"), "", $string);
		}

		$popt = new ParserOptions(
			User::newFromId( 0 ),
			MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( 'en' )
		); 

		$wikitext = "";
		
		foreach ($results["hits"]["hits"] as $result) {

			$args = "";

            foreach ($properties as $property) {

				$propID = $property->getPropertyID();
				$propType = $property->getPropertyType();

				$val = "";
				if (
					$result["_source"]
					&& $result["_source"]["P:" . $propID]
					&& $result["_source"]["P:" . $propID][$propType]
				) {
					$val .= implode(",", $result["_source"]["P:" . $propID][$propType]);
				}

				$args .= "|" . $property->getPropertyName() . "=" . strip($val);
			}
		
			$args .= '|$page=' . strip($result["_source"]["subject"]["title"]);
			$args .= '|$namespace=' . strip($result["_source"]["subject"]["namespace"]);
			$args .= '|$namespacename=' . strip($result["_source"]["subject"]["namespacename"]);
			$args .= '|$snippet=';

			foreach ($result["highlight"] as $snippet) {
				$args .= "<nowiki>";
				$args .= str_replace(
					"@@_HIGHLIGHT_@@}",
					"</span><nowiki>",
					str_replace(
						"{@@_HIGHLIGHT_@@",
						"</nowiki><span class='wikisearch-term-highlight'>",
						implode(",", $snippet)
					)
				);
				$args .= "</nowiki>";
			}

			$wikitext .= "{{" . $template . $args . "}}";
		}

		$parser = MediaWikiServices::getInstance()->getParserFactory()->create();
		$res = $parser->parse( $wikitext, Title::newFromTextThrow( "wikisearchfront" ), $popt );

		return $results["hits"]["hits"] = $res;
	}

	/**
	 * @param string $result
	 * @param \WikiSearch\SearchEngineConfig $config
	 * @param Parser $parser
	 * @param array $parameters
	 *
	 * @return bool
	 */
	public static function onWikiSearchOnLoadFrontend(
		string &$result,
		\WikiSearch\SearchEngineConfig $config,
		\Parser $parser,
		array $parameters
	) {
		$searchConfig = [
			"settings"      => (Object)[],
			"facetSettings" => (Object)[],
			"hitSettings"   => (Object)[],
		];

		$params = new WikiSearchParams();

		foreach ( $parameters as $input_parameter ) {
			//if we have a parameter
			if ( $input_parameter && ! empty( $input_parameter ) ) {
				$firstChar = substr(
					$input_parameter,
					0,
					1
				);

				// Switch based on first character or default
				switch ( $firstChar ) {
					case "@" :
						$params->getFacetSettings(
							$input_parameter,
							$searchConfig
						);
						break;
					case "?":
						$params->getHitSettings(
							$input_parameter,
							$searchConfig
						);
						break;
					default:
						$params->getParameterOutput(
							$input_parameter,
							$searchConfig
						);
				}
			}
		}

		$parser->getOutput()->addJsConfigVars( "WikiSearchFront",
											   array(
												   "config" => $searchConfig
											   ) );
		$parser->getOutput()->addModules( 'ext.WikiSearchFront.module' );

		$result = "<div id='app'></div>";

		return true;
	}


}
