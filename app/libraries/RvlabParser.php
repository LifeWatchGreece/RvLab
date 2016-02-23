<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of RvlabParser
 *
 * @author Alexandros
 */
class RvlabParser {
    
    private $error_string;
    private $output_string;
    private $buffer_string;
    
    public function __construct() {
        $this->error_string = array();
        $this->output_string = array();
    }
    
    public function parse_log($log_file){
        $log_text = "";
        if(file_exists($log_file)){
            $handle = fopen($log_file, "r");
            if ($handle) {
                while (($line = fgets($handle)) !== false) { 
                    $log_text .= "<br>".$line;
                }
                fclose($handle);
                return $log_text;
            } else {
                return "";
            }
        } else {
            return "";
        }
    }
    
    public function parse_output($filepath){
        
        $this->error_string = array();
        $this->output_String = array();
        $this->buffer_string = array();
        
        try {            
            $handle = fopen($filepath, "r");
            $found_nothing = true;
            if ($handle) {
                while (($line = fgets($handle)) !== false) { 
                    $this->buffer_string[] = $line;
                    if (((strpos($line,'Error') !== false)||(strpos($line,'error') != false))&&(strpos($line,'Initializing database from') == false)) {                    
                        $found_nothing = false;
                        $this->error_string[] = $line;
                        while (($line = fgets($handle)) !== false) {
                            $this->error_string[] = "<br>".$line;
                        }
                    } elseif(strpos($line,'Primary job') !== false){
                        $found_nothing = false;
                        $this->error_string[] = $line;
                        while (($line = fgets($handle)) !== false) {
                            $this->error_string[] = "<br>".$line;
                        }
                    } elseif(strpos($line,'unable to launch the specified') !== false){
                        $found_nothing = false;
                        $this->error_string[] = $line;
                        while (($line = fgets($handle)) !== false) {
                            $this->error_string[] = "<br>".$line;
                        }
                    } elseif (strpos($line,'summary') !== false){
                        $found_nothing = false;
                        while (($line = fgets($handle)) !== false) {
                            $this->output_string[] = "<br>".$line;
                        }
                    }                                        
                }
                if($found_nothing){
                    $this->output_string = $this->buffer_string;
                }
                fclose($handle);
            } else {
                $this->error_string[] = "Output of R script could not be opened!";
            } 
        } catch (Exception $ex) {
            $this->error_string[] = "Unexpected error happened when parsing the output of R script.";
            $this->error_string[] = "<br>Error message: ".$ex->getMessage();
        }
        
    }
    
    public function hasFailed(){
        if(!empty($this->error_string)){
            return true;
        } else {
            return false;
        }
    }
    
    public function getOutput(){
        if(!empty($this->error_string)){
            return $this->error_string;
        } else {
            return $this->output_string;
        }
    }
    
}
