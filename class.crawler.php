<?php

/*
	PHP Web Crawler
	by Jack Riales
	
	Recursively downloads information from a given website.
*/

class Crawler {
	
	// === CLASS PROPERTIES AND CONSTRUCTION ===
	
	// Initial link for crawler to start at
	protected $url;
	
	// Crawl depth. Checks recursion.
	protected $depth;	
	
	// Host of the URL. Ensures that the crawler stays on the same host.
	protected $host;
	
	// Flag tells if the crawler needs to use HTTP authentication or not.
	protected $auth = false;
	
	// Auth user
	protected $user;
	
	// Auth pass
	protected $pass;
	
	// Processed information
	protected $info = array();
	
	// Processed links
	protected $seen = array();
	
	// Filtered directories. Crawler avoids any interaction with these directories.
	protected $filter = array();
	
	// Filtered file types. Crawler does not download these file types.
	// Most importantly should contain media file types so the thing doesn't run like ass.
	protected $file_type_filter = array(".jpg", ".JPG", ".png", ".PNG", ".gif", ".mp4", ".avi", ".zip", ".rar", ".pdf", ".PDF");
	
	// Echo debug information when needed. *Set to false later.
	protected $debug = false;
	
	// Constructor
	public function __construct($url, $depth = 5) {
		$this->url = $url;
		$this->depth = $depth;
		$host_parse = parse_url($url);
		$this->host = $host_parse['host'];
		if ($this->debug) { echo ("<br>Crawler generated.<br>URL: ".$this->url."<br>Host is ".$this->host."."); }
	}
	
	// === PROTECTED INTERNAL FUNCTIONS ===
	
	// Get content (src, return codes, etc) from the URL
	protected function get_content($url) {		
		// Trim newlines
		$url = trim($url);
		
		// Begin a curl session on the URL
		$ch = curl_init($url);
		
		// If using HTTP Auth, set the parameters to access the content
		if ($this->auth) {
		 	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
         curl_setopt($ch, CURLOPT_USERPWD, $this->user . ":" . $this->pass);
		}
		
		// Make sure we get the content as a string
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		
		// Start getting content
		$response 	= curl_exec($ch);											// Get the HTML or whatever is returned
		$time			= curl_getinfo($ch, CURLINFO_TOTAL_TIME);			// Get the reponse time
		$code			= curl_getinfo($ch, CURLINFO_HTTP_CODE);			// Get any code we get, like a 404

		if ($response === false) {
			echo "cURL Error: " . curl_error($ch);
		}
		
		// Close the curl session
		curl_close($ch);
		
		// Return an array of the information gathered
		return array($url, $response, $time, $code);
	}
	
	// Obtains links from a given DOM document using anchor tags.
	// Also ensures that said links are uniformly formatted.
	// i.e. http://www.host.com/anchor
	protected function process_anchors($content, $url, $depth) {			
		// Begin a new DOM document
		$dom = new DOMDocument('1.0');
		
		// Load the HTML content for parsing
		$dom->loadHTML($content);
		
		// Get all anchors out of it
		$anchors = $dom->getElementsByTagName('a');
		
		// Iterate through the anchors
		foreach($anchors as $a) {
			// Get the defined href path
			$href = $a->getAttribute('href');
			
			// If the protocol ('http') doesn't exist, we need to normalize the link
			if (0 !== strpos($href, 'http')) {
				
				// Get the path info
				$path = '/' . ltrim($href, '/');
				
				// If we have the HTTP extension, just build the link
				if (extension_loaded('http')) {
                $href = http_build_url($url, array('path' => $path));
            
            // Otherwise, manual creation must be done
            } else {
					$parts = parse_url($url);
               $href = $parts['scheme'] . '://';
				  	if (isset($parts['user']) && isset($parts['pass'])) {
						$href .= $parts['user'] . ':' . $parts['pass'] . '@';
				  	}
				  	$href .= $parts['host'];
				  	if (isset($parts['port'])) {
						$href .= ':' . $parts['port'];
				  	}
				  	$href .= $path;
            }
			}
			
			// Recursively call the crawl function on the finalized link.
			// Decrement depth.
         $this->crawl($href, $depth - 1);
		}
	}
	
