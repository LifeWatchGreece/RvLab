<?php

/**
 * Implements functionality that allows a user to register for R vLab.
 * Registration is just a declaration of the user that he is going to use R vLab
 * at least for a period.
 * 
 * @license MIT
 * @author Alexandros Gougousis
 */
class RegistrationController extends AuthController {
    
    public function __construct() {
        parent::__construct();     
        
        // Check if cluster storage has been mounted to web server
        if(!$this->check_storage()){          
            if($this->is_mobile){          
                $response = array('message','Storage not found');
                return Response::json($response,500);
            } else {
                echo $this->load_view('errors/unmounted','Storage not found');
                die();
            }               
        }                
    }    
    
    /**
     * Displays the registration page
     * 
     * @return View
     */
    public function registration_page(){                
        
        if($this->isRegistered()){
            return Redirect::to('/');
        } else {
            $max_users_suported = $this->system_settings['max_users_suported'];
            $count_current_users = Registration::where('ends','>',date('Y-m-d H:i:s'))->count();
            
            if($count_current_users >= $max_users_suported){
                return $this->load_view('run_out_of_users','Registration Impossible');
            } else {
                return $this->load_view('registration','Registration');
            }                        
        }
        
    }
    
    /**
     * Checks if the user is registered for R vLab.
     * 
     * @param string $user_email
     * @return JSON
     */
    public function is_registered($user_email){
        
        if($this->is_mobile){ 
        
            if(!empty($user_email)){
                $registration = Registration::where('user_email',$user_email)->where('ends','>',date('Y-m-d H:i:s'))->get()->toArray();    
                if(empty($registration)){                
                    $response = array(
                        'registered'  =>  'no'
                        );
                    return Response::json($response,200);            
                } else {
                    $response = array(
                        'registered'  =>  'yes'
                        );
                    return Response::json($response,200);              
                }
            } else {
                $response = array();
                return Response::json($response,400);
            }              
            
        }
    }
    
    /**
     * Registers a user to R vLab.
     * 
     * @return RedirectResponse|JSON
     */
    public function register(){
        
        // Make sure the user is not already registered
        if($this->isRegistered()){
            if($this->is_mobile){          
                $response = array(
                    'registered' => 'failed',
                    'message'    => 'You are already registered'  
                    );
                return Response::json($response,200);
            } else {
                return Redirect::to('/');
            }  
        } 
        
        $form = Input::all();
        
        // Make sure the form is valid
        if(empty($form['registration_period'])){
            if($this->is_mobile){          
                $response = array(
                    'registered' => 'failed',
                    'message'    => 'Registration period was not provided'  
                    );
                return Response::json($response,200);
            } else {
                return $this->illegalAction();
            }              
        }
        $period = $form['registration_period'];
        
        // Make sure the registration period is valid
        if(!in_array($period, array('day','week','month','semester'))){
            if($this->is_mobile){          
                $response = array(
                    'registered' => 'failed',
                    'message'    => 'The registration period is invalid'  
                    );
                return Response::json($response,200);
            } else {
                return $this->illegalAction();
            }                         
        }
        
        // Decide the registration period dates
        $starts = date('Y-m-d H:i:s');
        $ends = new DateTime();               
        switch($period){
            case 'day':
                $ends->add(new DateInterval('P1D')); // add 60 seconds
                break;
            case 'week':
                $ends->add(new DateInterval('P7D')); // add 60 seconds
                break;
            case 'month':
                $ends->add(new DateInterval('P30D')); // add 60 seconds
                break;
            case 'semester':
                $ends->add(new DateInterval('P6M')); // add 60 seconds
                break;
        }
        
        // Make the registration
        $registration = new Registration();
        $registration->user_email = $this->user_status['email'];
        $registration->starts = $starts;
        $registration->ends = $ends;
        $registration->save();
        
        if($this->is_mobile){          
            $response = array('registered' => 'done');
            return Response::json($response,200);
        } else {
            return Redirect::to('/');
        }           
    }
    
}