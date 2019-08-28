<?php

/**
 * Recieves image via POST and saves it to the server
 */

require __DIR__ . '/../../bootstrap.php';

function uploadImage() {
  $picture_dir = conf('picture.tmp_dir');
  $targetFile = conf('picture.tmp_url_template');
  $imageFile = $_FILES["image"];
  $isImage = getimagesize($imageFile["tmp_name"]);

  if (!$imageFile) {
    http_response_code(400);
    echo json_encode(array("message" => "No image file"));
  }
  else if (!$isImage) {
    http_response_code(400);
    echo json_encode(array("message" => "Incorrect file format"));
  }
  else {
    // Generate random name for image
    $imageName = md5(microtime() . '.' .  mt_rand()) . '.jpg';
    $imagePath = $picture_dir . $imageName;
    if (move_uploaded_file($imageFile["tmp_name"], $imagePath)) {
      $data = array();
      $data["imageUrl"] = sprintf($targetFile, $imageName);

      http_response_code(201);
      header('Location: ' . $data["imageUrl"]);
      echo json_encode($data);
    }
    else {
      // Could not move uploaded image
      http_response_code(500);
    }
  }
}

try {
  if (request_method() != 'post') {
    http_response_code(405);
    echo json_encode(array("message" => "Only POST allowed."));
  }
  else {
    uploadImage();
  }
} catch (Http_error $e) {
  http_error($e->status_code, $e->msg);
}