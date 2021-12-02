<?php
/**
 * Ajax Controller for Libraries Extension
 *
 * PHP version 5
 *
 * Copyright (C) Staats- und Universitätsbibliothek 2017.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Hajo Seng <hajo.seng@sub.uni-hamburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/subhh/beluga
 */
namespace Libraries\AjaxHandler;

use Libraries\Selector;
use VuFind\Search\Results\PluginManager as ResultsManager;
use VuFind\Search\Memory;
use Libraries\Libraries;
use VuFind\AjaxHandler\AbstractBase;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Mvc\I18n\Translator;
use Laminas\Stdlib\Parameters;
use Laminas\Config\Config;

/**
 * This controller handles global AJAX functionality
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class GetLibraries extends AbstractBase
{
    /**
     * Libraries
     *
     * @var Libraries
     */
    protected $Libraries;

    /**
     * ResultsManager
     *
     * @var ResultsManager
     */
    protected $resultsManager;

    /**
     * Translation helper
     *
     * @var TransEsc
     */
    protected $translator;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(Config $config, ResultsManager $resultsManager, Memory $searchMemory, Translator $translator)
    {
        $this->resultsManager = $resultsManager;
        $this->Libraries = new Libraries(
            $config,
            $searchMemory
        );
        $this->translator = $translator;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $queryString = urldecode($params->fromQuery('querystring'));
        $queryString = str_replace('&amp;', '&',
            substr_replace(
                trim($queryString), '', 0, 1
            )
        );
        $queryArray = explode('&', $queryString);
        $searchParams = [];
        foreach ($queryArray as $queryItem) {
            $arrayKey = false;
            list($key, $value) = explode('=', $queryItem, 2);
            if ($key == 'library') {
                $selectedLibraryCode = $value;
            } elseif (preg_match('/[0-9](\[\]$)/', $key, $matches)) {
                $key = str_replace($matches[1], '', $key);
                $searchParams[$key][] = $value;
            } else {
                $selectedLibraryCode = $value;
            }
        }
        $this->Libraries->selectLibrary($selectedLibraryCode);
        $backend = $params->fromQuery('source', DEFAULT_SEARCH_BACKEND);
        $locationFilter = $this->Libraries->getLocationFilter();
        $libraryFacet = $this->Libraries->getLibraryFacetField($backend);
        $facetSearch = $this->Libraries->getFacetSearch($backend);

        $results = $this->resultsManager->get($backend);
        $paramsObj = $results->getParams();
        $paramsObj->addFacet($libraryFacet, null, false);
        if (!empty($locationFilter['field'])) {
            $paramsObj->addFacet($locationFilter['field'], null, false);
            $paramsObj->setFacetFieldPrefix($locationFilter['field'], $locationFilter['prefix']);
        }
        $paramsObj->setFacetLimit(2000); 
        $paramsObj->getOptions()->disableHighlighting();
        $paramsObj->getOptions()->spellcheckEnabled(false);

        if (!empty($facetSearch)) {
            $this->Libraries->selectLibrary($facetSearch);
        }
        $this->Libraries->resetLibraries();
        $paramsObj->initFromRequest(new Parameters($searchParams));
        $this->Libraries->selectLibrary($selectedLibraryCode);

        $facetList = $results->getFacetList();
        $libraryList = $facetList[$libraryFacet]['list'];
        $locationList = $facetList[$locationFilter['field']]['list'];

        $defaultLibraryCode = $this->Libraries->getDefaultLibraryCode($backend);
        array_unshift($libraryList, ['value' => $defaultLibraryCode, 'count' => $results->getResultTotal()]);
        $libraryData = [];

        foreach ($this->Libraries->getLibraryFacetValues($backend) as $libraryCode => $libraryFacetValue) {
            $library = $this->Libraries->getLibrary($libraryCode);
            $facetValues = explode(',', $libraryFacetValue);
            $count = 0;
            foreach ($facetValues as $facetValue) {
                foreach ($libraryList as $libraryItem) {
                    if ($facetValue == '*' || $libraryItem['value'] == $facetValue) {
                        $count += $libraryItem['count'];
                    }
                }
            }
            $libraryData[$libraryCode] = ['fullname' => $this->translator->translate($library['fullname']), 'count' => $count];
        }

        $locationFacets = [];
        foreach ($locationList as $locationItem) {
            $locationFacets[$locationItem['value']] = $locationItem['count'];
        }
        $locationFacets = $this->Libraries->getLocationList($locationFacets);

        $data = [
            'libraryCount' => count($libraryData),
            'libraryData' => $libraryData,
            'locationFacets' => $locationFacets,
            'locationFilter' => ['field' => $locationFilter['field'], 'value' => ''],
            'selectedLibraryCode' => $selectedLibraryCode,
        ];
        return $this->formatResponse($data);
    }

}
