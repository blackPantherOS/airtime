<?php

/*
* Navigation container (config/array)
 
* Each element in the array will be passed to
* Zend_Navigation_Page::factory() when constructing
* the navigation container below.
*/
$pages = array(
	array(
        'label'      => 'Now Playing',
        'module'     => 'default',
        'controller' => 'Nowplaying',
        'action'     => 'index',
        'resource'	=>	'nowplaying'
	),
	array(
		'label'      => 'Schedule',
        'module'     => 'default',
        'controller' => 'Schedule',
        'action'     => 'index',
        'resource'	=>	'schedule'
	),
	array(
		'label'      => 'Playlist Builder',
		'module'     => 'default',
		'controller' => 'Library',
		'action'     => 'index',
		'resource'	=>	'library'
	),
	array(
		'label'      => 'Add Audio',
		'module'     => 'default',
		'controller' => 'Plupload',
		'action'     => 'plupload',
		'resource'	=>	'plupload'
	),
    array(
        'label'      => 'Configure',
        'uri' => 'javascript:void(null)',
        'resource' => 'preference',
        'pages'      => array(
            array(
                'label'      => 'Preferences',
                'module'     => 'default',
                'controller' => 'Preference'
            ),
            array(
                'label'      => 'Manage Users',
                'module'     => 'default',
                'controller' => 'user',
                'action'     => 'add-user',
                'resource'	=>	'user'	
            )
        )
    )
);

 
// Create container from array
$container = new Zend_Navigation($pages);
$container->id = "nav";
 
//store it in the registry:
Zend_Registry::set('Zend_Navigation', $container);
