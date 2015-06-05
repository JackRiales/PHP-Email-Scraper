<?php
	/*
		PHP Email Parser
		by Jack Riales
		
		Effectively a web crawler script designed to obtain and print out email information parsed from a website's source code.
		
		Have fun.
	*/
	
	set_time_limit(0);
	
	// Include crawler class
	include("class.crawler.php");
	
	// ==========================
	// Parsing function
	// Scowers the given text for email addresses.
	// Formulates and returns an array with all of them.
	// ==========================
	function parse_emails($src) {
		// Array that will contain any found emails
		$matches = array();
		
		// Regex pattern for email formats
		$pattern = "/[a-z0-9_\-\+]+@[a-z0-9\-]+\.([a-z]{2,3})(?:\.[a-z]{2})?/i";
		
		// Strip HTML/PHP tags from the source and replace with spaces
		$text = preg_replace('/<[^>]*>/', ' ', $src);
		
		// Uncomment to test the regex replacement.
		# print_r($text);
		# print '<br>';
		
		// Get those matches
		preg_match_all($pattern, $text, $matches);
		
		// Uncomment to test matching.
		# print_r($matches);
		# print '<br>';
		
		// Set matches to equal only the first array of the regex return
		$matches = $matches[0];
		
		// Uncomment to print out the return value.
		# print_r($matches);
		
		// Return it
		return $matches;
	}
	
	// ==========================
	// Print function
	// Prints out table rows of found information from a given url
	// Works only with one URL
	// ==========================
	function print_site_emails($url, $depth, $file, $filename = "emails.csv") {
		$crawler = new Crawler($url, $depth);
		$crawler->run();
		$info = $crawler->get_info_array();
		$emails = array();
		$csv = null;
		$txt = "";
		foreach($info as $i) {
			$emails[] = array($i[0], parse_emails($i[1]));
		}
		
		if ($file) {
			echo "Printing information to file...";
			$csv = fopen($filename, "w") or die("Unable to open file stream");
			$txt .= "URL,email\n";
		}
		
		for($i = 0; $i < count($emails); $i++) {
			if (count($emails[$i][1]) > 0) {
				for($x = 0; $x < count($emails[$i][1]); $x++) {
					echo '<tr><td>'.$emails[$i][0].'</td><td>'.$emails[$i][1][$x].'</td></tr>';
					if ($file) {
						$txt .= $emails[$i][0].",".$emails[$i][1][$x]."\n";
					}
				}
			}
		}
		
		if ($file) {
			fwrite($csv, $txt);
			echo "Printed file.";
			fclose($csv);
			
			// Redirect to the file
			echo "<script type=\"text/javascript\">
						window.location.href = '".$filename."';
					</script>";
		}
	}
	
	// ==========================
	// Print function
	// Prints out table rows of found information from a given url
	// ==========================
	function print_multi_site_emails($urls, $depth, $file, $filename = "emails.csv") {
		
		// Instantiate objects 
		$emails = array();
		$csv = null;
		$txt = "";
		
		// Start output file, if needed
		if ($file) {
			echo "Printing information to file...";
			$csv = fopen($filename, "w") or die("Unable to open file stream");
			$txt .= "URL,email\n";
		}

		foreach($urls as $url) {
			// Generate a new crawler for the new site
			$crawler = new Crawler($url, $depth);
			
			// Run it
			$crawler->run();
			
			// Get the page content and info
			$info = $crawler->get_info_array();
			
			// Parse for emails
			foreach($info as $i) {
				$emails[] = array($i[0], parse_emails($i[1]));
			}
			
			// Write what was parsed
			for($i = 0; $i < count($emails); $i++) {
				if (count($emails[$i][1]) > 0) {
					for($x = 0; $x < count($emails[$i][1]); $x++) {
						echo '<tr><td>'.$emails[$i][0].'</td><td>'.$emails[$i][1][$x].'</td></tr>';
						if ($file) {
							$txt .= $emails[$i][0].",".$emails[$i][1][$x]."\n";
						}
					}
				}
			}
		}
		
		// Write output file
		if ($file) {
			fwrite($csv, $txt);
			echo "Printed file.";
			fclose($csv);
			
			// Redirect to the file
			echo "<script type=\"text/javascript\">
						window.location.href = '".$filename."';
					</script>";
		}
	}
?>

<!DOCTYPE html>
<html>
	<head>
		<title>PHP Email Parser</title>
		<link rel="stylesheet" href="style.css">
	</head>
	
	<body>
		<!-- USER INPUT FORM -->
		<?php if(!isset($_GET["url"])): ?>
		<!-- Form to put into $_GET['url'] -->
		<h1>PHP Email Retriever</h1>
		<p>Enter a URL (or a set of them), and you will receive any email text found in the website's source code.</p>
		<p>USE AT YOUR OWN RISK. I'M NOT RESPONSIBLE FOR WHO YOU PISS OFF WITH THIS.</p><br>
		
		<form action="" method="get">
			URLs (One per line): <textarea type="text" name="url" required></textarea><br>
			
			<button>GO!</button>
			Crawl Depth: <input type="number" name="depth" min="1" max="8" required><br>
			Output to file: <input type="checkbox" name="file"><br>
		</form>
		
		<!-- APP -->
		<?php else: ?>
		<table border="2">
			<tr>
				<td>URL</td>
				<td>Email found</td>
			</tr>
			<?php
				$links = explode("\n", $_GET['url']);
				print_multi_site_emails($links, $_GET['depth'], isset($_GET['file']));
			?>
		</table>
		<?php endif; ?>
	</body>
</html>