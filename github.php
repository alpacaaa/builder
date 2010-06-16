<?php

	require_once('../../symphony/lib/toolkit/class.gateway.php');
	require_once('../../symphony/lib/toolkit/class.xmlelement.php');
	require_once('../../symphony/lib/toolkit/class.extension.php');
	
	require_once('extension.driver.php');
    
    $ch = new Gateway;
    $query = $_GET['user']. '/'. $_GET['repo']. '/';
    
    $ch->init();
    $ch->setopt('URL', 'http://github.com/api/v2/json/repos/show/'. $query. 'tags');
    $tags = json_decode($ch->exec(), true);
    
    $ch->setopt('URL', 'http://github.com/api/v2/json/repos/show/'. $query. 'branches');
    $branches = json_decode($ch->exec(), true);
    
    krsort($tags['tags']); krsort($branches['branches']);
    $result = array_merge($tags, $branches);
    $xml = new XMLElement('github-info');
    extension_builder::addToNode($result, $xml);
    
    
    echo $xml->generate();
