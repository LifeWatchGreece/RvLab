<?php

/**
 * Builds functionality related to access control and logging
 *
 * @license MIT
 * @author Alexandros Gougousis
 */
class AuthController extends BaseController {
    
    protected $user_status;
    protected $system_settings = array();
    protected $registration;
    protected $is_admin;
    protected $is_mobile;
    
    public function __construct(){    
        // Identify if the request comes from a mobile client
        if(isset($_SERVER['HTTP_AAAA1']))
            $this->is_mobile = true;
        else    
            $this->is_mobile = false;     
        
        $settings = Setting::all();
        foreach($settings as $set){
            $this->system_settings[$set->name] = $set->value;
        }
        
        if(Auth::check()){
            if(Auth::user()->admin == 0){
                $this->is_admin = false;
            } else {
                $this->is_admin = true;
            }
            $this->user_status['email'] = Auth::user()->username;
            $this->user_status['timezone'] = 'Europe/Athens';
        } else {
            $this->is_admin = false;
        }
        
    }
    
    /**
     * Logs the user out 
     * 
     * @return ResponseRedirect
     */
    public function logout(){
        Auth::logout();
        return Redirect::to('/');
    }
    
    /**
     * Checks if the user has administrative privileges
     * 
     * @return boolean
     */
    protected function is_admin(){
        return $this->is_admin;
    }
    
    /**
     * Checks if the user has an active R vLab registration and if not, it redirects him
     * to login page (if his is not logged in) or registration page (if he has not registered). 
     * (this should be called only by routes that requires a logged in user)
     * 
     * @return Redirect|void
     */
    protected function check_registration(){
        
        if (!Auth::check()){ 
            // Just in case the user is not logged in (should not happen normally)
            $this->redirect_to_portal_login();
        } else {
            // Check if has an active registration
            $registration = Registration::where('user_email',$this->user_status['email'])->where('ends','>',date('Y-m-d H:i:s'))->get()->toArray();               
            if(empty($registration)){                
                header("Location: ".url('registration'));
                die();             
            } else {
                $this->registration = $registration[0];               
            }
        }
    }
    
    /**
     * Checks if the user has an active R vLab registration.
     * 
     * @return boolean
     */
    protected function isRegistered(){
        if (empty($this->user_status)){ // This case should not ever happen 
            return false;
        } else {
            // Check if has an active registration
            $registration = Registration::where('user_email',$this->user_status['email'])->where('ends','>',date('Y-m-d H:i:s'))->get()->toArray();    
            if(empty($registration)){                
                return false;             
            } else {
                return true;
            }
        }
    }
    
    /**
     * Provides a CSRF token to mobile application. Since the mobile app submits 
     * forms to the same URLs as the web app, it needs to include a CSRF token as well.
     * 
     * @return JSON
     */
    public function get_token(){
        if($this->is_mobile){       
            $token = csrf_token();
            $response = array(
                'token' =>  $token,
                'when'  =>  date('Y-m-d H:i:s')
            );
            return Response::json($response,200);
        }        
    }    
    
    /**
     * Saves a log to the database
     * 
     * @param string $message
     * @param string $category
     */
    protected function log_event($message,$category){
	
        $route = explode('@',Route::currentRouteAction()); 
         
        if (!empty($this->user_status)){
            if(!empty($this->user_status['email'])){
                $user_id = $this->user_status['email'];
            } else {
                $user_id = 'visitor';
            }                                    
        } else {
            $user_id = '';
            $category = 'error';
            $message = 'User status could not be retrieved during logging action. Original message was: '.$message;
        }                

	$log = new SystemLog();
	$log->when 	=   date("Y-m-d H:i:s");
	$log->user_email =   $user_id;
	$log->controller =  $route[0];
	$log->method 	=   $route[1];
	$log->message 	=   $message;
        $log->category   =   $category;
	$log->save();
    }
    
    /**
     * Loads a View using a template file and the HTML wrapper parts provided by the portal. 
     * 
     * @param string $the_view
     * @param string $title
     * @param array $data
     * @return View
     */
    protected function load_view($the_view,$title,$data = array()){
        
        if(Auth::check()){
            $template_folder = 'logged_template';
        } else {
            $template_folder = 'visitor_template';
        }
        
        $head = View::make("$template_folder.head")->with('title',$title);
        $body_top = View::make("$template_folder.body_top");
        $body_bottom = View::make("$template_folder.body_bottom");
        
        $content = View::make($the_view,$data);                
        
        return View::make("$template_folder.template")
                ->with('title',$title)
                ->with('head',$head)
                ->with('body_top',$body_top)
                ->with('body_bottom',$body_bottom)
                ->with('content',$content);
        
    }
    
    /**
     * Displays a page with a message about unauthorized action
     * 
     * @return View
     */
    protected function unauthorizedAccess(){

        return $this->load_view('errors/unauthorized','Unauthorized access');
        
    }
    
    /**
     * Displays a page with a message about unauthorized action
     * 
     * @return View
     */
    protected function illegalAction(){
        
        return $this->load_view('errors/illegalAction','Illegal action');
        
    }
    
    /**
     * Displays a page with a message about unexpected error.
     * 
     * @return View
     */
    protected function unexpected_error(){
        
        return $this->load_view('errors/unexpected','Unexpected error');
        
    }
    
    /**
     * Checks if the remote (cluster) storage has been mounted
     * 
     * @return boolean
     */
    protected function check_storage(){
        $jobs_path = Config::get('rvlab.jobs_path');
        if(!file_exists($jobs_path)){
            return false;
        } else {
            return true;
        }
    }    
    
}

