<?php
namespace Resolver\Module\Configuration;

$config = [
    'vufind' => [
        'plugin_managers' => [
            'ajaxhandler' => [
                'factories' => [
                    'Resolver\AjaxHandler\GetResolverLinks' =>
                        'Resolver\AjaxHandler\GetResolverLinksFactory',
                ],
                'aliases' => [
                    'getResolverLinks' => 'Resolver\AjaxHandler\GetResolverLinks',
                ]
            ],
            'recorddriver' => [
                'factories' => [
                    \Resolver\RecordDriver\SolrMarc::class => \Resolver\RecordDriver\SolrDefaultFactory::class,
                ],
                'aliases' => [
                    'solrmarc' => \Resolver\RecordDriver\SolrMarc::class,
                ]
            ],
            'resolver_driver' => [
                'factories' => [
                    'Resolver\Resolver\Driver\KVK' =>
                        'VuFind\Resolver\Driver\AbstractBaseFactory',
                    'Resolver\Resolver\Driver\Ezb' =>
                        \VuFind\Resolver\Driver\EzbFactory::class,
                    'Resolver\Resolver\Driver\HBZ' =>
                        'VuFind\Resolver\Driver\AbstractBaseFactory',
                    'Resolver\Resolver\Driver\MARC' =>
                        'Resolver\Resolver\Driver\DriverWithRecordDriverFactory',
                ],
                'aliases' => [
                    'kvk' => 'Resolver\Resolver\Driver\KVK',
                    'ezb' => 'Resolver\Resolver\Driver\Ezb',
                    'VuFind\Resolver\Driver\Ezb' => 
                        'Resolver\Resolver\Driver\Ezb',
                    'hbz' => 'Resolver\Resolver\Driver\HBZ',
                    'marc' => 'Resolver\Resolver\Driver\MARC',
                ]
            ],
        ],
    ],
];

return $config;

