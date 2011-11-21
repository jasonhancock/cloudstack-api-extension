This repository contains code related to how I extended the CloudStack API.

LICENSE:
--------
Copyright (C) 2011 Jason Hancock http://geek.jasonhancock.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see http://www.gnu.org/licenses/.

DESCRIPTION:
------------
I needed a couple of additional commands added to the CloudStack API for a proof
of concept private cloud I had been working with. I needed the ability to read
the userdata associated with a virtual machine instance via the API. 
Additionally, I wanted a way to launch a VM without having a specify a zoneid,
templateid, offering id, etc., but have it bundled up and associated with a
name. For example, if I want to launch a new machine of type 'foo', I don't
to have to specify zoneid, offering id, etc., I just want to launch a
bundle named 'foo' and have it deploy a machine with whatever 'foo' maps
to.

A total of three commands have been added to the API:

 * getUserData(id) - returns the userdata associated with the given instance id
 * listBundles() - returns a list of the bundles offered
 * deployBundle(bundle) - Launches a bundle of the specified name 

It is important to note that I'm not doing any fancy account->VM permissions
checking. This means that anyone with a valid API key/secret combo can read
any userdata for a VM, even one not belonging to them, so don't store anything
sensitive in the userdata. I am checking the signature of each request, so you
must have both a valid API key and a valid API secret.

DEPENDENCIES:
-------------
Because my API is making a call to the deployVirtualMachines command, I require
that the CloudStack php client (found at https://github.com/jasonhancock/cloudstack-php-client)
is present.

HOW IT WORKS:
-------------
I'm not a much of a java programmer and didn't feel like investing the time
necessary to figure out how to extend the API natively. So, I did the next
best thing and came up with a hack that allows me to extend the API in
whatever language I want. In this case, I did it with PHP. 

Using apache, I'm proxying through all API requests to the real CloudStack API.
I'm using some rewrite rules to watch the query string for the three commands
that I've listed above and if I find them, I rewrite the url to a local php 
script instead of proxying it though to the API backend. Here's an example
apache configuration:
    RewriteEngine On
    
    # Intercept requests for certain commands and process them ourselves
    RewriteCond %{REQUEST_URI} /client/api
    RewriteCond %{QUERY_STRING} command=deployBundle
    RewriteRule .* /api.php [L]
    
    RewriteCond %{REQUEST_URI} /client/api
    RewriteCond %{QUERY_STRING} command=getUserData
    RewriteRule .* /api.php [L]
    
    RewriteCond %{REQUEST_URI} /client/api
    RewriteCond %{QUERY_STRING} command=listBundles
    RewriteRule .* /api.php [L]
    
    # Proxy everything else through to the real backend
    RewriteCond %{REQUEST_URI} /client/api
    RewriteRule ^/(.+)$ http://localhost:8080/$1 [P,L]

I'm running this apache instance on the same box as my cloud manager so that
api.php has access to the db.properties file. Because the db.properties file
is owned by root.cloud and has permissions 0640, I added the apache user to
the cloud group and then restarted apache. This is necessary for api.php to
have read access to the db.properties file.

The magic all happens in api.php. When the ExtendedCloudAPI object is
instantiated, it gets a few arguments:

* db_file: The path to the db.properties file used by cloudstack. This is necessary 
for accessing the MySQL database. Defaults to /etc/cloud/management/db.properties
* bundle_file: The path to the config file defining the bundle offerings. Defaults to
bundles.php in your current working dir
*  api_endpoint: The path to the real API endpoint. Defaults to http://localhost:8080/client/api

BUNDLE FILE:
------------
An example bundle file might look like:
    <?php
    
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

You will need to update the ID's to reflect valid values for your environment.

HOW TO USE:
-----------
Once this is all set up, simply point at the apache instance instead of
CloudStack's native API endpoint. For example, if you're using the
cloudstack-php-client, instead of this:

    $cloudstack = new CloudStackClient(
        'http://cloudmanager.example.com:8080/client/api',
        'API_KEY',
        'SECRET_KEY'
    );

You might have this (assuming apache is running on port 80 of the same box
as your cloud management server):

    $cloudstack = new CloudStackClient(
        'http://cloudmanager.example.com/client/api',
        'API_KEY',
        'SECRET_KEY'
    );

