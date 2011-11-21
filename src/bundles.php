<?php

/*
 * This file is part of the cloudstack-api-extension located at
 * https://github.com/jasonhancock/cloudstack-api-extension
 * 
 * Copyright (C) 2011 Jason Hancock http://geek.jasonhancock.com
 *
 * You will need to modify the id's below to suit your environment.
 */
return array(
    array(
        'name'         => 'base_el5',
        'templateid'   => '214',
        'zoneid'       => '2',
        'offeringid'   => '2',
        'diskoffering' => '',
        'userdata'     => base64_encode("role=base\n"),
    ),
    array(
        'name'         => 'base_el6',
        'templateid'   => '217',
        'zoneid'       => '2',
        'offeringid'   => '2',
        'diskoffering' => '',
        'userdata'     => base64_encode("role=base\n"),
    ),
    array(
        'name'         => 'foo',
        'templateid'   => '218',
        'zoneid'       => '2',
        'offeringid'   => '10',
        'diskoffering' => '',
        'userdata'     => base64_encode("role=foo\n"),
    )
);
           
        