	// Function determines if a new link should be validated, allowing it to be processed
	// Returns: boolean
	protected function validate_url($url, $depth) {		
		// Check to see if the hosts are the same by checking string position
		if (strpos($url, $this->host) === false) {
			if ($this->debug) { print("Hosts are not the same.<br>"); }
			return false;
		}		
		
		// If depth is zero, crawl recursion stops and no links can be validated
		if ($depth === 0) {
			if ($this->debug) { print("Depth is zero.<br>"); }
			return false;
		}		
		
		// If a URL has already been processed, it must not be validated again
		if (isset($this->seen[$url])) {
			if ($this->debug) { print("URL already processed.<br>"); }
			return false;
		}
		
		// Check the filters. If the URL exists in any filter, it must not be validated.
		foreach($this->filter as $exclude) {
			// Check string position for the filter path. If it's there, no validation.
			if (strpos($url, $exclude) !== false) {
				if ($this->debug) { print("Didnt validate on filter exclusion.<br>"); }
				return false;
			}
		}
		
		// Check the file types. Any URL containing the filtered file types must not be validated.
		foreach ($this->file_type_filter as $exclude) {
			// Once again, check the string position for the type.
			if (strpos($url, $exclude) !== false) {
				if ($this->debug) { print("Didnt validate on file type exclusion.<br>"); }
				return false;
			}
		}
		
		// If all checks do not return false, the link is validated.
		return true;
	}
	
	// Validates and crawls the given url
	protected function crawl($url, $depth) {
		
		// Check for crawl validation
		if (!$this->validate_url($url, $depth)) {
			if($this->debug) { echo $url." not validated.<br><br>"; }
			return;
		}
		
		// If validated, add this link to the list of processed links
		$this->seen[$url] = true;
		
		// Add the links content and info to the info list
		list($c_url, $content, $httpcode, $time) = $this->get_content($url);
		$this->info[] = array($c_url, $content, $httpcode, $time);
		
		// Debug print the info
		//if ($this->debug) {
			$this->print_info($c_url, $depth, $httpcode, $time);
		//}
		
		// Process its subpages
		$this->process_anchors($content, $url, $depth);
	}
	
	// === PUBLIC FUNCTIONS ===
	
	// Must be used in the case that a website requires HTTP authentication
	public function set_authentication($user, $pass) {
		$this->auth = true;
		$this->user = $user;
		$this->pass = $pass;
	}
	
	// Used to add filters to the crawler
	public function add_filter($path) {
		if (!in_array($path, $this->filter)) {
			$this->filter[] = $path;
		} else {
			if ($this->debug) {
				echo "Path exists in filter.";
			}
		}
	}
	
	// Used to add filters to the crawler
	public function add_file_type_filter($type) {
		if (!in_array($type, $this->file_type_filter)) {
			$this->file_type_filter[] = $type;
		} else {
			if ($this->debug) {
				echo "Type exists in filter.";
			}
		}
	}
	
	// Starts the crawler on the initial URL
	public function run() {
		if ($this->debug) {
			echo '<br><br>Running crawler on '.$this->url.'<br>';
		}
		$this->crawl($this->url, $this->depth);
	}
	
	// === ACCESSORS ===
	
	// Accesses the information array
	public function get_info_array() {
		return $this->info;
	}
	
	// === DEBUG FUNCTIONS ===
	
	protected function print_info($url, $depth, $httpcode, $time) {
		ob_end_flush();
     	$currentDepth = $this->depth - $depth;
     	$count = count($this->seen);
     	echo "N::$count,CODE::$httpcode,TIME::$time,DEPTH::$currentDepth URL::$url <br>";
     	ob_start();
     	flush();
	}
}

?>