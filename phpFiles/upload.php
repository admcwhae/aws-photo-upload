<?php 
    // upload.php
    // Author : Alex McWhae
    // Date : 1/10/18
    // Allows a user to upload photos to an S3 bucket. 
	$dirFilePath = dirname(dirname(__FILE__));
	require $dirFilePath.'/aws/aws-autoloader.php';
	use Aws\S3\S3Client;
	use Aws\S3\MultipartUploader;
	use Aws\Common\Exception\S3Exception;
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="description" content="Photo uploaders">
	<meta name="author" content="Alex McWhae">
	<link rel="stylesheet" type="text/css" href="form.css"/>
	<title>Photo Album</title>
</head>
<body>
	<header>
		<h1>Photo uploader</h1>
		<p><strong>Student ID: 101801822</strong></p>
		<p><strong>Name: Alex McWhae</strong></p>
	</header>
	
	<fieldset>
	<form method="post" enctype="multipart/form-data">
		<p><strong>
			<label for="photoTitle"> Photo title: </label>
			<input type="text" name="photoTitle" id="photoTitle"/>
		</strong></p>
		<p><strong>
			<label for="photoFile"> Select a photo: </label>
			<input type="file" name="photoFile" id="photoFile"/>
		</strong></p>
		<p><strong>
			<label for="photoDesc">Description: </label>
			<input type="text" name="photoDesc" id="photoDesc"/>
		</strong></p>
		<p><strong>
			<label for="photoDate">Date:</label>
			<input type="date" name="photoDate" id="photoDate"/>	
		</strong></p>	
		<p><strong>
			Keywords (seperated by a semicolon, e.h. keyword1; keyword2; etc.):<br/>
			<input type="text" name="keywords" id="keywords"/>
		</strong></p>
		<p>
			<input type="submit" id="upload" name="upload" value="Upload"/>
		</p>
	</form>

	<?php
		if (isset($_POST["upload"]))
		{
			$title = $_POST["photoTitle"];
			$description = $_POST["photoDesc"];
			$date = $_POST["photoDate"];
			$keywords = $_POST["keywords"];

			if ($title == '' || $description== '' || $date == '' || $keywords == '')
			{
				echo "<p>Please fill in all fields</p>";
			}
			else {
				// creates S3 client
				$s3_client = new S3Client([
					'version' => 'latest',
					'region' => 'ap-southeast-2'
				]);
				// gets the temporary directory of the file on webserver
				$target_file = dirname(__FILE__).'/uploads/'.basename($_FILES["photoFile"]["name"]);
				$file_name = $_FILES["photoFile"]["name"];

				// used to check if upload is valid
				$uploadOk = true;

				// checks if file is an image
				$checkImage = getimagesize($_FILES["photoFile"]["tmp_name"]);
				if ($checkImage !== false) {
				}
				else {
					echo "File was not an image. Please select an image to upload.";
					$uploadOk = false;
				}

				if ($uploadOk)
				{
					// moves file to webserver
					if (move_uploaded_file($_FILES["photoFile"]["tmp_name"], $target_file)) {
						echo "File uploaded!";
						// creates multipart uploader with bucket name and file name
						$uploader = new MultipartUploader(
							$s3_client,
							$target_file,
							['bucket' => 'admcwhae', 'key' => $file_name]
						);
						try {
							// uploads file
							$result = $uploader->upload();
							
							// get database connectivity information
							include 'dbSettings.php';

							$conn = mysqli_connect($host, $user, $password, $db);
							// inserts meta data into database
							$query = "INSERT INTO photos (title, description, date, reference) VALUES ('$title', '$description', '$date', '$file_name')"; 
							mysqli_query($conn, $query);
							
							// gets photoid
							$photoId = mysqli_insert_id($conn);
							// converts keywords into array
							$keywordsArray = explode(";",$keywords);
							for ($i = 0; $i < count($keywordsArray); $i++) {
								// inserts keywords
								$query = "INSERT INTO keywords (photoId, keyword) VALUES ('$photoId', '$keywordsArray[$i]')";
								mysqli_query($conn, $query);
							}
							mysqli_close($conn);						
							// deletes temporary file
							unlink($target_file);
						}
						catch (S3Exception $e) {
						}
					}
					else 
						echo '<p>Something went wrong uploading file, please try again.</p>';
					
				}
			}
		}
	?>

	</fieldset>
	
	<footer>
		<p>
			<a href="upload.php"> Photo Upload </a>
			<br/>
			<a href="getphotos.php">Photo Search </a>
		</p>
	</footer>

</body>
</html> 