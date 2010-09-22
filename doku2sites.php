<?php
/*
Copyright (C) 2010 Christophe Pierret.

   Doku2Sites is free software; you can redistribute it and/or
   modify it under the terms of the Cecill-B license version 1.

   Doku2Sites is distributed in the hope that it will be useful,
   but the licensor does not warrant that the Software is free 
   from any error, that it will operate without interruption, 
   that it will be compatible with the Licensee's own equipment 
   and software configuration, nor that it will meet the Licensee's 
   requirements. See the Cecill-B License for more details.

   You should have received a copy of the Cecill-B License
   along with Doku2Sites; if not, you can get it here:
   http://www.cecill.info/licences/Licence_CeCILL-B_V1-en.html
   
   The PHP XML RPC library, used by Doku2Sites, is not part of 
   Doku2Sites and is licensed under its own BSD-style license.
*/

require_once 'xmlrpc-2.2.2/lib/xmlrpc.inc';


$opts = getopt('u:p:h:a:m:d:s:?');
if ($opts==FALSE)
{
	print_help_message();
	exit(1);
}

$rpcurl = "";
$username = '';
$pwd ='';
$default_author = "Doku2Sites";
$default_author_email = "doku2sites@gmail.com";
$host = 'localhost';
$directory = ".";

// Handle command line arguments
foreach ($opts as $opt=>$value) switch ($opt) {
	case 'u':
    	$username = $value;
    	break;
	case 'p':
    	$pwd = $value;
    	break;
    case 'h':
    	if (empty($rpcurl))
    	{
    		$host = $value;
    		$rpcurl = 'https://'.$host.'/wiki/lib/exe/xmlrpc.php';
    	}
    	break;
    case 'd':
    	if (is_dir($value))
	    	$directory = $value;
	    else 
	    	die("'$value' is not a directory");
    	break;
    case 's':
    	if (!empty($value) && strlen($value)>4 && substr($value, 0,4)=="http")
    		$rpcurl = $value;
    	else
    		die("'$value' is not an url");
    	break;
    case 'a':
    	$default_author = $value;
    	break;
    case 'm':
    	$default_author_email = $value;
    	break;
    	
  case 'p':
    print_help_message();
    exit(1);
}
if (empty($rpcurl))
	$rpcurl = "https://localhost/wiki/lib/exe/xmlrpc.php";

echo "doku2sites will connect to DokuWiki with username: ".$username."\nAnd using URL:\n".$rpcurl."\n";
	
$tidy_config = array(
	'clean' => true,
	'output-xhtml' => true,
	'show-body-only' => true,
	'wrap' => 0,
	'quote-nbsp' => false,
);

function print_help_message()
{
	echo "php doku2sites.php -u DOKU_USERNAME -p DOKU_PASSWORD [-h DOKU_HOSTNAME] [-d DIRECTORY] [-s DOKU_URL] [-a AUTHOR] [-m AUTHOR_EMAIL] [-?]\n";
	echo "Options:\n";
	echo "  -u DOKU_USERNAME a DokuWiki username with access to the DokuWiki XMLRPC API.\n";
	echo "  -p DOKU_PASSWORD the DokuWiki password.\n";
	echo '  -h DOKU_HOSTNAME the DokuWiki host name ( as in https://${DOKU_HOSTNAME}/wiki/lib/exe/xmlrpc.php).'."\n";
	echo '  -s DOKU_URL the DokuWiki XML RPC URL, for example https://myserver.com/wiki/lib/exe/xmlrpc.php'."\n";
	echo '  -d DIRECTORY an empty directory in which files will be generated (ensure you rince it before use)'."\n";
	echo '  -a AUTHOR the author name that will appear in the footer of all documents.'."\n";
	echo '  -m AUTHOR_EMAIL the author email that will appear in the footer of all documents.'."\n";
	echo '  -? show this help message'."\n";
	echo "Purpose:\n";	
	echo "  doku2google is a migration tool to export the pages of a DokuWiki for import into Google Sites\n";	
	echo "\n";	
	echo "  You should ensure that the XML RPC api is enabled in DokuWiki before use.\n";	
	echo "  And download the Google Sites Liberation tool to import the result into Google Sites.\n";
	echo "  You can get it here: http://code.google.com/p/google-sites-liberation/\n";	
}

function normalize_page_title($title)
{
	return preg_replace('/[^a-zA-Z0-9_]/','_',$title);
}

function get_footer($author,$email)
{
	return '<small>Updated on <abbr class="updated" title="'
		. gmdate('Y-m-d\TH:i:s.000\Z')
		. '">'
		. gmdate('M. j, Y')
		. '</abbr> by <span class="author"><span class="vcard"><a class="fn" href="mailto:'
		. htmlspecialchars($email,ENT_QUOTES,'UTF-8')
		. '">'
		. htmlspecialchars($author,ENT_QUOTES,'UTF-8')
		. '</a></span></span> (Version <span class="sites:revision">4</span>)</small>';
}

function build_sites_page($subdir,$title,$html_content,$author,$email)
{
	$title = normalize_page_title($title);
    $full_page = "<html>\n\t<head>\n\t\t<title>".$title."</title>\n\t</head>\n\t<body>\n\t\t";
    $full_page .= '<div class="hentry webpage"';
    $full_page .= '><span class="entry-title">'.$title.'</span><div><div class="entry-content">';
    
    $full_page .= $html_content;
    
    $full_page .= '</div></div>';
    $full_page .= get_footer($author,$email);
    $full_page .= "\n\t\t</div>\n\t</body>\n</html>";
    file_put_contents($subdir .'/index.html',$full_page);
	
}

