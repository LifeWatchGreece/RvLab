<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of VisitorController
 *
 * @author Alexandros
 */
class VisitorController extends AuthController {
    
    public function __construct() {
        parent::__construct();
        $this->workspace_path = Config::get('rvlab.workspace_path');
        $this->jobs_path = Config::get('rvlab.jobs_path');
        $this->remote_jobs_path = Config::get('rvlab.remote_jobs_path');
        $this->remote_workspace_path = Config::get('rvlab.remote_workspace_path');
            
        // Identify if the request comes from a mobile client
        if(isset($_SERVER['HTTP_AAAA1']))
            $this->is_mobile = true;
        else    
            $this->is_mobile = false;                                           
        
        // Check if cluster storage has been mounted to web server
        if(!$this->check_storage()){          
            if($this->is_mobile){          
                $response = array('message','Storage not found');
                return Response::json($response,500);
                die();
            } else {
                echo $this->load_view('errors/unmounted','Storage not found');
                die();
            }               
        }
               
    }      
    
    /**
     * Handles the submission of login form
     * 
     * @return ResponseRedirect
     */
    public function login(){
        $form = Input::all();                              
        $rules = Config::get('validation.login');
        $validation = Validator::make($form,$rules);
         
        if ($validation->fails()){
            return Redirect::back()->withErrors($validation);
        } else {
            // Compare credentials with database
            if (Auth::attempt(array('username' => $form['username'], 'password' => $form['password']))) {
                return Redirect::to('/');
            } else {
                return Redirect::back()->with('auth_error','Wrong username or password!');
            }            
        }
    }
    
    /**
     * Displays the Home Page
     * 
     * @return View
     */    
    public function index(){
        if (Auth::check()){
            $this->check_registration();  
            return $this->home_page();
        } else {
            return $this->login_page();
        }
    }
    
    /**
     * Displays the login page
     * 
     * @return View
     */
    private function login_page(){
        return $this->load_view('login','Login Page');
    }
    
    /**
     * Displays the R vLab home page
     * 
     * @return View
     */
    private function home_page() {
        $user_email = $this->user_status['email'];                    

        $job_list = DB::table('jobs')->where('user_email',$user_email)->orderBy('id','desc')->get();
        $r_functions = Config::get('r_functions.list');
        $form_data['workspace_files'] = WorkspaceFile::getUserFiles($user_email);
        $form_data['user_email'] = $user_email;
        $form_data['tooltips'] = Config::get('tooltips');
        
        if($this->is_mobile){
            $response = array(
                'r_functions'   =>  $r_functions,
                'job_list'      =>  $job_list,
                'workspace_files'   =>  $form_data['workspace_files'],                
            );
            return Response::json($response,200);
        } else {
            $forms = array();
            foreach($r_functions as $codename => $title){
                $forms[$codename] = View::make('forms.'.$codename,$form_data);
            }

            $data['forms'] = $forms;
            $data['job_list'] = $job_list;
            $data['r_functions'] = $r_functions;

            $data2['workspace_files'] = $form_data['workspace_files'];              
            
            // Calculate user storage utilization
            $rvlab_storage_limit = $this->system_settings['rvlab_storage_limit'];
            $max_users_suported = $this->system_settings['max_users_suported'];            
            $jobs_path = Config::get('rvlab.jobs_path');
            $workspace_path = Config::get('rvlab.workspace_path');
            
            $inputspace_totals = directory_size($workspace_path.'/'.$user_email); // in KB
            $jobspace_totals = directory_size($jobs_path.'/'.$user_email); // in KB
            
            $data2['storage_utilization'] = 100*($inputspace_totals+$jobspace_totals)/($rvlab_storage_limit/$max_users_suported);
            $data2['totalsize'] = $inputspace_totals+$jobspace_totals;
            $data['workspace'] = View::make('workspace.manage',$data2);
            $data['is_admin'] = $this->is_admin();
            $data['refresh_rate'] = $this->system_settings['status_refresh_rate_page'];
            $data['timezone'] = $this->user_status['timezone'];
            //$data['refresh_rate'] = $this->system_settings['status_refresh_rate_page'];

            // Check if this we load the page after delete_many_jobs() has been called
            if(Session::has('deletion_info')){
                $data['deletion_info'] = Session::get('deletion_info');
            }
            
            //
            if(Session::has('workspace_tab_status')){
                $data['workspace_tab_status'] = Session::get('workspace_tab_status');
            } else {
                $data['workspace_tab_status'] = 'closed';
            }
            
            //
            if(Session::has('last_function_used')){
                $data['last_function_used'] = Session::get('last_function_used');
            } else {
                $data['last_function_used'] = "taxa2dist";
            }
            
            return $this->load_view('index','R vLab Home Page',$data);
        }
                
    }   
    
}
