<?php 
    // getphotos.php
    // Author : Alex McWhae
    // Date : 1/10/18
    // Searches for photos on that are hosted in a s3 bucket by connecting to a rds instance running in the amazon cloud. 
    // Allows the user to search for photos based on title, keywords and/or dates
    $dirFilePath = dirname(dirname(__FILE__));
    require $dirFilePath.'/aws/aws-autoloader.php';
    use Aws\S3\S3Client;
    use Aws\S3\Exception\S3Exception;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="description" content="Photo album website">
    <meta name="author" content="Alex McWhae"/>
    <title> Photo Search </title>
</head>
<body>
    <h1> Photo Search </h1>
        <form> 
            <label for="keyword"> Keyword: </label>
            <input type="text" id="keyword" name="keyword"/>
            <label for="title"> Title: </label>
            <input type="text" id="title" name="title"/>
            <label> Date Range: </label>
            <input type="date" id="date1" name="date1"/>
            <span> - </span>
            <input type="date" id="date2" name="date2"/>
            <input type="submit" name="search" value="Search"/>
        </form>
        <hr/>

<?php   
    // search results go here
    if (isset($_GET["search"]))
    {
        $searchedString = "Searched parameters";
        $keywordSet = false;
        $titleSet = false;
        $date1Set = false;
        $date2Set = false;
        $date1 = "";
        $date1 = "";

        // checks that each field is set and if so, saves the field to the corresponding variable and adds its contents to the searched parameters string
        if (isset($_GET["date1"]) && $_GET["date1"] != "")
        {
            $date1Set = true;
            $date1 = $_GET["date1"];
            $searchedString .= ", date1: $date1";
        }
        if (isset($_GET["keyword"]) && $_GET["keyword"] != "")
        {
            $keywordSet = true;
            $keyword = $_GET["keyword"];
            $searchedString .=", keyword: $keyword";
        }
        if (isset($_GET["title"]) && $_GET["title"] != "")
        {
            $titleSet = true;
            $title = $_GET["title"];
            $searchedString .= ", title: $title";
        }

        if (isset($_GET["date2"]) && $_GET["date2"] != "")
        {
            $date2Set = true;
            $date2 = $_GET["date2"];
            $searchedString .= ", date2: $date2";
        }

        // builds up the sql query based on what fields are completed allowing to search by both TITLE and date ect
        if ($date1Set)
            $query = "SELECT * FROM photos WHERE date > '$date1'";
        else 
            $query = "SELECT * FROM photos";
        if ($date2Set)
            $query = "SELECT * FROM ($query) as alias1 WHERE date < '$date2'";
        else 
            $query = "SELECT * FROM ($query) as alias1";
        if ($titleSet)
            $query = "SELECT * FROM ($query) as alias2 WHERE title LIKE '%$title%'";
        else
            $query = "SELECT * FROM ($query) as alias2";
        if ($keywordSet)
            $query = "SELECT * FROM ($query) as p INNER JOIN keywords k ON k.photoId = p.photoId WHERE k.keyword = '$keyword'";
        
        // get database login details
		include 'dbSettings.php';
        // connects to the database
        $conn = mysqli_connect($host, $user, $password, $db);

        // function that returns all the keywords belonging to a photo into an array, taking the id of the photo
        function getKeywords($photoId, $conn)
        {
            $keywords = array();
            // makes the query and executes it
            $query = "SELECT * FROM keywords WHERE photoId = $photoId";
            $result = mysqli_query($conn, $query);
            // gets the row 
            $row = mysqli_fetch_assoc($result);
            while ($row)
            {   
                $rowKeyword = $row["keyword"];
                // adds the key word to the keywords array
                $keywords[] = $rowKeyword;
                $row = mysqli_fetch_assoc($result);
            }
            // returns array
            return $keywords;
        }

        // a function to print out all the details of the photo given the row from the photos table
        function photoDetails($row, $conn)
        {
            // gets the meta data from the row
            $title = $row["title"];
            $description = $row["description"];
            $date = $row["date"];
            // print out the meta data
            echo "<strong> Title: </strong>$title<br/>";
            echo "<strong> Description: </strong>$description<br/>";
            echo "<strong> Date: </strong>$date<br/>";
            echo "<strong> Keywords: </strong>";

            // gets the keywords
            $keywords = getKeywords($row["photoId"], $conn);
            // prints each keyword
            for ($i = 0; $i < count($keywords); $i++)
            {
                echo $keywords[$i];
                if ($i != count($keywords) - 1)
                    echo ", ";
            }
        }

        // if any field is set, execute the database search
        if ($date1Set || $date2Set || $titleSet || $keywordSet)
        {
            // prints the searched string
            echo "$searchedString <br/>";
            
            $result = mysqli_query($conn, $query);
            $row = mysqli_fetch_assoc($result);
            // if row does not exist, no results found, displays message
            if (!$row)
            {
                echo "<p> There were no results for those parameters </p>";
            }
            // while results
            while ($row)
            {
                // gets the image reference from the database
                $reference = $row["reference"];
                // creates s3client object
				$s3_client = new S3Client([
					'version' => 'latest',
					'region' => 'ap-southeast-2'
                ]);	           
                // gets object
				try {
					$cmd = $s3_client->getCommand('GetObject', [
						'Bucket' => 'admcwhae',
						'Key'    => $reference
					]);
				}
				catch (S3Exception $e) {
					echo $e->getMessage().PHP_EOL;
                }        
                // creates signed url for 2 minutes
                $request = $s3_client->createPresignedRequest($cmd, '+2 minutes');
                $presignedUrl = (string) $request->getUri();  
                      

                $url = "http://d203f1q8uzwfwh.cloudfront.net/".$reference;

                // displays the image, max height of 400 pixels
                echo '<br/><img src="'.$url.'" alt="'.$reference.'" title="'.$row["title"].'" style="image-orientation: from-image;" height ="400"/><br/>';
                // displays the photo details
                photoDetails($row, $conn);
                $row = mysqli_fetch_assoc($result);
            }
         }
        mysqli_close($conn);
    }
?>
	<footer>
		<p>
            <hr/>
			<a href="upload.php"> Photo Upload </a>
			<br/>
			<a href="getphotos.php">Photo Search </a>
		</p>
	</footer>
</body>

</html>



