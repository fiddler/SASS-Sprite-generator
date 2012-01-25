<?php
/**
* Copyright (c) 2011 Jussi Kaijalainen
* 
* Permission is hereby granted, free of charge, to any person obtaining
* a copy of this software and associated documentation files (the
* "Software"), to deal in the Software without restriction, including
* without limitation the rights to use, copy, modify, merge, publish,
* distribute, sublicense, and/or sell copies of the Software, and to
* permit persons to whom the Software is furnished to do so, subject to
* the following conditions:
* 
* The above copyright notice and this permission notice shall be
* included in all copies or substantial portions of the Software.
* 
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
* EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
* MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
* NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
* LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
* OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
* WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

/** 
* SASS Sprite Generator
* Takes a folder of images and combines them into one sprite and generates SASS-mixins containing bg-positions
* & proper dimensions for the sprite. Places the sprite image and mixin file to preset locations. Optionally
* uses pngcrush to reduce file size.
*/

// Source image folder
$path = '/var/www/images/sprite_images';

// Internet path
$www_path = '/images/';

// Sprite image name
$sprite_name = 'sass_sprite';

// Location for the generated sprite image
$final_sprite_location = '/var/www/images/';

// Location and name for the generated sass mixin file
$markup_mixin_file = '/var/www/css/_sprite.scss';

// Accepted filetypes
$filetypes = array('png','gif','jpg','jpeg');

// Padding between images
$padding = 2;

// Add random string to image name in generated scss (eg. sprite.png?v=42)
$random_str = '?v='.rand(0,999);

// Should png crush be used
$use_png_crush = true;

// ---------- END CONFIG ----------

// Read source folder into an array 
$images = array();
if( $dir_handle = opendir($path) ) {
    while( ( $file = readdir($dir_handle) ) !== false ){
        $ext = strtolower( substr( $file, strrpos($file, '.') + 1 ) );
        if( in_array($ext, $filetypes) && $file != $sprite_name.'.png' && $file != $sprite_name.'_crushed.png' ){
            $images[$file] = getimagesize($path .'/'. $file);
        }
    }
    
    closedir($dir_handle);
    unset($dir_handle, $file, $ext);
}

if(count($images) === 0) die("No images found\n");

// Loop trough images, create markup and create array with positioned images
$x = 0; $y = 0; $markup = ''; $images_positioned = array();
foreach( $images as $file_name => $file_info ){
    $markup .= '@mixin sprite_'.removeExt($file_name).'(){'.
                'width:'.$file_info[0].'px;'.
                'height:'.$file_info[1].'px;'.
                'background:url("'.$www_path.$sprite_name.'.png'.$random_str.'") 0px -'.$y.'px no-repeat;'.
                '}'."\n";
    
    // Add image to current y coord
    $images_positioned[$file_name] = array( 'x' => 0, 'y' => $y, 'width' => $file_info[0], 'height' => $file_info[1], 'extension' => getExt($file_name) );
    
    // Increment y
    $y = $y + $file_info[1] + $padding;
    
    // Keep largest width
    if($file_info[0] > $x){
        $x = $file_info[0];
    }
}

echo "\nSaving scss markup...";
$markup_file = fopen($markup_mixin_file, 'w') or die('');
fwrite($markup_file, $markup);
fclose($markup_file);
echo " DONE.";

echo "\nCombining images...";
generate_sprite_png($images_positioned, $path, $x, $y, $sprite_name);
echo " DONE.";

if($use_png_crush){
    echo "\nCrushing png...";
    $cmd = 'pngcrush -rem alla -brute -reduce '.$path.'/'.$sprite_name.'.png '.$path.'/'.$sprite_name.'_crushed.png';
    $cmd .= ' && rm  '.$path.'/'.$sprite_name.'.png && mv '.$path.'/'.$sprite_name.'_crushed.png '.$path.'/'.$sprite_name.'.png';
    exec($cmd);
    echo " DONE.";
}

echo "\nMoving to final destination...";
$cmd2 = 'mv '.$path.'/'.$sprite_name.'.png '.$final_sprite_location.$sprite_name.'.png';
exec($cmd2);
echo " DONE.";

die("\n\nAll done.\n");


// Helper functions
function getExt($file_name){
    return strtolower( substr( $file_name, strrpos($file_name, '.') + 1 ) );
}

function removeExt($file_name){
    return substr( $file_name, 0, -strlen( strrchr($file_name, '.') ) );
}

function generate_sprite_png($imgs, $path, $x, $y, $sprite_name) {
  $image = imagecreatetruecolor($x, $y) or die("Gd problem");
  $transparency = imagecolorallocatealpha($image, 0, 0, 0, 127);
  imagealphablending($image, FALSE);
  imagefilledrectangle($image, 0, 0, $x, $y, $transparency);
  imagealphablending($image, TRUE);
  imagesavealpha($image, TRUE);

  // Overlay all images
  foreach ($imgs as $file => $img) {
    if (isset($img['extension'], $img['x'], $img['y'], $img['width'], $img['height'])) {
        $source = image_gd_open($path.'/'.$file, $img['extension']);
        imagecopyresampled($image, $source, $img['x'], $img['y'], 0, 0, $img['width'], $img['height'], $img['width'], $img['height']);
        imagedestroy($source);
    }
  }

  image_gd_close($image, $path.'/'.$sprite_name.'.png', 'png');
  imagedestroy($image);
}

function image_gd_open($file, $extension) {
  $extension = str_replace('jpg', 'jpeg', $extension);
  $open_func = 'imageCreateFrom'. $extension;
  if (!function_exists($open_func)) {
    return FALSE;
  }
  return $open_func($file);
}

function image_gd_close($res, $destination, $extension) {
  $extension = str_replace('jpg', 'jpeg', $extension);
  $close_func = 'image'. $extension;
  if (!function_exists($close_func)) {
    return FALSE;
  }
  if ($extension == 'jpeg') {
    return $close_func($res, $destination, 100);
  }
  else {
    return $close_func($res, $destination);
  }
}