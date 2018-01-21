<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Address visualization with Google Maps',
    'description' => 'With the extension st_address_map you are able to show addresses out of tt_address in Google Maps. Further it is possible to show the addresses in a list.',
    'category' => 'plugin',
    'version' => '7.6.0',
    'state' => 'stable',
    'clearcacheonload' => true,
    'author' => 'Thomas Scheibitz',
    'author_email' => 'mail@kreativschmiede-eichsfeld.de',
    'author_company' => 'Kreativschmiede Eichsfeld',
    'constraints' =>
        [
            'depends' =>
                [
                    'typo3' => '7.6.0-8.7.99',
                    'tt_address' => '2.3.5-9.9.9',
                ],
            'conflicts' =>
                [],
            'suggests' =>
                [],
        ],
];

