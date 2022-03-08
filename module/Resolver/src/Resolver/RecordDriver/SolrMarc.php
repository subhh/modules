<?php
/**
 * Module RecordDriver: SolrMarc Parser
 *
 * PHP version 7
 *
 * Copyright (C) Staats- und Universitätsbibliothek Hamburg 2018.
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
 * @category VuFind
 * @package  RecordDrivers
 * @author   Hajo Seng <hajo.seng@sub.uni-hamburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/beluga-core
 */
namespace Resolver\RecordDriver;

if (class_exists('RecordDriver\RecordDriver\SolrMarc')) {
    class_alias('RecordDriver\RecordDriver\SolrMarc', 'Resolver\RecordDriver\SolrMarcBase');
} elseif (class_exists('VuFind\RecordDriver\SolrMarc')) {
    class_alias('VuFind\RecordDriver\SolrMarc', 'Resolver\RecordDriver\SolrMarcBase');
}

/**
 * Model for MARC records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Hajo Seng <hajo.seng@sub.uni-hamburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class SolrMarc extends SolrMarcBase
{
    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $mainConfig     VuFind main configuration (omit for
     * built-in defaults)
     * @param \Laminas\Config\Config $recordConfig   Record-specific configuration file
     * (omit to use $mainConfig as $recordConfig)
     * @param \Laminas\Config\Config $searchSettings Search-specific configuration file
     * @param string $marcYaml
     */
/*
    public function __construct($mainConfig = null, $recordConfig = null,
        $searchSettings = null, $solrMarcYaml = null
    ) {
        $this->addSolrMarcYaml($solrMarcYaml, false);
        parent::__construct($mainConfig, $recordConfig, $searchSettings);
    }
*/
    /**
     * Set and parse SolrMarcSpecs config.
     *
     * @return void
     */
    public function hasFidLicence()
    {
        $fidLicenceData = $this->getFidLink('FidRomLicence');
        return (empty($fidLicenceData['link'])) ? 'n' : 'y';
    }

    public function hasDoi()
    {
        $fidLicenceData = $this->getFidLink('FidRomDoi');
        return (empty($fidLicenceData['link'])) ? 'n' : 'y';
    }

    public function hasFulltext()
    {
        $fidLicenceData = $this->getFidLink('FidRomFulltext');
        return (empty($fidLicenceData['link'])) ? 'n' : 'y';
    }

    public function getFidLink($solrMarcKey)
    {
        $link = $description = '';
        $marcData = $this->getMarcData($solrMarcKey);
        if (!empty($marcData[0]['link'])) {
            $link = $marcData[0]['link']['data'][0];
            $description = $marcData[0]['description']['data'][0];
        }
        return ['link' => $link, 'description' => $description];
    }
}
