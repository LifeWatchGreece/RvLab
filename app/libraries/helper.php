<?php

function dateToTimezone($date_string,$timezone){
    $mydate = new DateTime($date_string);
    $target_timezone = new DateTimeZone($timezone);
    $mydate->setTimeZone($target_timezone);
    return $mydate->format('d M Y H:i:s');
}

function form_radio_files($tooltip_id,$labelText,$tooltips,$workspace_files){
    
    $parts = explode('-',$tooltip_id);
    $input_id = $parts[1];
    
    $html =  "<div class='radio_wrapper'>
        <div class='configuration-label'>
            <div class='row'>
                <div class='col-sm-11'>
                    $labelText
                </div>
                ".fTooltip($tooltip_id,$tooltips)."
            </div>
        </div>";
    
        if(empty($workspace_files)){
            $html .= "<br><span style='color: red'>No files in your workspace!</span>";
        }
        foreach($workspace_files as $file){
            $html .= "<div class='radio'>
                        <label>
                          <input type='radio' name='$input_id' value='".$file->filename."'>
                          ".$file->filename."
                        </label>
                    </div>";
        }
    $html .= "</div>";        
    return $html;
}

function form_checkbox_files($tooltip_id,$labelText,$tooltips,$workspace_files){
    
    $parts = explode('-',$tooltip_id);
    $input_id = $parts[1];
    
    $html =  "<div class='radio_wrapper'>
        <div class='configuration-label'>
            <div class='row'>
                <div class='col-sm-11'>
                    $labelText
                </div>
                ".fTooltip($tooltip_id,$tooltips)."
            </div>
        </div>";
    
        if(empty($workspace_files)){
            $html .= "<br><span style='color: red'>No files in your workspace!</span>";
        }
        foreach($workspace_files as $file){
            $html .= "<div class='radio'>
                        <label>
                          <input type='checkbox' name='".$input_id."[]' value='".$file->filename."'>
                          ".$file->filename."
                        </label>
                    </div>";
        }
    $html .= "</div>";        
    return $html;
}

function form_dropdown($tooltip_id,$labelText,$options,$default,$tooltips){
    $parts = explode('-',$tooltip_id);;
    $input_id = $parts[1];
    
    $html = "<div class='select_wrapper'>
            <div class='row'>
                <div class='col-sm-11'>
                    <div class='configuration-label'>                    
                        $labelText
                    </div>
                    <select name='$input_id'>";
                        foreach($options as $option){
                            if($option == $default){
                                $html .= "<option selected='selected'>$option</option>";
                            } else {
                                $html .= "<option>$option</option>";
                            }
                        }
                $html .= "</select>
                </div>
                ".fTooltip($tooltip_id,$tooltips)."
            </div>            
        </div>";
                
    return $html;
}

function form_textinput($tooltip_id,$labelText,$default,$tooltips){
    $parts = explode('-',$tooltip_id);
    $input_id = $parts[1];
       
    $html = "<div class='textarea_wrapper'>
            <div class='row'>
                <div class='col-sm-11'>
                    <div class='configuration-label'>                    
                        $labelText
                    </div>
                    <input type='text' class='form-control' name='$input_id' value='$default'>
                </div>
                ".fTooltip($tooltip_id,$tooltips)."
            </div>            
        </div>";
    
    return $html;
}

function form_checkbox($tooltip_id,$labelText,$value,$checked,$tooltips){
    $parts = explode('-',$tooltip_id);
    $input_id = $parts[1];
       
    $html = "<div class='checkbox checkbox_wrapper'>
                <label>";
                    if($checked)
                        $html .= "<input type='checkbox'  name='$input_id' value='$value' checked=''> $labelText";
                    else
                        $html .= "<input type='checkbox'  name='$input_id' value='$value'> $labelText";
    $html .=    "</label>
            </div>";
    
    return $html;
}

function fTooltip($tooltip_id,$tooltips){
    $tooltipHtml = "<div class='col-sm-1'>";
        if(!empty($tooltips[$tooltip_id])){
            $tooltipHtml .= "<img src='".asset('images/info.png')."' class='info-button' data-container='body' data-toggle='popover' data-placement='left' data-content='".$tooltips[$tooltip_id]."'>";
        }
    $tooltipHtml .= "</div>";
    return $tooltipHtml;
}


function flatten($input_array){
    $output = array();
    array_walk_recursive($input_array, function ($current) use (&$output) {
        $output[] = $current;
    });
    return $output;
}

function safe_filename($string) {
    //Lower case everything
    //$string = strtolower($string); Is this necessery?
    //Make alphanumeric (removes all other characters)
    $string = preg_replace("/[^\pL\pN\s.\(\)_-]/u",'', $string);
    //Clean up multiple dashes or whitespaces
    $string = preg_replace("/[\s-]+/", " ", $string);
    //Convert whitespaces and underscore to dash
    $string = preg_replace("/[\s_]/", "-", $string);
    return $string;
}

// Calculate directory size in KB
function directory_size($directory){
    $output = null;
    exec('du -ch '.$directory.' | grep total',$output);
    $output_parts = preg_split('/\s+/', $output[0]); 
    $size_info = $output_parts[0];
    $metric = substr($size_info,-1);
    switch($metric){
        case 'K':
            $multiplier = 1000;
            $number = substr($size_info,0,-1);
            break;
        case 'M':
            $multiplier = 1000000;
            $number = substr($size_info,0,-1);
            break;
        case 'G':
            $multiplier = 1000000000;
            $number = substr($size_info,0,-1);
            break;
        default:
            $multiplier = 1;
            $number = $size_info;
    }
    $total = round($number*$multiplier/1000);
    return $total;
}

// Deletes a folder with its contents
function delete_folder($folder){
    $it = new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it,RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file) {
        if ($file->isDir()){
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    return rmdir($folder);
}

function rmdir_recursive($dir) {    
    try {
        // List the contents of the directory table    
        $dir_content = scandir($dir);    
        // Is it a directory?    
        if ($dir_content !== FALSE) {   
            // For each directory entry    
            foreach ($dir_content as $entry) {      
                // Unix symbolic shortcuts, we go    
                if (!in_array ($entry, array ('.','..'))){    
                    // We find the path from the beginning    
                    $entry = $dir. '/'. $entry;                                                                
                    // This entry is not an issue: it clears    
                    if (!is_dir($entry)) {    
                        unlink($entry);    
                    } // This entry is a folder, it again on this issue    
                    else {
                        rmdir_recursive($entry);    
                    }   
                }    
            }
        }    
        
        // It has erased all entries in the folder, we can now delete ut   
        return rmdir($dir);
    } catch (Exception $ex) {
        return false;
    }
         
}    