function new_rpc_client($rpcurl,$username,$pwd)
{
	$client = new xmlrpc_client($rpcurl);
	$client->setCredentials($username,$pwd,CURLAUTH_BASIC);
	$client->return_type = "phpvals";
	return $client;
}

function get_all_pages($client)
{
	$message = new xmlrpcmsg("wiki.getAllPages", array());
    $resp = $client->send($message);
    if ($resp->faultCode()) {

        echo "Error talking to dokuwiki: ".$resp->faultString()."\n";
        echo "Check that the xmlrpc API access is enabled in the admin interface of DokuWiki.\n";
        exit(1);

    }
    return $resp->value();
}

function get_page_html($client,$id)
{
	$message = new xmlrpcmsg("wiki.getPageHTML", array(new xmlrpcval($id, 'string')));
    $resp = $client->send($message);
    if ($resp->faultCode()) {

        echo "Error talking to dokuwiki: ".$resp->faultString()."\n";
        exit(1);

    }
	return $resp->value();
}

function preprocess_page_html($page)
{
	global $tidy_config;
	// transform doku hrefs
	$result = preg_replace('#href="/wiki/doku.php\?id=([^"&]+)(&[^"]+)?"#','href="\1/index.html"',$page);
	// removes : from hrefs
    $matches = array();
    preg_match_all('#href="[^"]+"#',$result,$matches,PREG_OFFSET_CAPTURE);
    foreach($matches[0] as $key=>$m)
    {
    	$l = strlen($m[0]);
    	$repl = str_replace(':','/',$m[0]);
    	$offset = $m[1];
    	$result = substr_replace($result,$repl,$offset,$l);
    }
        
    $fragment = html_entity_decode(utf8_encode($result));
	$tidy = tidy_parse_string($fragment, $tidy_config, 'UTF8');
	$tidy->cleanRepair();
	$result = tidy_get_output($tidy);
	// fix quirk in Tidy (preserves PHP code)
	$result = str_replace('<?php','&lt;?php',$result);
	$result = str_replace('?>','?&gt;',$result);
	$result = str_replace('>&<','>&amp;<',$result);
	return $result;
}
/**
 * 
 * Make Google Liberation tool directories
 * @param string $basedir
 * @param string $id
 * @return default title
 */
function make_dirs($basedir,$id)
{
		$dirs= preg_split("/:/",$id);
	    $curdir = $basedir;
	    $jdir = $dirs[0];
	    foreach($dirs as $d)
	    {
	    	if ($d != $jdir)
	    		$jdir .= ':' . $d;
	    	$curdir .= '/';
	    	$curdir .= normalize_page_title($d);
	    	if (!is_dir($curdir))
	    	{
		    	mkdir($curdir);
	    	}
	    }
	    return end($dirs);
}

function get_subpages($path, $exclude = ".|..") 
{
	$path = rtrim($path, "/") . "/";
	$folder_handle = opendir($path);
	$exclude_array = explode("|", $exclude);
	$result = array();
	while(false !== ($filename = readdir($folder_handle))) {
		if(!in_array(strtolower($filename), $exclude_array)) {
			if(file_exists($path . $filename . "/index.html")) {
				$result[] = $filename;
			} 
		}
	}
	return $result;
}

function make_indexes($basedir, $subdir, $author, $email, $exclude = array(".","..")) 
{
	if (empty($subdir))
		$curdir = $basedir . '/';
	else 
		$curdir = $basedir . '/' . $subdir . '/';
	$folder_handle = opendir($curdir);
	$result = array();
	while(false !== ($filename = readdir($folder_handle))) {
		if(!in_array($filename, $exclude)) {
			if (empty($subdir))
				$new_subdir = $filename;
			else
				$new_subdir = $subdir . '/'. $filename;
			if(is_dir($basedir.'/'.$new_subdir)) {
				make_indexes($basedir, $new_subdir,$author, $email, $exclude);
				if (!file_exists($curdir . $filename . "/index.html"))
				{
					make_index($basedir,$subdir,$filename,$author,$email);
				}
			}
		}
	}
	return $result;
}
function make_index($basedir,$subdir,$filename,$author,$email)
{
    $curdir = $basedir . '/' . $subdir . '/'. $filename;
	if (!file_exists($curdir .'/index.html'))
    {
    	$subpages = get_subpages($curdir);
    	$content = "<ul>\n";
    	foreach($subpages as $subpage)
    	{
    		$content .= '<li><a href="'.$filename.'/'.$subpage.'">'.normalize_page_title($subpage)."</a></li>\n";	
    	}
    	$content .= "</ul>\n";
    	build_sites_page($curdir,$filename,$content,$author,$email);
    }
}

	

$client = new_rpc_client($rpcurl,$username,$pwd);

$all_pages = get_all_pages($client);
$ids = array();
foreach($all_pages as $key=>$data)
{
	//echo $data['id']." - " .$data['perms']." - " .$data['size']." - " .$data['lastModified']."\n";
	$id = $data['id'];
	$page = get_page_html($client,$id);

	$content = preprocess_page_html($page);

	$title = normalize_page_title(make_dirs($directory,$id));
	$ids[$id] = $title;
	$fname = str_replace(':','/',$id);
	$fname = preg_replace('#[^a-zA-Z0-9_/]#','_',$fname);
	$subdir = $directory.'/'. $fname;

	build_sites_page($subdir, $title, $content, $default_author, $default_author_email);
}
make_indexes($directory, "", $default_author, $default_author_email);

?>